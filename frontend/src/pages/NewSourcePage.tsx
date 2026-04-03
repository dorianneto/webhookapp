import { useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'

interface CreateSourceResponse {
  id: string
  name: string
  inboundUuid: string
  inboundUrl: string
  createdAt: string
  error?: string
}

export default function NewSourcePage() {
  const navigate = useNavigate()

  const [name, setName] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setError(null)
    setLoading(true)

    try {
      const res = await fetch('/api/v1/sources', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name }),
      })

      const data = (await res.json()) as CreateSourceResponse

      if (!res.ok) {
        throw new Error(data.error ?? 'Failed to create source.')
      }

      navigate(`/sources/${data.id}`)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create source.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div style={{ maxWidth: 480, margin: '80px auto', padding: '0 16px' }}>
      <h1>New Source</h1>
      <form onSubmit={handleSubmit}>
        <div style={{ marginBottom: 12 }}>
          <label htmlFor="name">Name</label>
          <br />
          <input
            id="name"
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
            autoComplete="off"
            style={{ width: '100%', padding: '8px', marginTop: 4 }}
          />
        </div>
        {error && <p style={{ color: 'red' }}>{error}</p>}
        <button type="submit" disabled={loading} style={{ width: '100%', padding: '10px' }}>
          {loading ? 'Creating…' : 'Create Source'}
        </button>
      </form>
      <p style={{ marginTop: 16 }}>
        <Link to="/">Back to Sources</Link>
      </p>
    </div>
  )
}
