import { getToken } from '../auth/tokenStorage'
import type { ValidationErrors } from './types'

export class ApiError extends Error {
  readonly status: number
  readonly errors: ValidationErrors
  /** Raw response body, for endpoint-specific details (e.g. a 409's `available`). */
  readonly body: unknown

  constructor(status: number, message: string, errors: ValidationErrors = {}, body: unknown = null) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
    this.body = body
  }

  /** First message for a field, e.g. from a 422 response. */
  fieldError(field: string): string | undefined {
    return this.errors[field]?.[0]
  }
}

type ErrorBody = { message?: string; errors?: ValidationErrors } | null

let unauthorizedHandler: (() => void) | null = null

/**
 * Register what happens when an authenticated request gets a 401 (token
 * revoked or expired). Returns an unsubscribe function.
 */
export function onUnauthorized(handler: () => void): () => void {
  unauthorizedHandler = handler
  return () => {
    if (unauthorizedHandler === handler) unauthorizedHandler = null
  }
}

interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  headers?: Record<string, string>
}

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = 'GET', body, headers = {} } = options
  const token = getToken()

  let response: Response
  try {
    response = await fetch(`/api${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(body !== undefined && { 'Content-Type': 'application/json' }),
        ...(token && { Authorization: `Bearer ${token}` }),
        ...headers,
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch {
    throw new ApiError(0, 'Could not reach the server. Is the backend running?')
  }

  if (response.status === 204) {
    return undefined as T
  }

  const data: unknown = await response.json().catch(() => null)

  if (!response.ok) {
    if (response.status === 401 && token) {
      unauthorizedHandler?.()
    }

    const error = data as ErrorBody
    throw new ApiError(
      response.status,
      error?.message ?? `Request failed (${response.status}).`,
      error?.errors ?? {},
      data,
    )
  }

  return data as T
}
