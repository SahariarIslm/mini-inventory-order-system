import { useState, type FormEvent } from 'react'
import { ApiError } from '../../api/client'
import { productsApi } from '../../api/products'
import type { Product } from '../../api/types'
import { Field } from '../../components/Field'
import { Modal } from '../../components/Modal'

interface AdjustStockModalProps {
  product: Product
  onClose: () => void
  onSaved: (product: Product) => void
}

type Direction = 'add' | 'remove'

export function AdjustStockModal({ product, onClose, onSaved }: AdjustStockModalProps) {
  const [direction, setDirection] = useState<Direction>('add')
  const [amount, setAmount] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [fieldError, setFieldError] = useState<string | undefined>()
  const [submitting, setSubmitting] = useState(false)

  const parsed = /^\d+$/.test(amount) ? Number(amount) : null
  const preview = parsed === null ? null : product.stock_quantity + (direction === 'add' ? parsed : -parsed)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setFieldError(undefined)
    setSubmitting(true)

    try {
      // The API takes a signed, relative change — never an absolute value.
      onSaved(await productsApi.adjustStock(product.id, direction === 'remove' ? `-${amount}` : amount))
    } catch (err) {
      if (err instanceof ApiError && err.status === 409) {
        // The server decides against the locked, current stock level, which
        // may differ from what this page loaded.
        const available = (err.body as { available?: number } | null)?.available
        setError(`Not enough stock to remove that many — only ${available ?? 'fewer'} currently in stock.`)
      } else if (err instanceof ApiError && err.fieldError('quantity')) {
        setFieldError(err.fieldError('quantity'))
      } else {
        setError(err instanceof ApiError ? err.message : 'Something went wrong.')
      }
      setSubmitting(false)
    }
  }

  return (
    <Modal title={`Adjust stock — ${product.name}`} onClose={onClose}>
      <form className="form" onSubmit={handleSubmit} noValidate>
        {error && (
          <p className="alert alert--error" role="alert">
            {error}
          </p>
        )}

        <fieldset className="segmented">
          <legend className="field__label">Change</legend>
          {(['add', 'remove'] as const).map((d) => (
            <label key={d} className={`segmented__option ${direction === d ? 'is-selected' : ''}`}>
              <input type="radio" name="direction" value={d} checked={direction === d} onChange={() => setDirection(d)} />
              {d === 'add' ? 'Restock (+)' : 'Write off (−)'}
            </label>
          ))}
        </fieldset>

        <Field label="Quantity" error={fieldError}>
          <input
            type="number"
            min={1}
            step={1}
            value={amount}
            onChange={(e) => setAmount(e.target.value)}
            aria-invalid={Boolean(fieldError)}
            autoFocus
          />
        </Field>

        <p className="muted">
          Currently {product.stock_quantity} in stock
          {preview !== null && (
            <>
              {' → '}
              <strong className={preview < 0 ? 'text-danger' : undefined}>{preview}</strong> after this change
            </>
          )}
        </p>

        <div className="form__actions">
          <button type="button" className="button button--ghost" onClick={onClose}>
            Cancel
          </button>
          <button type="submit" className="button button--primary" disabled={submitting || !amount}>
            {submitting ? 'Saving…' : 'Apply'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
