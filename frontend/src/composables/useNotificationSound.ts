import {
  computed,
  ref,
} from 'vue'

import {
  api,
} from '../services/api'

interface ProfileNotificationResponse {
  notificationSoundEnabled: boolean
}

type AudioContextConstructor =
  new () => AudioContext

const enabled =
  ref(true)

const loaded =
  ref(false)

let loadingPromise:
  Promise<void>
  | null = null

let audioContext:
  AudioContext
  | null = null

let unlockListenersBound =
  false

function audioContextConstructor():
  AudioContextConstructor
  | null {
  const audioWindow =
    window as typeof window & {
      webkitAudioContext?:
        AudioContextConstructor
    }

  return (
    window.AudioContext
    ?? audioWindow.webkitAudioContext
    ?? null
  )
}

async function unlockAudio(): Promise<void> {
  try {
    if (!audioContext) {
      const Constructor =
        audioContextConstructor()

      if (!Constructor) {
        return
      }

      audioContext =
        new Constructor()
    }

    if (
      audioContext.state
      === 'suspended'
    ) {
      await audioContext.resume()
    }

    if (
      audioContext.state
      === 'running'
    ) {
      removeUnlockListeners()
    }
  } catch {
    /*
     * Browsers may deny audio until a later
     * user interaction. The application must
     * continue normally without sound.
     */
  }
}

function handleUserInteraction(): void {
  void unlockAudio()
}

function removeUnlockListeners(): void {
  if (!unlockListenersBound) {
    return
  }

  window.removeEventListener(
    'pointerdown',
    handleUserInteraction,
  )

  window.removeEventListener(
    'keydown',
    handleUserInteraction,
  )

  unlockListenersBound = false
}

function bindUnlockListeners(): void {
  if (unlockListenersBound) {
    return
  }

  unlockListenersBound = true

  window.addEventListener(
    'pointerdown',
    handleUserInteraction,
    {
      passive: true,
    },
  )

  window.addEventListener(
    'keydown',
    handleUserInteraction,
  )
}

async function load(): Promise<void> {
  if (loaded.value) {
    return
  }

  if (loadingPromise) {
    await loadingPromise

    return
  }

  loadingPromise = (
    async () => {
      try {
        const profile =
          await api<ProfileNotificationResponse>(
            '/api/profile',
          )

        enabled.value =
          profile.notificationSoundEnabled

        loaded.value = true
      } catch {
        /*
         * Notification sound is optional.
         * Failure to load the preference must
         * not affect the rest of Homeen.
         */
      } finally {
        loadingPromise = null
      }
    }
  )()

  await loadingPromise
}

function initialize(): void {
  bindUnlockListeners()
  void load()
}

function syncEnabled(
  value: boolean,
): void {
  enabled.value = value
  loaded.value = true
}

async function setEnabled(
  value: boolean,
): Promise<void> {
  const previous =
    enabled.value

  enabled.value = value
  loaded.value = true

  /*
   * This method is normally called from a click,
   * so try to unlock audio while user activation
   * is still available.
   */
  if (value) {
    void unlockAudio()
  }

  try {
    await api(
      '/api/profile/notifications',
      {
        method: 'PATCH',

        body: JSON.stringify({
          soundEnabled: value,
        }),
      },
    )
  } catch (exception) {
    enabled.value = previous

    throw exception
  }
}

function play(): void {
  if (
    !enabled.value
    || !audioContext
    || audioContext.state !== 'running'
  ) {
    return
  }

  try {
    const now =
      audioContext.currentTime

    const oscillator =
      audioContext.createOscillator()

    const gain =
      audioContext.createGain()

    oscillator.type = 'sine'

    oscillator.frequency
      .setValueAtTime(
        784,
        now,
      )

    oscillator.frequency
      .exponentialRampToValueAtTime(
        988,
        now + 0.12,
      )

    gain.gain
      .setValueAtTime(
        0.0001,
        now,
      )

    gain.gain
      .exponentialRampToValueAtTime(
        0.055,
        now + 0.015,
      )

    gain.gain
      .exponentialRampToValueAtTime(
        0.0001,
        now + 0.2,
      )

    oscillator.connect(
      gain,
    )

    gain.connect(
      audioContext.destination,
    )

    oscillator.start(
      now,
    )

    oscillator.stop(
      now + 0.21,
    )
  } catch {
    /*
     * Sound failure is never fatal.
     */
  }
}

export function useNotificationSound() {
  return {
    enabled:
      computed(
        () =>
          enabled.value,
      ),

    initialize,
    load,
    syncEnabled,
    setEnabled,
    play,
  }
}
