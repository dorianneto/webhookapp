import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { apiFetch } from '@/lib/apiFetch'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import DeleteConfirmModal from '@/components/DeleteConfirmModal'

interface Source {
  id: string
  name: string
  inboundUuid: string
  inboundUrl: string
  createdAt: string
}

export default function SourcesPage() {
  const navigate = useNavigate()

  const [sources, setSources] = useState<Source[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<{ id: string; name: string } | null>(null)
  const [deleteLoading, setDeleteLoading] = useState(false)

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

  const handleDeleteConfirm = async () => {
    if (!deleteTarget) return
    setDeleteLoading(true)
    const res = await apiFetch(`/api/v1/sources/${deleteTarget.id}`, { method: 'DELETE' })
    setDeleteLoading(false)
    if (res.ok) {
      setSources((prev) => prev.filter((s) => s.id !== deleteTarget.id))
      setDeleteTarget(null)
      toast.success('Source deleted.')
    } else {
      toast.error('Failed to delete source.')
    }
  }

  return (
    <>
    <DeleteConfirmModal
      open={deleteTarget !== null}
      onOpenChange={(open) => { if (!open) setDeleteTarget(null) }}
      resourceLabel="source"
      resourceName={deleteTarget?.name ?? ''}
      onConfirm={handleDeleteConfirm}
      isLoading={deleteLoading}
    />
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Sources</h1>
        <Button onClick={() => navigate('/sources/new')}>New Source</Button>
      </div>

      {loading && <p className="text-sm text-muted-foreground">Loading…</p>}

      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {!loading && !error && sources.length === 0 && (
        <p className="text-sm text-muted-foreground">No sources yet. Create one to get started.</p>
      )}

      {sources.length > 0 && (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Inbound URL</TableHead>
              <TableHead>Created</TableHead>
              <TableHead />
            </TableRow>
          </TableHeader>
          <TableBody>
            {sources.map((source) => (
              <TableRow key={source.id}>
                <TableCell>
                  <Link to={`/sources/${source.id}`} className="font-medium hover:underline">
                    {source.name}
                  </Link>
                </TableCell>
                <TableCell>
                  <code className="text-xs">{source.inboundUrl}</code>
                </TableCell>
                <TableCell className="text-sm text-muted-foreground">
                  {new Date(source.createdAt).toLocaleDateString()}
                </TableCell>
                <TableCell className="text-right">
                  <Button variant="destructive" size="sm" onClick={() => setDeleteTarget({ id: source.id, name: source.name })}>
                    Delete
                  </Button>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
    </div>
    </>
  )
}
