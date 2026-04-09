import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '@/contexts/AuthContext'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Toaster } from '@/components/ui/sonner'

export default function Layout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth()

  return (
    <div className="min-h-svh flex flex-col">
      <header className="h-14 px-6 flex items-center justify-between shrink-0">
        <Link to="/" className="font-semibold text-sm tracking-tight">
          WebhookApp
        </Link>
        <div className="flex items-center gap-3">
          <span className="text-sm text-muted-foreground">{user?.email}</span>
          <Button variant="ghost" size="sm" onClick={() => { void logout() }}>
            Sign out
          </Button>
        </div>
      </header>
      <Separator />
      <main className="flex-1 w-full max-w-5xl mx-auto px-6 py-8">
        {children}
      </main>
      <Toaster />
    </div>
  )
}
