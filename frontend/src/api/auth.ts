import { apiRequest } from './client'
import type { LoginResponse, RegisterInput, User } from './types'

export const authApi = {
  login: (email: string, password: string) =>
    apiRequest<LoginResponse>('/login', { method: 'POST', body: { email, password } }),

  register: (input: RegisterInput) =>
    apiRequest<LoginResponse>('/register', { method: 'POST', body: input }),

  logout: () => apiRequest<{ message: string }>('/logout', { method: 'POST' }),

  me: () => apiRequest<User>('/me'),
}
