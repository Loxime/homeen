import { computed, reactive } from 'vue'
import { api } from '../services/api'
import { calculatePomodoroState, type PomodoroLiveState } from '../services/pomodoroMath'
import type { PomodoroPreset, PomodoroSession } from '../types/domain'

interface PomodoroStore {
  active: PomodoroSession | null
  live: PomodoroLiveState | null
  presets: PomodoroPreset[]
  loading: boolean
}

const store = reactive<PomodoroStore>({ active: null, live: null, presets: [], loading: false })
const channel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('homeen-pomodoro') : null
let timer: number | null = null
let audioContext: AudioContext | null = null
let previousPhase: 'work' | 'break' | null = null
let channelListening = false

function enableAudio(): void {
  if (!audioContext) audioContext = new AudioContext()
  if (audioContext.state === 'suspended') void audioContext.resume()
}

function beep(phase: 'work' | 'break'): void {
  if (!audioContext || audioContext.state !== 'running') return
  const oscillator = audioContext.createOscillator()
  const gain = audioContext.createGain()
  oscillator.type = 'sine'
  oscillator.frequency.value = phase === 'work' ? 740 : 520
  gain.gain.setValueAtTime(0.0001, audioContext.currentTime)
  gain.gain.exponentialRampToValueAtTime(0.15, audioContext.currentTime + 0.01)
  gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.22)
  oscillator.connect(gain)
  gain.connect(audioContext.destination)
  oscillator.start()
  oscillator.stop(audioContext.currentTime + 0.24)
}

function tick(): void {
  if (!store.active) {
    store.live = null
    previousPhase = null
    document.title = 'Homeen'
    return
  }

  const next = calculatePomodoroState(store.active.startedAt, store.active.workMinutes)
  if (previousPhase && next.phase !== previousPhase) beep(next.phase)
  previousPhase = next.phase
  store.live = next
  const minutes = Math.floor(next.remainingSeconds / 60).toString().padStart(2, '0')
  const seconds = (next.remainingSeconds % 60).toString().padStart(2, '0')
  document.title = `[${minutes}:${seconds}] ${next.phase === 'work' ? 'Work' : 'Break'} — Homeen`
}

async function loadActive(): Promise<void> {
  const response = await api<{ session: PomodoroSession | null }>('/api/pomodoro/active')
  store.active = response.session
  tick()
}

async function loadPresets(): Promise<void> {
  const response = await api<{ presets: PomodoroPreset[] }>('/api/pomodoro/presets')
  store.presets = response.presets
}

async function start(workMinutes: number): Promise<void> {
  enableAudio()
  store.active = await api<PomodoroSession>('/api/pomodoro/sessions', {
    method: 'POST',
    body: JSON.stringify({ workMinutes }),
  })
  previousPhase = 'work'
  channel?.postMessage({ type: 'started' })
  await loadPresets()
  tick()
}

async function quickStart(): Promise<void> {
  enableAudio()
  store.active = await api<PomodoroSession>('/api/pomodoro/quick-start', { method: 'POST' })
  previousPhase = store.active.phase === 'break' ? 'break' : 'work'
  channel?.postMessage({ type: 'started' })
  await loadPresets()
  tick()
}

async function stop(): Promise<void> {
  if (!store.active) return
  await api(`/api/pomodoro/sessions/${store.active.id}/stop`, { method: 'POST' })
  store.active = null
  store.live = null
  previousPhase = null
  document.title = 'Homeen'
  channel?.postMessage({ type: 'stopped' })
}

function startGlobalTimer(): void {
  if (timer !== null) return
  void loadActive()
  timer = window.setInterval(tick, 1000)
  if (!channelListening) {
    channel?.addEventListener('message', () => void loadActive())
    channelListening = true
  }
  window.addEventListener('pointerdown', enableAudio, { once: true })
  window.addEventListener('keydown', enableAudio, { once: true })
}

function stopGlobalTimer(): void {
  if (timer !== null) {
    window.clearInterval(timer)
    timer = null
  }
  store.active = null
  store.live = null
  previousPhase = null
  document.title = 'Homeen'
}

export function usePomodoro() {
  return {
    store,
    hasPreset: computed(() => store.presets.length > 0),
    loadActive,
    loadPresets,
    start,
    quickStart,
    stop,
    startGlobalTimer,
    stopGlobalTimer,
    enableAudio,
  }
}
