import { useEffect, useState, useCallback } from "react";
import DeleteConfirmModal from "@/components/DeleteConfirmModal";
import { Link, useNavigate, useParams } from "react-router-dom";
import { toast } from "sonner";
import { apiFetch } from "@/lib/apiFetch";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
import { ScrollArea } from "@/components/ui/scroll-area";

interface Source {
  id: string;
  name: string;
  inboundUuid: string;
  inboundUrl: string;
  createdAt: string;
}

interface Endpoint {
  id: string;
  sourceId: string;
  url: string;
  createdAt: string;
}

interface Event {
  id: string;
  method: string;
  status: "pending" | "delivered" | "failed";
  receivedAt: string;
}

type EventStatus = Event["status"];

function statusBadgeClass(status: EventStatus) {
  if (status === "delivered")
    return "bg-green-600 hover:bg-green-700 text-white";
  if (status === "failed") return "";
  return "";
}

function statusBadgeVariant(
  status: EventStatus,
): "default" | "secondary" | "destructive" {
  if (status === "delivered") return "default";
  if (status === "failed") return "destructive";
  return "secondary";
}

export default function SourceDetailPage() {
  const { sourceId } = useParams<{ sourceId: string }>();
  const navigate = useNavigate();

  const [source, setSource] = useState<Source | null>(null);
  const [endpoints, setEndpoints] = useState<Endpoint[]>([]);
  const [events, setEvents] = useState<Event[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [eventsLoading, setEventsLoading] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<{
    id: string;
    url: string;
  } | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  useEffect(() => {
    Promise.all([
      apiFetch("/api/v1/sources").then((res) => {
        if (!res.ok) throw new Error("Failed to load source.");
        return res.json() as Promise<Source[]>;
      }),
      apiFetch(`/api/v1/sources/${sourceId}/endpoints`).then((res) => {
        if (!res.ok) throw new Error("Failed to load endpoints.");
        return res.json() as Promise<Endpoint[]>;
      }),
      apiFetch(`/api/v1/sources/${sourceId}/events`).then((res) => {
        if (!res.ok) throw new Error("Failed to load events.");
        return res.json() as Promise<Event[]>;
      }),
    ])
      .then(([sources, endpointList, eventList]) => {
        setSource(sources.find((s) => s.id === sourceId) ?? null);
        setEndpoints(endpointList);
        setEvents(eventList);
      })
      .catch((err: unknown) =>
        setError(err instanceof Error ? err.message : "Failed to load data."),
      )
      .finally(() => setLoading(false));
  }, [sourceId]);

  const refreshEvents = useCallback(() => {
    setEventsLoading(true);
    apiFetch(`/api/v1/sources/${sourceId}/events`)
      .then((res) => {
        if (!res.ok) throw new Error("Failed to load events.");
        return res.json() as Promise<Event[]>;
      })
      .then(setEvents)
      .catch((err: unknown) =>
        setError(
          err instanceof Error ? err.message : "Failed to refresh events.",
        ),
      )
      .finally(() => setEventsLoading(false));
  }, [sourceId]);

  const handleDeleteEndpointConfirm = async () => {
    if (!deleteTarget) return;
    setDeleteLoading(true);
    const res = await apiFetch(`/api/v1/endpoints/${deleteTarget.id}`, {
      method: "DELETE",
    });
    setDeleteLoading(false);
    if (res.ok) {
      setEndpoints((prev) => prev.filter((e) => e.id !== deleteTarget.id));
      setDeleteTarget(null);
      toast.success("Endpoint deleted.");
    } else {
      toast.error("Failed to delete endpoint.");
    }
  };

  return (
    <>
      <DeleteConfirmModal
        open={deleteTarget !== null}
        onOpenChange={(open) => {
          if (!open) setDeleteTarget(null);
        }}
        resourceLabel="endpoint"
        resourceName={deleteTarget?.url ?? ""}
        onConfirm={handleDeleteEndpointConfirm}
        isLoading={deleteLoading}
      />
      <div className="flex flex-1 flex-col min-h-0 gap-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink asChild>
                <Link to="/sources">Sources</Link>
              </BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>{source?.name ?? sourceId}</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <h1 className="text-2xl font-semibold">{source?.name ?? sourceId}</h1>

        {loading && <p className="text-sm text-muted-foreground">Loading…</p>}

        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {!loading && !error && (
          <>
            <Card className="shrink-0">
              <CardHeader className="flex flex-row items-center justify-between space-y-0">
                <CardTitle className="text-base">Endpoints</CardTitle>
                <Button
                  size="sm"
                  onClick={() => navigate(`/sources/${sourceId}/endpoints/new`)}
                >
                  Add Endpoint
                </Button>
              </CardHeader>
              <CardContent>
                {endpoints.length === 0 ? (
                  <p className="text-sm text-muted-foreground">
                    No endpoints yet. Add one to start receiving webhooks.
                  </p>
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>URL</TableHead>
                        <TableHead>Created</TableHead>
                        <TableHead />
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {endpoints.map((endpoint) => (
                        <TableRow key={endpoint.id}>
                          <TableCell>
                            <code className="text-xs">{endpoint.url}</code>
                          </TableCell>
                          <TableCell className="text-sm text-muted-foreground">
                            {new Date(endpoint.createdAt).toLocaleDateString()}
                          </TableCell>
                          <TableCell className="text-right">
                            <Button
                              variant="destructive"
                              size="sm"
                              onClick={() =>
                                setDeleteTarget({
                                  id: endpoint.id,
                                  url: endpoint.url,
                                })
                              }
                            >
                              Delete
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>

            <Card className="flex flex-col flex-1 min-h-0">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 shrink-0">
                <CardTitle className="text-base">Events</CardTitle>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={refreshEvents}
                  disabled={eventsLoading}
                >
                  {eventsLoading ? "Refreshing…" : "Refresh"}
                </Button>
              </CardHeader>
              <CardContent className="flex-1 min-h-0 overflow-hidden">
                <ScrollArea className="h-full w-full">
                  {events.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                      No events received yet.
                    </p>
                  ) : (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Received</TableHead>
                          <TableHead>Method</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead />
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {events.map((event) => (
                          <TableRow key={event.id}>
                            <TableCell className="text-sm text-muted-foreground">
                              {new Date(event.receivedAt).toLocaleString()}
                            </TableCell>
                            <TableCell>
                              <code className="text-xs">{event.method}</code>
                            </TableCell>
                            <TableCell>
                              <Badge
                                variant={statusBadgeVariant(event.status)}
                                className={statusBadgeClass(event.status)}
                              >
                                {event.status}
                              </Badge>
                            </TableCell>
                            <TableCell className="text-right">
                              <Link
                                to={`/sources/${sourceId}/events/${event.id}`}
                                className="text-sm text-primary underline-offset-4 hover:underline"
                              >
                                View
                              </Link>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  )}
                </ScrollArea>
              </CardContent>
            </Card>
          </>
        )}
      </div>
    </>
  );
}
