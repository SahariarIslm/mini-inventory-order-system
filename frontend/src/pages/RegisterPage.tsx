import { useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate } from 'react-router'
import { ApiError } from '../api/client'
import { useAuth } from '../auth/useAuth'
import { Field } from '../components/Field'

const FIELDS = ['name', 'email', 'password'] as const

export function RegisterPage() {
  const { status, register } = useAuth()
  const navigate = useNavigate()

  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [error, setError] = useState<ApiError | null>(null)
  const [submitting, setSubmitting] = useState(false)

  if (status === 'authenticated') {
    return <Navigate to="/products" replace />
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSubmitting(true)

    try {
      await register({ name, email, password, password_confirmation: passwordConfirmation })
      navigate('/products', { replace: true })
    } catch (err) {
      setError(err instanceof ApiError ? err : new ApiError(0, 'Something went wrong. Please try again.'))
      setSubmitting(false)
    }
  }

  // A 422 carries per-field messages (a confirmation mismatch lands on
  // `password`); anything else is shown as one banner.
  const nameError = error?.fieldError('name')
  const emailError = error?.fieldError('email')
  const passwordError = error?.fieldError('password')
  const bannerError = error && !FIELDS.some((field) => error.fieldError(field)) ? error.message : null

  return (
    <div className="login">
      <div className="card login__card">
        <h1 className="login__title">Mini Inventory</h1>
        <p className="muted">Create an account to manage products and orders.</p>

        <form className="form" onSubmit={handleSubmit} noValidate>
          {bannerError && (
            <p className="alert alert--error" role="alert">
              {bannerError}
            </p>
          )}

          <Field label="Name" error={nameError}>
            <input
              type="text"
              autoComplete="name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              aria-invalid={Boolean(nameError)}
              required
            />
          </Field>

          <Field label="Email" error={emailError}>
            <input
              type="email"
              autoComplete="username"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              aria-invalid={Boolean(emailError)}
              required
            />
          </Field>

          <Field label="Password" error={passwordError}>
            <input
              type="password"
              autoComplete="new-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              aria-invalid={Boolean(passwordError)}
              required
            />
          </Field>

          <Field label="Confirm password">
            <input
              type="password"
              autoComplete="new-password"
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              aria-invalid={Boolean(passwordError)}
              required
            />
          </Field>

          <button type="submit" className="button button--primary" disabled={submitting}>
            {submitting ? 'Creating account…' : 'Create account'}
          </button>
        </form>

        <p className="login__switch muted">
          Already have an account? <Link to="/login">Log in</Link>
        </p>
      </div>
    </div>
  )
}
