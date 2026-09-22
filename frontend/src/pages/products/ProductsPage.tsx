import { useState } from 'react'
import { ApiError } from '../../api/client'
import { productsApi } from '../../api/products'
import type { Product } from '../../api/types'
import { useAuth } from '../../auth/useAuth'
import { useCart } from '../../cart/useCart'
import { Pagination } from '../../components/Pagination'
import { StockBadge } from '../../components/StockBadge'
import { EmptyPage } from '../../components/Pagination'
import { usePageParam } from '../../hooks/usePageParam'
import { usePaginated } from '../../hooks/usePaginated'
import { AdjustStockModal } from './AdjustStockModal'
import { ProductFormModal } from './ProductFormModal'

type ModalState = { type: 'create' } | { type: 'edit'; product: Product } | { type: 'stock'; product: Product } | null

export function ProductsPage() {
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'

  const [page, setPage] = usePageParam()
  const products = usePaginated(productsApi.list, page)
  const [modal, setModal] = useState<ModalState>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  const rows = products.result?.data ?? []

  function handleSaved() {
    setModal(null)
    setActionError(null)
    products.reload()
  }

  async function handleDelete(product: Product) {
    if (!window.confirm(`Delete “${product.name}”? Past orders keep their own copy of its details.`)) return

    setActionError(null)
    try {
      await productsApi.remove(product.id)
      // Deleting the last row of a page: step back instead of showing an empty page.
      if (rows.length === 1 && page > 1) setPage(page - 1)
      else products.reload()
    } catch (err) {
      setActionError(err instanceof ApiError ? err.message : 'Could not delete the product.')
    }
  }

  return (
    <section>
      <div className="page-header">
        <div>
          <h1>Products</h1>
          <p className="muted">{isAdmin ? 'Manage the catalogue and stock levels.' : 'Browse the catalogue and add items to an order.'}</p>
        </div>
        {isAdmin && (
          <button type="button" className="button button--primary" onClick={() => setModal({ type: 'create' })}>
            New product
          </button>
        )}
      </div>

      {actionError && (
        <p className="alert alert--error" role="alert">
          {actionError}
        </p>
      )}

      {products.status === 'error' && (
        <p className="alert alert--error" role="alert">
          {products.error.message}{' '}
          <button type="button" className="link-button" onClick={products.reload}>
            Retry
          </button>
        </p>
      )}

      <div className="card card--flush">
        {products.result === null ? (
          products.status === 'loading' && <p className="page-status">Loading products…</p>
        ) : rows.length === 0 ? (
          page > 1 ? (
            <EmptyPage page={page} onFirstPage={() => setPage(1)} />
          ) : (
            <p className="page-status">No products yet.</p>
          )
        ) : (
          <table className="table" aria-busy={products.status === 'loading'}>
            <thead>
              <tr>
                <th>Product</th>
                <th className="num">Price</th>
                <th>Stock</th>
                <th className="actions-col">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((product) => (
                <tr key={product.id}>
                  <td>
                    <div className="cell-title">{product.name}</div>
                    <div className="cell-sub">{product.sku}</div>
                  </td>
                  <td className="num">{product.price}</td>
                  <td>
                    <StockBadge quantity={product.stock_quantity} />
                  </td>
                  <td className="actions-col">
                    <div className="row-actions">
                      <AddToOrderButton product={product} />
                      {isAdmin && (
                        <>
                          <button type="button" className="link-button" onClick={() => setModal({ type: 'stock', product })}>
                            Adjust stock
                          </button>
                          <button type="button" className="link-button" onClick={() => setModal({ type: 'edit', product })}>
                            Edit
                          </button>
                          <button type="button" className="link-button link-button--danger" onClick={() => void handleDelete(product)}>
                            Delete
                          </button>
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {products.result && <Pagination meta={products.result.meta} onPageChange={setPage} />}

      {modal?.type === 'create' && <ProductFormModal onClose={() => setModal(null)} onSaved={handleSaved} />}
      {modal?.type === 'edit' && <ProductFormModal product={modal.product} onClose={() => setModal(null)} onSaved={handleSaved} />}
      {modal?.type === 'stock' && <AdjustStockModal product={modal.product} onClose={() => setModal(null)} onSaved={handleSaved} />}
    </section>
  )
}

function AddToOrderButton({ product }: { product: Product }) {
  const { lines, add } = useCart()
  const inCart = lines.find((line) => line.product.id === product.id)?.quantity ?? 0

  return (
    <button type="button" className="button button--small" disabled={!product.in_stock} onClick={() => add(product)}>
      {inCart > 0 ? `Add another (${inCart})` : 'Add to order'}
    </button>
  )
}
