import { useState, type FormEvent } from 'react'
import { ApiError } from '../../api/client'
import { productsApi } from '../../api/products'
import type { Product, ProductInput } from '../../api/types'
import { Field } from '../../components/Field'
import { Modal } from '../../components/Modal'

interface ProductFormModalProps {
  /** Omit to create a new product. */
  product?: Product
  onClose: () => void
  onSaved: (product: Product) => void
}

const FIELDS = ['name', 'sku', 'description', 'price', 'stock_quantity'] as const

export function ProductFormModal({ product, onClose, onSaved }: ProductFormModalProps) {
  const isEdit = product !== undefined
  const [values, setValues] = useState<Required<ProductInput>>({
    name: product?.name ?? '',
    sku: product?.sku ?? '',
    description: product?.description ?? '',
    price: product?.price ?? '',
    stock_quantity: '0',
  })
  const [error, setError] = useState<ApiError | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const set = (field: keyof ProductInput) => (value: string) => setValues((v) => ({ ...v, [field]: value }))

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSubmitting(true)

    try {
      const saved = isEdit
        ? await productsApi.update(product.id, {
            name: values.name,
            sku: values.sku,
            description: values.description,
            price: values.price,
          })
        : await productsApi.create(values)
      onSaved(saved)
    } catch (err) {
      setError(err instanceof ApiError ? err : new ApiError(0, 'Something went wrong.'))
      setSubmitting(false)
    }
  }

  const fieldError = (field: (typeof FIELDS)[number]) => error?.fieldError(field)
  const hasFieldErrors = FIELDS.some((f) => fieldError(f))

  return (
    <Modal title={isEdit ? `Edit ${product.name}` : 'New product'} onClose={onClose}>
      <form className="form" onSubmit={handleSubmit} noValidate>
        {error && !hasFieldErrors && (
          <p className="alert alert--error" role="alert">
            {error.message}
          </p>
        )}

        <Field label="Name" error={fieldError('name')}>
          <input value={values.name} onChange={(e) => set('name')(e.target.value)} aria-invalid={Boolean(fieldError('name'))} autoFocus />
        </Field>

        <Field label="SKU" error={fieldError('sku')}>
          <input value={values.sku} onChange={(e) => set('sku')(e.target.value)} aria-invalid={Boolean(fieldError('sku'))} />
        </Field>

        <Field label="Description" error={fieldError('description')}>
          <textarea
            rows={3}
            value={values.description}
            onChange={(e) => set('description')(e.target.value)}
            aria-invalid={Boolean(fieldError('description'))}
          />
        </Field>

        <div className="form__row">
          <Field label="Price" error={fieldError('price')}>
            <input
              inputMode="decimal"
              placeholder="0.00"
              value={values.price}
              onChange={(e) => set('price')(e.target.value)}
              aria-invalid={Boolean(fieldError('price'))}
            />
          </Field>

          {isEdit ? (
            <Field label="Stock" hint="Use “Adjust stock” to change it.">
              <input value={product.stock_quantity} disabled />
            </Field>
          ) : (
            <Field label="Initial stock" error={fieldError('stock_quantity')}>
              <input
                type="number"
                min={0}
                step={1}
                value={values.stock_quantity}
                onChange={(e) => set('stock_quantity')(e.target.value)}
                aria-invalid={Boolean(fieldError('stock_quantity'))}
              />
            </Field>
          )}
        </div>

        <div className="form__actions">
          <button type="button" className="button button--ghost" onClick={onClose}>
            Cancel
          </button>
          <button type="submit" className="button button--primary" disabled={submitting}>
            {submitting ? 'Saving…' : isEdit ? 'Save changes' : 'Create product'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
