import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { apiFetch } from '../lib/apiFetch'

interface Source {
  id: string
  name: string
  inboundUuid: string
  inboundUrl: string
  createdAt: string
}

export default function DashboardPage() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  const [sources, setSources] = useState<Source[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    apiFetch('/api/v1/sources')
      .then((res) => {
        if (!res.ok) throw new Error('Failed to load sources.')
        return res.json() as Promise<Source[]>
      })
      .then(setSources)
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Failed to load sources.'))
      .finally(() => setLoading(false))
  }, [])

  const handleDelete = async (id: string) => {
    if (!window.confirm('Delete this source?')) return

    const res = await apiFetch(`/api/v1/sources/${id}`, { method: 'DELETE' })
    if (res.ok) {
      setSources((prev) => prev.filter((s) => s.id !== id))
    } else {
      alert('Failed to delete source.')
    }
  }

  const handleLogout = async () => {
    await logout()
  }

  return (
    <div style={{ padding: 24 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <h1 style={{ margin: 0 }}>Sources</h1>
        <div>
          <span style={{ marginRight: 16 }}>{user?.email}</span>
          <button onClick={handleLogout}>Sign out</button>
        </div>
      </div>

      <div style={{ marginBottom: 16 }}>
        <button onClick={() => navigate('/sources/new')}>New Source</button>
      </div>

      {loading && <p>Loading…</p>}
      {error && <p style={{ color: 'red' }}>{error}</p>}

      {!loading && !error && sources.length === 0 && (
        <p style={{ color: '#666' }}>No sources yet. Create one to get started.</p>
      )}

      {sources.length > 0 && (
        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr>
              <th style={thStyle}>Name</th>
              <th style={thStyle}>Inbound URL</th>
              <th style={thStyle}>Created</th>
              <th style={thStyle}></th>
            </tr>
          </thead>
          <tbody>
            {sources.map((source) => (
              <tr key={source.id}>
                <td style={tdStyle}>
                  <Link to={`/sources/${source.id}`}>{source.name}</Link>
                </td>
                <td style={tdStyle}>
                  <code style={{ fontSize: 13 }}>{source.inboundUrl}</code>
                </td>
                <td style={tdStyle}>
                  {new Date(source.createdAt).toLocaleDateString()}
                </td>
                <td style={{ ...tdStyle, textAlign: 'right' }}>
                  <button onClick={() => handleDelete(source.id)}>Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  )
}

const thStyle: React.CSSProperties = {
  textAlign: 'left',
  padding: '8px 12px',
  borderBottom: '2px solid #ddd',
}

const tdStyle: React.CSSProperties = {
  padding: '8px 12px',
  borderBottom: '1px solid #eee',
  verticalAlign: 'middle',
}
