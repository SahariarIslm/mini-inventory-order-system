import { useState, type FormEvent } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router'
import { ApiError } from '../api/client'
import { useAuth } from '../auth/useAuth'

const DEMO_ACCOUNTS = [
  { label: 'Admin', email: 'admin@example.com' },
  { label: 'Staff', email: 'staff@example.com' },
]
const DEMO_PASSWORD = 'password'

export function LoginPage() {
  const { status, login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const redirectTo = (location.state as { from?: string } | null)?.from ?? '/'

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<ApiError | null>(null)
  const [submitting, setSubmitting] = useState(false)

  if (status === 'authenticated') {
    return <Navigate to={redirectTo} replace />
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSubmitting(true)

    try {
      await login(email, password)
      navigate(redirectTo, { replace: true })
    } catch (err) {
      setError(err instanceof ApiError ? err : new ApiError(0, 'Something went wrong. Please try again.'))
      setSubmitting(false)
    }
  }

  // A 422 carries per-field messages; anything else is shown as one banner.
  const emailError = error?.fieldError('email')
  const passwordError = error?.fieldError('password')
  const bannerError = error && !emailError && !passwordError ? error.message : null

  return (
    <div className="login">
      <div className="card login__card">
        <h1 className="login__title">Mini Inventory</h1>
        <p className="muted">Sign in to manage products and orders.</p>

        <form className="form" onSubmit={handleSubmit} noValidate>
          {bannerError && (
            <p className="alert alert--error" role="alert">
              {bannerError}
            </p>
          )}

          <label className="field">
            <span className="field__label">Email</span>
            <input
              type="email"
              autoComplete="username"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              aria-invalid={Boolean(emailError)}
              required
            />
            {emailError && <span className="field__error">{emailError}</span>}
          </label>

          <label className="field">
            <span className="field__label">Password</span>
            <input
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              aria-invalid={Boolean(passwordError)}
              required
            />
            {passwordError && <span className="field__error">{passwordError}</span>}
          </label>

          <button type="submit" className="button button--primary" disabled={submitting}>
            {submitting ? 'Signing in…' : 'Sign in'}
          </button>
        </form>

        <div className="demo-accounts">
          <p className="muted">Demo accounts (password: {DEMO_PASSWORD})</p>
          <div className="demo-accounts__buttons">
            {DEMO_ACCOUNTS.map((account) => (
              <button
                key={account.email}
                type="button"
                className="button button--ghost"
                onClick={() => {
                  setEmail(account.email)
                  setPassword(DEMO_PASSWORD)
                  setError(null)
                }}
              >
                Fill {account.label}
              </button>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}
