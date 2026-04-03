import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'

interface Source {
  id: string
  name: string
  inboundUuid: string
  inboundUrl: string
  createdAt: string
}

interface Endpoint {
  id: string
  sourceId: string
  url: string
  createdAt: string
}

export default function SourceDetailPage() {
  const { sourceId } = useParams<{ sourceId: string }>()
  const navigate = useNavigate()

  const [source, setSource] = useState<Source | null>(null)
  const [endpoints, setEndpoints] = useState<Endpoint[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    Promise.all([
      fetch('/api/v1/sources').then((res) => {
        if (!res.ok) throw new Error('Failed to load source.')
        return res.json() as Promise<Source[]>
      }),
      fetch(`/api/v1/sources/${sourceId}/endpoints`).then((res) => {
        if (!res.ok) throw new Error('Failed to load endpoints.')
        return res.json() as Promise<Endpoint[]>
      }),
    ])
      .then(([sources, endpointList]) => {
        const found = sources.find((s) => s.id === sourceId) ?? null
        setSource(found)
        setEndpoints(endpointList)
      })
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Failed to load data.'))
      .finally(() => setLoading(false))
  }, [sourceId])

  const handleDeleteEndpoint = async (id: string) => {
    if (!window.confirm('Delete this endpoint?')) return

    const res = await fetch(`/api/v1/endpoints/${id}`, { method: 'DELETE' })
    if (res.ok) {
      setEndpoints((prev) => prev.filter((e) => e.id !== id))
    } else {
      alert('Failed to delete endpoint.')
    }
  }

  return (
    <div style={{ padding: 24 }}>
      <div style={{ marginBottom: 16 }}>
        <Link to="/">← Back to Sources</Link>
      </div>

      <h1 style={{ marginTop: 0 }}>{source?.name ?? sourceId}</h1>

      {loading && <p>Loading…</p>}
      {error && <p style={{ color: 'red' }}>{error}</p>}

      {!loading && !error && (
        <>
          <div style={{ marginBottom: 24 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
              <h2 style={{ margin: 0 }}>Endpoints</h2>
              <button onClick={() => navigate(`/sources/${sourceId}/endpoints/new`)}>Add Endpoint</button>
            </div>

            {endpoints.length === 0 && (
              <p style={{ color: '#666' }}>No endpoints yet. Add one to start receiving webhooks.</p>
            )}

            {endpoints.length > 0 && (
              <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                <thead>
                  <tr>
                    <th style={thStyle}>URL</th>
                    <th style={thStyle}>Created</th>
                    <th style={thStyle}></th>
                  </tr>
                </thead>
                <tbody>
                  {endpoints.map((endpoint) => (
                    <tr key={endpoint.id}>
                      <td style={tdStyle}>
                        <code style={{ fontSize: 13 }}>{endpoint.url}</code>
                      </td>
                      <td style={tdStyle}>
                        {new Date(endpoint.createdAt).toLocaleDateString()}
                      </td>
                      <td style={{ ...tdStyle, textAlign: 'right' }}>
                        <button onClick={() => handleDeleteEndpoint(endpoint.id)}>Delete</button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          <div>
            <h2>Events</h2>
            <p style={{ color: '#666' }}>Events coming in Phase 7.</p>
          </div>
        </>
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
