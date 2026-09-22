import { useCallback, useMemo, useState, type ReactNode } from 'react'
import type { Product } from '../api/types'
import { CartContext, type CartLine } from './CartContext'

/**
 * In-memory cart. Rendered inside the authenticated layout, so it is
 * discarded on logout and never leaks between users.
 */
export function CartProvider({ children }: { children: ReactNode }) {
  const [lines, setLines] = useState<CartLine[]>([])

  const add = useCallback((product: Product) => {
    setLines((current) =>
      current.some((line) => line.product.id === product.id)
        ? current.map((line) => (line.product.id === product.id ? { ...line, quantity: line.quantity + 1 } : line))
        : [...current, { product, quantity: 1 }],
    )
  }, [])

  const setQuantity = useCallback((productId: number, quantity: number) => {
    if (!Number.isInteger(quantity) || quantity < 1) return
    setLines((current) => current.map((line) => (line.product.id === productId ? { ...line, quantity } : line)))
  }, [])

  const remove = useCallback((productId: number) => {
    setLines((current) => current.filter((line) => line.product.id !== productId))
  }, [])

  const clear = useCallback(() => setLines([]), [])

  const value = useMemo(
    () => ({
      lines,
      itemCount: lines.reduce((sum, line) => sum + line.quantity, 0),
      add,
      setQuantity,
      remove,
      clear,
    }),
    [lines, add, setQuantity, remove, clear],
  )

  return <CartContext value={value}>{children}</CartContext>
}
