import { useCallback, useEffect, useState } from 'react'
import { ApiError } from '../api/client'
import type { Paginated } from '../api/types'

export type PaginatedState<T> =
  | { status: 'loading'; result: Paginated<T> | null; error: null; reload: () => void }
  | { status: 'ready'; result: Paginated<T>; error: null; reload: () => void }
  | { status: 'error'; result: Paginated<T> | null; error: ApiError; reload: () => void }

interface Settled<T> {
  /** Which request this outcome belongs to. */
  key: string
  result: Paginated<T> | null
  error: ApiError | null
}

/**
 * Loads one page of a paginated endpoint and exposes a reload(). The last
 * good result is kept while reloading so the table doesn't flash empty.
 * `load` must be stable (e.g. a module-level API function).
 */
export function usePaginated<T>(load: (page: number) => Promise<Paginated<T>>, page: number): PaginatedState<T> {
  const [reloadCount, setReloadCount] = useState(0)
  const [settled, setSettled] = useState<Settled<T> | null>(null)
  const requestKey = `${page}:${reloadCount}`

  useEffect(() => {
    let cancelled = false

    load(page)
      .then((result) => {
        if (!cancelled) setSettled({ key: requestKey, result, error: null })
      })
      .catch((err: unknown) => {
        if (cancelled) return
        const error = err instanceof ApiError ? err : new ApiError(0, 'Failed to load.')
        setSettled((prev) => ({ key: requestKey, result: prev?.result ?? null, error }))
      })

    return () => {
      cancelled = true
    }
  }, [load, page, requestKey])

  const reload = useCallback(() => setReloadCount((n) => n + 1), [])
  const result = settled?.result ?? null

  // Loading is derived: the latest outcome belongs to an older request.
  if (settled?.key !== requestKey) return { status: 'loading', result, error: null, reload }
  if (settled.error) return { status: 'error', result, error: settled.error, reload }
  return { status: 'ready', result: settled.result!, error: null, reload }
}
