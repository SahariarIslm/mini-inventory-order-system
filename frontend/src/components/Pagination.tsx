import type { Paginated } from '../api/types'

interface PaginationProps {
  meta: Paginated<unknown>['meta']
  onPageChange: (page: number) => void
}

/** Shown for a ?page=N beyond the last page (e.g. an old link). */
export function EmptyPage({ page, onFirstPage }: { page: number; onFirstPage: () => void }) {
  return (
    <p className="page-status">
      Nothing on page {page}.{' '}
      <button type="button" className="link-button" onClick={onFirstPage}>
        Go to the first page
      </button>
    </p>
  )
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
