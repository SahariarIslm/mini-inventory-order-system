import { useEffect, useRef, type ReactNode } from 'react'

interface ModalProps {
  title: string
  onClose: () => void
  children: ReactNode
}

/**
 * Native <dialog> shown modally while mounted: the browser handles focus
 * trapping, Escape and the backdrop. Mount it conditionally to open it.
 */
export function Modal({ title, onClose, children }: ModalProps) {
  const ref = useRef<HTMLDialogElement>(null)

  useEffect(() => {
    const dialog = ref.current
    if (dialog && !dialog.open) dialog.showModal()
    return () => dialog?.close()
  }, [])

  return (
    <dialog
      ref={ref}
      className="modal"
      aria-labelledby="modal-title"
      onCancel={(e) => {
        // Escape: let React unmount us rather than the browser closing it.
        e.preventDefault()
        onClose()
      }}
    >
      <div className="modal__header">
        <h2 id="modal-title">{title}</h2>
        <button type="button" className="icon-button" aria-label="Close" onClick={onClose}>
          ×
        </button>
      </div>
      {children}
    </dialog>
  )
}
