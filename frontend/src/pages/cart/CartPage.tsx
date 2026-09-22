import { useState } from 'react'
import { Link } from 'react-router'
import { ApiError } from '../../api/client'
import { ordersApi } from '../../api/orders'
import type { InsufficientStockBody, Order } from '../../api/types'
import type { CartLine } from '../../cart/CartContext'
import { useCart } from '../../cart/useCart'
import { OrderItemsTable } from '../../components/OrderItemsTable'
import { newIdempotencyKey } from '../../lib/idempotencyKey'
import { formatCents, toCents } from '../../lib/money'

/** A key is only valid for the exact cart contents it was created for. */
interface Attempt {
  key: string
  lines: CartLine[]
}

export function CartPage() {
  const { lines, setQuantity, remove, clear } = useCart()

  const [attempt, setAttempt] = useState<Attempt | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [shortages, setShortages] = useState<Record<number, number>>({})
  const [placed, setPlaced] = useState<Order | null>(null)

  const estimatedCents = lines.reduce((sum, line) => sum + toCents(line.product.price) * line.quantity, 0)

  async function placeOrder() {
    // Same cart as the last attempt (e.g. retry after a network error):
    // reuse its key so the server can't create a second order.
    // Any cart change produced a new `lines` array, so that gets a new key.
    const key = attempt?.lines === lines ? attempt.key : newIdempotencyKey()
    setAttempt({ key, lines })
    setSubmitting(true)
    setError(null)
    setShortages({})

    try {
      const order = await ordersApi.place(
        lines.map((line) => ({ product_id: line.product.id, quantity: line.quantity })),
        key,
      )
      setPlaced(order)
      setAttempt(null)
      clear()
    } catch (err) {
      const failure = describeFailure(err, lines)
      setError(failure.message)
      if (failure.shortage) setShortages({ [failure.shortage.productId]: failure.shortage.available })
    } finally {
      setSubmitting(false)
    }
  }

  if (placed) {
    return (
      <section className="card">
        <h1>Order #{placed.id} placed</h1>
        <p className="muted">Stock has been reserved for every item below.</p>
        <OrderItemsTable order={placed} />
        <div className="form__actions">
          <Link to={`/orders/${placed.id}`} className="button button--ghost">
            View order
          </Link>
          <Link to="/products" className="button button--primary">
            Continue shopping
          </Link>
        </div>
      </section>
    )
  }

  if (lines.length === 0) {
    return (
      <section className="card">
        <h1>Your order</h1>
        <p className="muted">
          Nothing here yet. <Link to="/products">Browse products</Link> and add some.
        </p>
      </section>
    )
  }

  return (
    <section>
      <div className="page-header">
        <div>
          <h1>Your order</h1>
          <p className="muted">Stock is checked and reserved when you place the order.</p>
        </div>
      </div>

      {error && (
        <p className="alert alert--error" role="alert">
          {error}
        </p>
      )}

      <div className="card card--flush">
        <table className="table">
          <thead>
            <tr>
              <th>Product</th>
              <th className="num">Price</th>
              <th>Quantity</th>
              <th className="num">Subtotal</th>
              <th className="actions-col" aria-label="Actions" />
            </tr>
          </thead>
          <tbody>
            {lines.map((line) => {
              const available = shortages[line.product.id]
              return (
                <tr key={line.product.id}>
                  <td>
                    <div className="cell-title">{line.product.name}</div>
                    <div className="cell-sub">{line.product.sku}</div>
                    {available !== undefined && (
                      <div className="field__error">
                        {available === 0 ? 'Sold out — remove it to continue.' : `Only ${available} available — lower the quantity.`}
                      </div>
                    )}
                  </td>
                  <td className="num">{line.product.price}</td>
                  <td>
                    <QuantityStepper
                      value={line.quantity}
                      onChange={(quantity) => setQuantity(line.product.id, quantity)}
                      label={`Quantity of ${line.product.name}`}
                    />
                  </td>
                  <td className="num">{formatCents(toCents(line.product.price) * line.quantity)}</td>
                  <td className="actions-col">
                    <button type="button" className="link-button link-button--danger" onClick={() => remove(line.product.id)}>
                      Remove
                    </button>
                  </td>
                </tr>
              )
            })}
          </tbody>
          <tfoot>
            <tr>
              <td colSpan={3} className="num">
                <strong>Estimated total</strong>
              </td>
              <td className="num">
                <strong>{formatCents(estimatedCents)}</strong>
              </td>
              <td />
            </tr>
          </tfoot>
        </table>
      </div>

      <div className="form__actions">
        <Link to="/products" className="button button--ghost">
          Add more
        </Link>
        <button type="button" className="button button--primary" disabled={submitting} onClick={() => void placeOrder()}>
          {submitting ? 'Placing order…' : 'Place order'}
        </button>
      </div>
    </section>
  )
}

interface Failure {
  message: string
  shortage?: { productId: number; available: number }
}

function describeFailure(err: unknown, lines: CartLine[]): Failure {
  if (!(err instanceof ApiError)) {
    return { message: 'Something went wrong. Please try again.' }
  }

  if (err.status === 409) {
    // Someone else got there first; the server checked against locked stock.
    const body = err.body as InsufficientStockBody
    const line = lines.find((l) => l.product.id === body.product_id)
    return {
      message: `Not enough stock for ${line?.product.name ?? 'an item'}: only ${body.available} left. Nothing was ordered.`,
      shortage: { productId: body.product_id, available: body.available },
    }
  }

  if (err.status === 0) {
    // We can't know whether the request reached the server. Retrying is
    // safe: the same idempotency key is reused for an unchanged cart.
    return {
      message: `${err.message} Your order may or may not have gone through — it's safe to press “Place order” again; it will not create a duplicate.`,
    }
  }

  if (err.status === 422) {
    // e.g. a product was deleted since it was added to the cart.
    return { message: Object.values(err.errors).flat()[0] ?? err.message }
  }

  return { message: err.message }
}

function QuantityStepper({ value, onChange, label }: { value: number; onChange: (n: number) => void; label: string }) {
  return (
    <div className="stepper">
      <button type="button" aria-label="Decrease" disabled={value <= 1} onClick={() => onChange(value - 1)}>
        −
      </button>
      <input
        type="number"
        min={1}
        step={1}
        aria-label={label}
        value={value}
        onChange={(e) => onChange(Number.parseInt(e.target.value, 10))}
      />
      <button type="button" aria-label="Increase" onClick={() => onChange(value + 1)}>
        +
      </button>
    </div>
  )
}
