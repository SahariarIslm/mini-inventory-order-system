import { Link } from 'react-router'
import { ordersApi } from '../../api/orders'
import { useAuth } from '../../auth/useAuth'
import { OrderStatusBadge } from '../../components/OrderStatusBadge'
import { EmptyPage, Pagination } from '../../components/Pagination'
import { usePageParam } from '../../hooks/usePageParam'
import { usePaginated } from '../../hooks/usePaginated'
import { formatDateTime } from '../../lib/dates'

export function OrdersPage() {
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'

  const [page, setPage] = usePageParam()
  const orders = usePaginated(ordersApi.list, page)
  const rows = orders.result?.data ?? []

  return (
    <section>
      <div className="page-header">
        <div>
          <h1>{isAdmin ? 'All orders' : 'My orders'}</h1>
          <p className="muted">{isAdmin ? 'Every order placed by any user.' : 'Orders you have placed.'}</p>
        </div>
      </div>

      {orders.status === 'error' && (
        <p className="alert alert--error" role="alert">
          {orders.error.message}{' '}
          <button type="button" className="link-button" onClick={orders.reload}>
            Retry
          </button>
        </p>
      )}

      <div className="card card--flush">
        {orders.result === null ? (
          orders.status === 'loading' && <p className="page-status">Loading orders…</p>
        ) : rows.length === 0 && page > 1 ? (
          <EmptyPage page={page} onFirstPage={() => setPage(1)} />
        ) : rows.length === 0 ? (
          <p className="page-status">
            No orders yet. <Link to="/products">Browse products</Link> to place one.
          </p>
        ) : (
          <table className="table" aria-busy={orders.status === 'loading'}>
            <thead>
              <tr>
                <th>Order</th>
                <th>Placed</th>
                {isAdmin && <th>Placed by</th>}
                <th className="num">Items</th>
                <th className="num">Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((order) => (
                <tr key={order.id}>
                  <td>
                    <Link to={`/orders/${order.id}`} className="cell-title">
                      #{order.id}
                    </Link>
                  </td>
                  <td>{formatDateTime(order.created_at)}</td>
                  {isAdmin && <td>{order.user?.name ?? `User #${order.user_id}`}</td>}
                  <td className="num">{order.items.reduce((sum, item) => sum + item.quantity, 0)}</td>
                  <td className="num">{order.total}</td>
                  <td>
                    <OrderStatusBadge status={order.status} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {orders.result && <Pagination meta={orders.result.meta} onPageChange={setPage} />}
    </section>
  )
}
