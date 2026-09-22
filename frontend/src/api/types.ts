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

export type OrderStatus = 'confirmed' | 'cancelled'

export interface OrderItem {
  id: number
  /** Null if the product has since been deleted; the snapshot fields remain. */
  product_id: number | null
  product_name: string
  product_sku: string
  unit_price: string
  quantity: number
  line_total: string
}

export interface Order {
  id: number
  user_id: number
  user?: { id: number; name: string }
  idempotency_key: string
  status: OrderStatus
  total: string
  items: OrderItem[]
  created_at: string
}

export interface OrderLineInput {
  product_id: number
  quantity: number
}

/** Body of a 409 from order placement or stock adjustment. */
export interface InsufficientStockBody {
  message: string
  product_id: number
  available: number
  requested: number
}

/** Form values are sent as typed; Laravel validates and coerces them. */
export interface ProductInput {
  name: string
  sku: string
  description: string
  price: string
  stock_quantity?: string
}
