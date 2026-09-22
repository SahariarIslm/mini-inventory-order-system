import { apiRequest } from './client'
import type { Order, OrderLineInput } from './types'

type Single<T> = { data: T }

export const ordersApi = {
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
}
