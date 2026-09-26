import { createContext } from 'react'
import type { RegisterInput, User } from '../api/types'

export type AuthStatus = 'loading' | 'authenticated' | 'guest'

export interface AuthState {
  status: AuthStatus
  user: User | null
  login: (email: string, password: string) => Promise<void>
  register: (input: RegisterInput) => Promise<void>
  logout: () => Promise<void>
}

export const AuthContext = createContext<AuthState | null>(null)
