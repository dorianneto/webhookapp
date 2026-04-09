import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { apiFetch } from '@/lib/apiFetch'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'

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

type DeliveryStatus = EndpointDelivery['status']

function statusBadgeVariant(status: DeliveryStatus): 'default' | 'secondary' | 'destructive' {
  if (status === 'delivered') return 'default'
  if (status === 'failed') return 'destructive'
  return 'secondary'
}

function statusBadgeClass(status: DeliveryStatus) {
  return status === 'delivered' ? 'bg-green-600 hover:bg-green-700 text-white' : ''
}

function httpCodeBadgeVariant(code: number | null): 'default' | 'secondary' | 'destructive' {
  if (code === null) return 'secondary'
  if (code >= 200 && code < 300) return 'default'
  return 'destructive'
}

function httpCodeBadgeClass(code: number | null) {
  return code !== null && code >= 200 && code < 300 ? 'bg-green-600 hover:bg-green-700 text-white' : ''
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
    <div className="space-y-6">
      <Breadcrumb>
        <BreadcrumbList>
          <BreadcrumbItem>
            <BreadcrumbLink asChild>
              <Link to="/">Sources</Link>
            </BreadcrumbLink>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <BreadcrumbLink asChild>
              <Link to={`/sources/${sourceId}`}>Source</Link>
            </BreadcrumbLink>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <BreadcrumbPage>Event</BreadcrumbPage>
          </BreadcrumbItem>
        </BreadcrumbList>
      </Breadcrumb>

      {loading && <p className="text-sm text-muted-foreground">Loading…</p>}

      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {event && (
        <>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-semibold">
              <code>{event.method}</code>
            </h1>
            <Badge
              variant={statusBadgeVariant(event.status)}
              className={statusBadgeClass(event.status)}
            >
              {event.status}
            </Badge>
            <span className="text-sm text-muted-foreground">
              {new Date(event.receivedAt).toLocaleString()}
            </span>
          </div>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Headers</CardTitle>
            </CardHeader>
            <CardContent>
              <pre className="bg-muted rounded-md p-4 text-xs overflow-x-auto">
                {JSON.stringify(event.headers, null, 2)}
              </pre>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Body</CardTitle>
            </CardHeader>
            <CardContent>
              <pre className="bg-muted rounded-md p-4 text-xs overflow-x-auto">
                {event.body || <em className="text-muted-foreground">(empty)</em>}
              </pre>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Deliveries</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {event.deliveries.length === 0 && (
                <p className="text-sm text-muted-foreground">No delivery records yet.</p>
              )}

              {event.deliveries.map((delivery) => (
                <div key={delivery.endpointId} className="border rounded-md overflow-hidden">
                  <div className="px-4 py-2.5 bg-muted flex items-center justify-between gap-4">
                    <code className="text-xs truncate">{delivery.endpointUrl}</code>
                    <Badge
                      variant={statusBadgeVariant(delivery.status)}
                      className={statusBadgeClass(delivery.status)}
                    >
                      {delivery.status}
                    </Badge>
                  </div>

                  {delivery.attempts.length === 0 ? (
                    <p className="px-4 py-3 text-sm text-muted-foreground">No attempts yet.</p>
                  ) : (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>#</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead>Duration</TableHead>
                          <TableHead>Response</TableHead>
                          <TableHead>Time</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {delivery.attempts.map((attempt) => (
                          <TableRow key={attempt.attemptNumber}>
                            <TableCell className="text-muted-foreground">{attempt.attemptNumber}</TableCell>
                            <TableCell>
                              <Badge
                                variant={httpCodeBadgeVariant(attempt.statusCode)}
                                className={httpCodeBadgeClass(attempt.statusCode)}
                              >
                                {attempt.statusCode ?? '—'}
                              </Badge>
                            </TableCell>
                            <TableCell className="text-sm">{attempt.durationMs} ms</TableCell>
                            <TableCell>
                              <code className="text-xs">{attempt.responseBody || '—'}</code>
                            </TableCell>
                            <TableCell className="text-sm text-muted-foreground">
                              {new Date(attempt.attemptedAt).toLocaleString()}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  )}
                </div>
              ))}
            </CardContent>
          </Card>
        </>
      )}
    </div>
  )
}
