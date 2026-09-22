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
