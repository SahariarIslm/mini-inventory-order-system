import { use } from 'react'
import { CartContext, type CartState } from './CartContext'

export function useCart(): CartState {
  const cart = use(CartContext)
  if (!cart) {
    throw new Error('useCart must be used inside <CartProvider>.')
  }
  return cart
}
