import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { authApi } from '../api/auth'
import { onUnauthorized } from '../api/client'
import type { LoginResponse, RegisterInput, User } from '../api/types'
import { AuthContext, type AuthStatus } from './AuthContext'
import { clearToken, getToken, setToken } from './tokenStorage'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  // With a stored token we don't know yet whether it's still valid.
  const [status, setStatus] = useState<AuthStatus>(() => (getToken() ? 'loading' : 'guest'))

  const becomeGuest = useCallback(() => {
    clearToken()
    setUser(null)
    setStatus('guest')
  }, [])

  // Restore the session from a stored token.
  useEffect(() => {
    if (!getToken()) return

    let cancelled = false
    authApi
      .me()
      .then((me) => {
        if (cancelled) return
        setUser(me)
        setStatus('authenticated')
      })
      .catch(() => {
        if (!cancelled) becomeGuest()
      })

    return () => {
      cancelled = true
    }
  }, [becomeGuest])

  // Any 401 on an authenticated request means the token is dead.
  useEffect(() => onUnauthorized(becomeGuest), [becomeGuest])

  // Shared by login and register: both endpoints return the same { user, token }.
  const startSession = useCallback(({ user: loggedIn, token }: LoginResponse) => {
    setToken(token)
    setUser(loggedIn)
    setStatus('authenticated')
  }, [])

  const login = useCallback(
    async (email: string, password: string) => startSession(await authApi.login(email, password)),
    [startSession],
  )

  const register = useCallback(
    async (input: RegisterInput) => startSession(await authApi.register(input)),
    [startSession],
  )

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } catch {
      // Revoking server-side failed (e.g. token already dead); still log out locally.
    } finally {
      becomeGuest()
    }
  }, [becomeGuest])

  const value = useMemo(
    () => ({ status, user, login, register, logout }),
    [status, user, login, register, logout],
  )

  return <AuthContext value={value}>{children}</AuthContext>
}
