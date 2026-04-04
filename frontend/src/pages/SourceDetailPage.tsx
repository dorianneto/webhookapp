import { useEffect, useState, useCallback } from 'react'
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

interface Event {
  id: string
  method: string
  status: 'pending' | 'delivered' | 'failed'
  receivedAt: string
}

const STATUS_COLORS: Record<string, string> = {
  pending: '#888',
  delivered: '#2a7a2a',
  failed: '#c0392b',
}

export default function SourceDetailPage() {
  const { sourceId } = useParams<{ sourceId: string }>()
  const navigate = useNavigate()

  const [source, setSource] = useState<Source | null>(null)
  const [endpoints, setEndpoints] = useState<Endpoint[]>([])
  const [events, setEvents] = useState<Event[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [eventsLoading, setEventsLoading] = useState(false)

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
      fetch(`/api/v1/sources/${sourceId}/events`).then((res) => {
        if (!res.ok) throw new Error('Failed to load events.')
        return res.json() as Promise<Event[]>
      }),
    ])
      .then(([sources, endpointList, eventList]) => {
        const found = sources.find((s) => s.id === sourceId) ?? null
        setSource(found)
        setEndpoints(endpointList)
        setEvents(eventList)
      })
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Failed to load data.'))
      .finally(() => setLoading(false))
  }, [sourceId])

  const refreshEvents = useCallback(() => {
    setEventsLoading(true)
    fetch(`/api/v1/sources/${sourceId}/events`)
      .then((res) => {
        if (!res.ok) throw new Error('Failed to load events.')
        return res.json() as Promise<Event[]>
      })
      .then(setEvents)
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Failed to refresh events.'))
      .finally(() => setEventsLoading(false))
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
          <div style={{ marginBottom: 32 }}>
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
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
              <h2 style={{ margin: 0 }}>Events</h2>
              <button onClick={refreshEvents} disabled={eventsLoading}>
                {eventsLoading ? 'Refreshing…' : 'Refresh'}
              </button>
            </div>

            {events.length === 0 && (
              <p style={{ color: '#666' }}>No events received yet.</p>
            )}

            {events.length > 0 && (
              <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                <thead>
                  <tr>
                    <th style={thStyle}>Received</th>
                    <th style={thStyle}>Method</th>
                    <th style={thStyle}>Status</th>
                    <th style={thStyle}></th>
                  </tr>
                </thead>
                <tbody>
                  {events.map((event) => (
                    <tr key={event.id}>
                      <td style={tdStyle}>
                        {new Date(event.receivedAt).toLocaleString()}
                      </td>
                      <td style={tdStyle}>
                        <code>{event.method}</code>
                      </td>
                      <td style={tdStyle}>
                        <span style={{
                          color: STATUS_COLORS[event.status] ?? '#888',
                          fontWeight: 600,
                          textTransform: 'capitalize',
                        }}>
                          {event.status}
                        </span>
                      </td>
                      <td style={{ ...tdStyle, textAlign: 'right' }}>
                        <Link to={`/sources/${sourceId}/events/${event.id}`}>View</Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
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
