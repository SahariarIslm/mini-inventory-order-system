import { apiRequest } from './client'
import type { LoginResponse, User } from './types'

export const authApi = {
  login: (email: string, password: string) =>
    apiRequest<LoginResponse>('/login', { method: 'POST', body: { email, password } }),

  logout: () => apiRequest<{ message: string }>('/logout', { method: 'POST' }),

  me: () => apiRequest<User>('/me'),
}
