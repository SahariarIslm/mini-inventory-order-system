import { use } from 'react'
import { AuthContext, type AuthState } from './AuthContext'

export function useAuth(): AuthState {
  const auth = use(AuthContext)
  if (!auth) {
    throw new Error('useAuth must be used inside <AuthProvider>.')
  }
  return auth
}
