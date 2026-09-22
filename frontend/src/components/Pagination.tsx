import type { Paginated } from '../api/types'

interface PaginationProps {
  meta: Paginated<unknown>['meta']
  onPageChange: (page: number) => void
}

export function Pagination({ meta, onPageChange }: PaginationProps) {
  if (meta.last_page <= 1) return null

  return (
    <nav className="pagination" aria-label="Pagination">
      <button
        type="button"
        className="button button--ghost"
        disabled={meta.current_page <= 1}
        onClick={() => onPageChange(meta.current_page - 1)}
      >
        Previous
      </button>
      <span className="muted">
        Page {meta.current_page} of {meta.last_page} · {meta.total} total
      </span>
      <button
        type="button"
        className="button button--ghost"
        disabled={meta.current_page >= meta.last_page}
        onClick={() => onPageChange(meta.current_page + 1)}
      >
        Next
      </button>
    </nav>
  )
}
