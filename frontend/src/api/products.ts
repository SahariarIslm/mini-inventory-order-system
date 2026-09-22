import { apiRequest } from './client'
import type { Paginated, Product, ProductInput } from './types'

type Single<T> = { data: T }

export const productsApi = {
  list: (page: number) => apiRequest<Paginated<Product>>(`/products?page=${page}`),

  create: (input: ProductInput) =>
    apiRequest<Single<Product>>('/products', { method: 'POST', body: input }).then((r) => r.data),

  // Stock is deliberately not updatable here; see adjustStock.
  update: (id: number, input: Omit<ProductInput, 'stock_quantity'>) =>
    apiRequest<Single<Product>>(`/products/${id}`, { method: 'PATCH', body: input }).then((r) => r.data),

  remove: (id: number) => apiRequest<void>(`/products/${id}`, { method: 'DELETE' }),

  /** Relative change: positive restocks, negative writes off. */
  adjustStock: (id: number, quantity: string) =>
    apiRequest<Single<Product>>(`/products/${id}/stock-adjustments`, {
      method: 'POST',
      body: { quantity },
    }).then((r) => r.data),
}
