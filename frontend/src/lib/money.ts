// Prices arrive as exact decimal strings ("149.99"). Client-side estimates
// are done in integer cents so no float rounding creeps in; the server's
// totals remain the source of truth.

export function toCents(amount: string): number {
  const [whole, fraction = ''] = amount.split('.')
  return Number(whole) * 100 + Number(fraction.padEnd(2, '0').slice(0, 2))
}

export function formatCents(cents: number): string {
  return (cents / 100).toFixed(2)
}
