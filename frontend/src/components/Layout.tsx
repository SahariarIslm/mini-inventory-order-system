import { NavLink, Outlet } from 'react-router'
import { useAuth } from '../auth/useAuth'
import { CartProvider } from '../cart/CartProvider'
import { useCart } from '../cart/useCart'

export function Layout() {
  return (
    // Inside the authenticated layout: the cart is discarded on logout.
    <CartProvider>
      <div className="app">
        <Header />
        <main className="app-main">
          <Outlet />
        </main>
      </div>
    </CartProvider>
  )
}

function Header() {
  const { user, logout } = useAuth()
  const { itemCount } = useCart()

  return (
    <header className="app-header">
      <div className="app-header__inner">
        <span className="brand">Mini Inventory</span>

        <nav className="nav">
          <NavLink to="/" end>
            Home
          </NavLink>
          <NavLink to="/products">Products</NavLink>
          <NavLink to="/orders">{user?.role === 'admin' ? 'All orders' : 'My orders'}</NavLink>
          <NavLink to="/cart">
            Your order{itemCount > 0 && <span className="count">{itemCount}</span>}
          </NavLink>
        </nav>

        {user && (
          <div className="user-menu">
            <span className="user-menu__name">{user.name}</span>
            <span className={`badge badge--${user.role}`}>{user.role}</span>
            <button type="button" className="button button--ghost" onClick={() => void logout()}>
              Log out
            </button>
          </div>
        )}
      </div>
    </header>
  )
}
