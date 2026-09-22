import { useCallback, useEffect, useState } from 'react'
import { ApiError } from '../api/client'

export type ResourceState<R> =
  | { status: 'loading'; result: R | null; error: null; reload: () => void }
  | { status: 'ready'; result: R; error: null; reload: () => void }
  | { status: 'error'; result: R | null; error: ApiError; reload: () => void }

interface Settled<R> {
  /** Which request this outcome belongs to. */
  key: string
  result: R | null
  error: ApiError | null
}

/**
 * Loads `load(arg)` and exposes a reload(). The last good result is kept
 * while reloading so the UI doesn't flash empty. `load` must be stable
 * (e.g. a module-level API function); `arg` must be a primitive.
 */
export function useResource<A extends string | number, R>(load: (arg: A) => Promise<R>, arg: A): ResourceState<R> {
  const [reloadCount, setReloadCount] = useState(0)
  const [settled, setSettled] = useState<Settled<R> | null>(null)
  const requestKey = `${arg}:${reloadCount}`

  useEffect(() => {
    let cancelled = false

    load(arg)
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
  }, [load, arg, requestKey])

  const reload = useCallback(() => setReloadCount((n) => n + 1), [])
  const result = settled?.result ?? null

  // Loading is derived: the latest outcome belongs to an older request.
  if (settled?.key !== requestKey) return { status: 'loading', result, error: null, reload }
  if (settled.error) return { status: 'error', result, error: settled.error, reload }
  return { status: 'ready', result: settled.result!, error: null, reload }
}
