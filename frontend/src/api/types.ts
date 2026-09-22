export type Role = 'admin' | 'staff'

export interface User {
  id: number
  name: string
  email: string
  role: Role
}

export interface LoginResponse {
  user: User
  token: string
}

/** Laravel's 422 body: field name -> list of messages. */
export type ValidationErrors = Record<string, string[]>

/** Shape of a Laravel paginated API Resource collection. */
export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
  }
}

export interface Product {
  id: number
  name: string
  sku: string
  description: string | null
  /** Decimal string, e.g. "149.99" — never a float. */
  price: string
  stock_quantity: number
  in_stock: boolean
  created_at: string
  updated_at: string
}

/** Form values are sent as typed; Laravel validates and coerces them. */
export interface ProductInput {
  name: string
  sku: string
  description: string
  price: string
  stock_quantity?: string
}
