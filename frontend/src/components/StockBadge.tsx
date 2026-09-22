const LOW_STOCK_THRESHOLD = 5

export function StockBadge({ quantity }: { quantity: number }) {
  if (quantity === 0) {
    return <span className="stock stock--out">Out of stock</span>
  }

  if (quantity <= LOW_STOCK_THRESHOLD) {
    return <span className="stock stock--low">{quantity} left</span>
  }

  return <span className="stock stock--ok">{quantity} in stock</span>
}
