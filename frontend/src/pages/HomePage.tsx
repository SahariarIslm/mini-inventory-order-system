import { useAuth } from '../auth/useAuth'

export function HomePage() {
  const { user } = useAuth()

  return (
    <section className="card">
      <h1>Welcome, {user?.name}</h1>
      <p className="muted">
        {user?.role === 'admin'
          ? 'As an admin you can manage the product catalogue, adjust stock, and see every order.'
          : 'As staff you can browse products, place orders, and see the orders you placed.'}
      </p>
    </section>
  )
}
