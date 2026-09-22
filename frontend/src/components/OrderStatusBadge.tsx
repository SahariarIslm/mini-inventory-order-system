import type { OrderStatus } from '../api/types'

export function OrderStatusBadge({ status }: { status: OrderStatus }) {
  return <span className={`stock ${status === 'confirmed' ? 'stock--ok' : 'stock--out'}`}>{status}</span>
}
