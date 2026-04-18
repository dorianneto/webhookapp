import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  resourceLabel: string
  resourceName: string
  onConfirm: () => void | Promise<void>
  isLoading?: boolean
}

export default function DeleteConfirmModal({
  open,
  onOpenChange,
  resourceLabel,
  resourceName,
  onConfirm,
  isLoading = false,
}: Props) {
  const [inputValue, setInputValue] = useState('')

  useEffect(() => {
    if (!open) setInputValue('')
  }, [open])

  const confirmed = inputValue === resourceName

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent showCloseButton={false}>
        <DialogHeader>
          <DialogTitle>Delete {resourceLabel}</DialogTitle>
          <DialogDescription>
            This action is irreversible. Type <strong className="text-foreground">{resourceName}</strong> to confirm.
          </DialogDescription>
        </DialogHeader>

        <Input
          value={inputValue}
          onChange={(e) => setInputValue(e.target.value)}
          placeholder={resourceName}
          disabled={isLoading}
        />

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isLoading}>
            Cancel
          </Button>
          <Button variant="destructive" onClick={() => void onConfirm()} disabled={!confirmed || isLoading}>
            {isLoading ? 'Deleting…' : 'Delete'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
