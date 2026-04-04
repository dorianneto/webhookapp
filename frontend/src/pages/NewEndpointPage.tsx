import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { apiFetch } from '../lib/apiFetch'

export default function NewEndpointPage() {
  const { sourceId } = useParams<{ sourceId: string }>()
  const navigate = useNavigate()

  const [url, setUrl] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setLoading(true)

    try {
      const res = await apiFetch(`/api/v1/sources/${sourceId}/endpoints`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ url }),
      })

      if (res.ok) {
        navigate(`/sources/${sourceId}`)
        return
      }

      const data = await res.json().catch(() => ({})) as { error?: string }
      setError(data.error ?? 'Failed to create endpoint.')
    } catch {
      setError('Failed to create endpoint.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div style={{ padding: 24, maxWidth: 480 }}>
      <div style={{ marginBottom: 16 }}>
        <Link to={`/sources/${sourceId}`}>← Back</Link>
      </div>

      <h1 style={{ marginTop: 0 }}>Add Endpoint</h1>

      <form onSubmit={handleSubmit}>
        <div style={{ marginBottom: 16 }}>
          <label htmlFor="url" style={{ display: 'block', marginBottom: 4 }}>
            Destination URL
          </label>
          <input
            id="url"
            type="url"
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            placeholder="https://example.com/webhook"
            required
            style={{ width: '100%', padding: '8px', boxSizing: 'border-box' }}
          />
        </div>

        {error && <p style={{ color: 'red' }}>{error}</p>}

        <button type="submit" disabled={loading}>
          {loading ? 'Saving…' : 'Add Endpoint'}
        </button>
      </form>
    </div>
  )
}
