import { api, getCsrfToken } from '../services/api'

const TAB_ID = crypto.randomUUID()
const LOCK_KEY = 'homeen-usage-leader'
const LOCK_STALE_MS = 45_000
const HEARTBEAT_MS = 30_000
const IDLE_AFTER_MS = 120_000

interface LeaderLock {
  id: string
  timestamp: number
}

let usageSessionId: number | null = null
let lastInteraction = Date.now()
let lastHeartbeat = Date.now()
let interval: number | null = null
let started = false
let previousVisibility = document.visibilityState

function readLock(): LeaderLock | null {
  try {
    const raw = localStorage.getItem(LOCK_KEY)
    return raw ? (JSON.parse(raw) as LeaderLock) : null
  } catch {
    return null
  }
}

function isLeader(): boolean {
  const now = Date.now()
  const lock = readLock()
  if (!lock || lock.id === TAB_ID || now - lock.timestamp > LOCK_STALE_MS) {
    localStorage.setItem(LOCK_KEY, JSON.stringify({ id: TAB_ID, timestamp: now }))
    return true
  }
  return false
}

function renewLeader(): boolean {
  if (document.visibilityState !== 'visible') return false
  if (!isLeader()) return false
  localStorage.setItem(LOCK_KEY, JSON.stringify({ id: TAB_ID, timestamp: Date.now() }))
  return true
}

async function ensureSession(): Promise<void> {
  if (usageSessionId !== null || !renewLeader()) return
  const response = await api<{ id: number }>('/api/usage/sessions', { method: 'POST' })
  usageSessionId = response.id
  lastHeartbeat = Date.now()
}

async function heartbeat(): Promise<void> {
  if (!renewLeader()) return
  await ensureSession()
  if (usageSessionId === null) return

  const now = Date.now()
  const activeUntil = Math.min(now, lastInteraction + IDLE_AFTER_MS)
  const seconds = document.visibilityState === 'visible'
    ? Math.max(0, Math.min(60, Math.round((activeUntil - lastHeartbeat) / 1000)))
    : 0
  lastHeartbeat = now
  await api(`/api/usage/sessions/${usageSessionId}/heartbeat`, {
    method: 'POST',
    body: JSON.stringify({ activeSeconds: seconds }),
  })
}

function markInteraction(): void {
  lastInteraction = Date.now()
}

function onVisibilityChange(): void {
  const wasVisible = previousVisibility === 'visible'
  previousVisibility = document.visibilityState
  if (document.visibilityState === 'visible') {
    lastHeartbeat = Date.now()
    void ensureSession()
    return
  }
  const lock = readLock()
  if (lock?.id === TAB_ID) {
    stopSession(wasVisible)
    localStorage.removeItem(LOCK_KEY)
  }
  lastHeartbeat = Date.now()
}

function onBeforeUnload(): void {
  const lock = readLock()
  if (lock?.id === TAB_ID) localStorage.removeItem(LOCK_KEY)
  stopSession()
}

function stopSession(wasVisible = document.visibilityState === 'visible'): void {
  if (usageSessionId === null) return
  const now = Date.now()
  const activeUntil = Math.min(now, lastInteraction + IDLE_AFTER_MS)
  const finalActiveSeconds = wasVisible ? Math.max(0, Math.min(60, Math.round((activeUntil - lastHeartbeat) / 1000))) : 0
  const token = getCsrfToken()
  if (!token) {
    usageSessionId = null
    return
  }
  const headers = new Headers({ 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token })
  void fetch(`/api/usage/sessions/${usageSessionId}/stop`, {
    method: 'POST',
    headers,
    credentials: 'same-origin',
    keepalive: true,
    body: JSON.stringify({ activeSeconds: finalActiveSeconds }),
  })
  usageSessionId = null
}

export function startUsageTracking(): void {
  if (started) return
  started = true
  previousVisibility = document.visibilityState
  lastInteraction = Date.now()
  lastHeartbeat = Date.now()
  for (const event of ['pointerdown', 'keydown', 'scroll', 'touchstart']) {
    window.addEventListener(event, markInteraction, { passive: true })
  }
  document.addEventListener('visibilitychange', onVisibilityChange)
  window.addEventListener('beforeunload', onBeforeUnload)

  void ensureSession()
  interval = window.setInterval(() => void heartbeat(), HEARTBEAT_MS)
}

export function stopUsageTracking(): void {
  if (!started) return
  started = false
  if (interval !== null) {
    window.clearInterval(interval)
    interval = null
  }
  for (const event of ['pointerdown', 'keydown', 'scroll', 'touchstart']) {
    window.removeEventListener(event, markInteraction)
  }
  document.removeEventListener('visibilitychange', onVisibilityChange)
  window.removeEventListener('beforeunload', onBeforeUnload)
  const lock = readLock()
  if (lock?.id === TAB_ID) localStorage.removeItem(LOCK_KEY)
  stopSession()
}
