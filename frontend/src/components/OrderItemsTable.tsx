import type { Order } from '../api/types'

/** An order's lines as recorded at purchase time (snapshot prices). */
export function OrderItemsTable({ order }: { order: Order }) {
  return (
    <table className="table">
      <thead>
        <tr>
          <th>Product</th>
          <th className="num">Unit price</th>
          <th className="num">Qty</th>
          <th className="num">Line total</th>
        </tr>
      </thead>
      <tbody>
        {order.items.map((item) => (
          <tr key={item.id}>
            <td>
              <div className="cell-title">{item.product_name}</div>
              <div className="cell-sub">
                {item.product_sku}
                {item.product_id === null && ' · product since deleted'}
              </div>
            </td>
            <td className="num">{item.unit_price}</td>
            <td className="num">{item.quantity}</td>
            <td className="num">{item.line_total}</td>
          </tr>
        ))}
      </tbody>
      <tfoot>
        <tr>
          <td colSpan={3} className="num">
            <strong>Total</strong>
          </td>
          <td className="num">
            <strong>{order.total}</strong>
          </td>
        </tr>
      </tfoot>
    </table>
  )
}
