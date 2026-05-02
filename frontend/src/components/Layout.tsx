import type { ReactNode } from "react";
import { Toaster } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import { AppSidebar } from "@/components/AppSidebar";
import { SiteHeader } from "./SiteHeader";

export default function Layout({ children }: { children: ReactNode }) {
  return (
    <TooltipProvider>
      <SidebarProvider
        className="h-dvh overflow-hidden"
        style={
          {
            "--sidebar-width": "calc(var(--spacing) * 72)",
            "--header-height": "calc(var(--spacing) * 12)",
          } as React.CSSProperties
        }
      >
        <AppSidebar variant="inset" />
        <SidebarInset>
          <SiteHeader />
          <div className="flex flex-1 flex-col overflow-hidden">
            <div className="@container/main flex flex-1 flex-col gap-2 overflow-hidden">
              <div className="flex flex-1 flex-col gap-4 py-4 md:gap-6 md:py-6 overflow-hidden">
                <div className="flex flex-1 flex-col min-h-0 overflow-y-auto px-4 lg:px-6">{children}</div>
              </div>
            </div>
            <Toaster />
          </div>
        </SidebarInset>
      </SidebarProvider>
    </TooltipProvider>
  );
}
