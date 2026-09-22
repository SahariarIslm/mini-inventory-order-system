import { NavLink, Outlet } from 'react-router'
import { useAuth } from '../auth/useAuth'

export function Layout() {
  const { user, logout } = useAuth()

  return (
    <div className="app">
      <header className="app-header">
        <div className="app-header__inner">
          <span className="brand">Mini Inventory</span>

          <nav className="nav">
            <NavLink to="/" end>
              Home
            </NavLink>
            <NavLink to="/products">Products</NavLink>
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

      <main className="app-main">
        <Outlet />
      </main>
    </div>
  )
}
