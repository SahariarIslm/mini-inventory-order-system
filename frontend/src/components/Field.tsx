import type { ReactNode } from 'react'

interface FieldProps {
  label: string
  error?: string
  hint?: ReactNode
  children: ReactNode
}

/** Label + control + inline validation message. */
export function Field({ label, error, hint, children }: FieldProps) {
  return (
    <label className="field">
      <span className="field__label">{label}</span>
      {children}
      {hint && !error && <span className="field__hint">{hint}</span>}
      {error && <span className="field__error">{error}</span>}
    </label>
  )
}
