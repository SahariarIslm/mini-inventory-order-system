import { createContext } from 'react'
import type { Product } from '../api/types'

export interface CartLine {
  /** Snapshot from when it was added; stock/price are re-checked by the server. */
  product: Product
  quantity: number
}

export interface CartState {
  /** Replaced (new array) on every change, so identity marks a cart version. */
  lines: CartLine[]
  itemCount: number
  add: (product: Product) => void
  setQuantity: (productId: number, quantity: number) => void
  remove: (productId: number) => void
  clear: () => void
}

export const CartContext = createContext<CartState | null>(null)
