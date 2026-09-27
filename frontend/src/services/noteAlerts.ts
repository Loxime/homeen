import Swal from 'sweetalert2'

export const pinnedNoteLimitMessage =
  'Vous ne pouvez pas épingler plus de 20 notes. Désépinglez une autre note avant de continuer.'

export function isPinnedNoteLimitError(
  error: unknown,
): boolean {
  return (
    error instanceof Error
    && error.message
      === pinnedNoteLimitMessage
  )
}

export async function showPinnedNoteLimitAlert():
Promise<void> {
  await Swal.fire({
    icon: 'warning',
    title: 'Limite atteinte',
    text:
      pinnedNoteLimitMessage,
    confirmButtonText: 'Compris',
  })
}
