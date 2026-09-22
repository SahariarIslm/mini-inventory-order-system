import { useCallback } from 'react'
import { useSearchParams } from 'react-router'

/**
 * Current page number, kept in the URL (?page=N) so Back/Forward, reload
 * and shared links all land on the same page. Page 1 is the bare URL.
 */
export function usePageParam(): [number, (page: number) => void] {
  const [searchParams, setSearchParams] = useSearchParams()
  const parsed = Number.parseInt(searchParams.get('page') ?? '', 10)
  const page = Number.isInteger(parsed) && parsed > 1 ? parsed : 1

  const setPage = useCallback(
    (next: number) => {
      setSearchParams((current) => {
        const params = new URLSearchParams(current)
        if (next <= 1) params.delete('page')
        else params.set('page', String(next))
        return params
      })
    },
    [setSearchParams],
  )

  return [page, setPage]
}
