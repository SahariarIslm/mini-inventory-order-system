import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { ApiError } from '../../api/client'
import { ordersApi } from '../../api/orders'
import type { Order } from '../../api/types'
import { useAuth } from '../../auth/useAuth'
import { OrderItemsTable } from '../../components/OrderItemsTable'
import { OrderStatusBadge } from '../../components/OrderStatusBadge'
import { useResource } from '../../hooks/useResource'
import { formatDateTime } from '../../lib/dates'

export function OrderDetailPage() {
  const { user } = useAuth()
  const id = Number(useParams().id)
  const order = useResource(ordersApi.get, id)

  // The cancel endpoint returns the updated order; show it directly.
  const [cancelled, setCancelled] = useState<Order | null>(null)
  const [cancelling, setCancelling] = useState(false)
  const [notice, setNotice] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  if (order.result === null) {
    if (order.status === 'error') {
      return (
        <section className="card">
          <h1>Order #{id}</h1>
          <p className="alert alert--error" role="alert">
            {describeLoadError(order.error)}
          </p>
          <p>
            <Link to="/orders">Back to orders</Link>
          </p>
        </section>
      )
    }
    return <p className="page-status">Loading order…</p>
  }

  const current = cancelled?.id === order.result.id ? cancelled : order.result
  const isOwn = current.user_id === user?.id
  // Mirrors OrderPolicy::cancel; the server enforces it regardless.
  const canCancel = current.status === 'confirmed' && (isOwn || user?.role === 'admin')

  async function handleCancel() {
    if (!window.confirm(`Cancel order #${current.id}? Its items will be returned to stock.`)) return

    setCancelling(true)
    setActionError(null)
    try {
      const updated = await ordersApi.cancel(current.id)
      const units = updated.items.reduce((sum, item) => sum + item.quantity, 0)
      setCancelled({ ...updated, user: current.user })
      setNotice(`Order cancelled — ${units} ${units === 1 ? 'unit' : 'units'} returned to stock.`)
    } catch (err) {
      setActionError(err instanceof ApiError ? err.message : 'Could not cancel the order.')
    } finally {
      setCancelling(false)
    }
  }

  return (
    <section>
      <p>
        <Link to="/orders">← Back to orders</Link>
      </p>

      <div className="page-header">
        <div>
          <h1>
            Order #{current.id} <OrderStatusBadge status={current.status} />
          </h1>
          <p className="muted">
            Placed {formatDateTime(current.created_at)}
            {current.user && !isOwn && <> by {current.user.name}</>}
          </p>
        </div>
        {canCancel && (
          <button type="button" className="button button--ghost button--danger" disabled={cancelling} onClick={() => void handleCancel()}>
            {cancelling ? 'Cancelling…' : 'Cancel order'}
          </button>
        )}
      </div>

      {notice && (
        <p className="alert alert--success" role="status">
          {notice}
        </p>
      )}
      {actionError && (
        <p className="alert alert--error" role="alert">
          {actionError}
        </p>
      )}

      <div className="card card--flush">
        <OrderItemsTable order={current} />
      </div>
    </section>
  )
}

function describeLoadError(error: ApiError): string {
  if (error.status === 404) return 'This order does not exist.'
  if (error.status === 403) return 'You can only view orders you placed.'
  return error.message
}
