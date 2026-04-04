import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { apiFetch } from '../lib/apiFetch'

interface DeliveryAttempt {
  attemptNumber: number
  statusCode: number | null
  responseBody: string
  durationMs: number
  attemptedAt: string
}

interface EndpointDelivery {
  endpointId: string
  endpointUrl: string
  status: 'pending' | 'delivered' | 'failed'
  attempts: DeliveryAttempt[]
}

interface EventDetail {
  id: string
  method: string
  headers: Record<string, string[]>
  body: string
  status: 'pending' | 'delivered' | 'failed'
  receivedAt: string
  deliveries: EndpointDelivery[]
}

const STATUS_COLORS: Record<string, string> = {
  pending: '#888',
  delivered: '#2a7a2a',
  failed: '#c0392b',
}

export default function EventDetailPage() {
  const { sourceId, eventId } = useParams<{ sourceId: string; eventId: string }>()

  const [event, setEvent] = useState<EventDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    apiFetch(`/api/v1/events/${eventId}`)
      .then((res) => {
        if (!res.ok) throw new Error('Event not found.')
        return res.json() as Promise<EventDetail>
      })
      .then(setEvent)
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Failed to load event.'))
      .finally(() => setLoading(false))
  }, [eventId])

  return (
    <div style={{ padding: 24 }}>
      <div style={{ marginBottom: 16 }}>
        <Link to={`/sources/${sourceId}`}>← Back to Source</Link>
      </div>

      {loading && <p>Loading…</p>}
      {error && <p style={{ color: 'red' }}>{error}</p>}

      {event && (
        <>
          <div style={{ display: 'flex', alignItems: 'center', gap: 16, marginBottom: 24 }}>
            <h1 style={{ margin: 0 }}><code>{event.method}</code></h1>
            <span style={{
              color: STATUS_COLORS[event.status] ?? '#888',
              fontWeight: 600,
              fontSize: 14,
              textTransform: 'capitalize',
            }}>
              {event.status}
            </span>
            <span style={{ color: '#888', fontSize: 13 }}>
              {new Date(event.receivedAt).toLocaleString()}
            </span>
          </div>

          <div style={{ marginBottom: 24 }}>
            <h2>Headers</h2>
            <pre style={preStyle}>{JSON.stringify(event.headers, null, 2)}</pre>
          </div>

          <div style={{ marginBottom: 32 }}>
            <h2>Body</h2>
            <pre style={preStyle}>{event.body || <em style={{ color: '#888' }}>(empty)</em>}</pre>
          </div>

          <div>
            <h2>Deliveries</h2>

            {event.deliveries.length === 0 && (
              <p style={{ color: '#666' }}>No delivery records yet.</p>
            )}

            {event.deliveries.map((delivery) => (
              <div key={delivery.endpointId} style={{ marginBottom: 24, border: '1px solid #ddd', borderRadius: 4, overflow: 'hidden' }}>
                <div style={{ padding: '10px 16px', background: '#f5f5f5', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <code style={{ fontSize: 13 }}>{delivery.endpointUrl}</code>
                  <span style={{
                    color: STATUS_COLORS[delivery.status] ?? '#888',
                    fontWeight: 600,
                    fontSize: 13,
                    textTransform: 'capitalize',
                  }}>
                    {delivery.status}
                  </span>
                </div>

                {delivery.attempts.length === 0 && (
                  <p style={{ padding: '8px 16px', color: '#888', margin: 0 }}>No attempts yet.</p>
                )}

                {delivery.attempts.length > 0 && (
                  <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                      <tr>
                        <th style={thStyle}>#</th>
                        <th style={thStyle}>Status</th>
                        <th style={thStyle}>Duration</th>
                        <th style={thStyle}>Response</th>
                        <th style={thStyle}>Time</th>
                      </tr>
                    </thead>
                    <tbody>
                      {delivery.attempts.map((attempt) => (
                        <tr key={attempt.attemptNumber}>
                          <td style={tdStyle}>{attempt.attemptNumber}</td>
                          <td style={tdStyle}>
                            {attempt.statusCode !== null ? (
                              <span style={{ color: attempt.statusCode >= 200 && attempt.statusCode < 300 ? '#2a7a2a' : '#c0392b' }}>
                                {attempt.statusCode}
                              </span>
                            ) : (
                              <span style={{ color: '#888' }}>—</span>
                            )}
                          </td>
                          <td style={tdStyle}>{attempt.durationMs} ms</td>
                          <td style={tdStyle}>
                            <code style={{ fontSize: 12 }}>{attempt.responseBody || '—'}</code>
                          </td>
                          <td style={tdStyle}>{new Date(attempt.attemptedAt).toLocaleString()}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  )
}

const preStyle: React.CSSProperties = {
  background: '#f8f8f8',
  border: '1px solid #ddd',
  borderRadius: 4,
  padding: 12,
  overflowX: 'auto',
  fontSize: 13,
  margin: 0,
}

const thStyle: React.CSSProperties = {
  textAlign: 'left',
  padding: '8px 12px',
  borderBottom: '2px solid #ddd',
  background: '#fafafa',
}

const tdStyle: React.CSSProperties = {
  padding: '8px 12px',
  borderBottom: '1px solid #eee',
  verticalAlign: 'top',
}
