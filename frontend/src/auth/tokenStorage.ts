// Bearer token persistence. Storage can be unavailable (private mode, blocked
// site data), so every access is guarded and falls back to "no token".

const KEY = 'mios.token'

export function getToken(): string | null {
  try {
    return localStorage.getItem(KEY)
  } catch {
    return null
  }
}

export function setToken(token: string): void {
  try {
    localStorage.setItem(KEY, token)
  } catch {
    // Not persisted; the session still works until the tab reloads.
  }
}

export function clearToken(): void {
  try {
    localStorage.removeItem(KEY)
  } catch {
    // Nothing to clear.
  }
}
