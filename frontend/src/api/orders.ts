import { apiRequest } from './client'
import type { Order, OrderLineInput, Paginated } from './types'

type Single<T> = { data: T }

export const ordersApi = {
  /** Staff get their own orders; admins get everyone's (scoped server-side). */
  list: (page: number) => apiRequest<Paginated<Order>>(`/orders?page=${page}`),

  get: (id: number) => apiRequest<Single<Order>>(`/orders/${id}`).then((r) => r.data),

  /**
   * Resending the same key (e.g. after a timeout) returns the original order
   * instead of creating a second one.
   */
  place: (items: OrderLineInput[], idempotencyKey: string) =>
    apiRequest<Single<Order>>('/orders', {
      method: 'POST',
      body: { items },
      headers: { 'Idempotency-Key': idempotencyKey },
    }).then((r) => r.data),

  /** Returns stock; cancelling an already-cancelled order is a no-op. */
  cancel: (id: number) =>
    apiRequest<Single<Order>>(`/orders/${id}/cancel`, { method: 'POST' }).then((r) => r.data),
}
