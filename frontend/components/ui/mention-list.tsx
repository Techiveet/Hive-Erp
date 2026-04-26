import React, {
  forwardRef,
  useEffect,
  useImperativeHandle,
  useState,
} from 'react'
import { cn } from '@/lib/utils'
import { Loader2 } from 'lucide-react'

export interface MentionNode {
  id: string
  name: string
  email: string
}

export const MentionList = forwardRef((props: any, ref) => {
  const [selectedIndex, setSelectedIndex] = useState(0)

  const selectItem = (index: number) => {
    const item = props.items[index]
    if (item) {
      // Passes the selected user ID and label back to TipTap node attributes
      props.command({ id: item.id, label: item.name })
    }
  }

  const upHandler = () => {
    setSelectedIndex((selectedIndex + props.items.length - 1) % props.items.length)
  }

  const downHandler = () => {
    setSelectedIndex((selectedIndex + 1) % props.items.length)
  }

  const enterHandler = () => {
    selectItem(selectedIndex)
  }

  useEffect(() => {
    setSelectedIndex(0)
  }, [props.items])

  useImperativeHandle(ref, () => ({
    onKeyDown: ({ event }: { event: KeyboardEvent }) => {
      if (props.loading) return false;
      
      if (event.key === 'ArrowUp') {
        upHandler()
        return true
      }
      if (event.key === 'ArrowDown') {
        downHandler()
        return true
      }
      if (event.key === 'Enter') {
        enterHandler()
        return true
      }
      return false
    },
  }))

  return (
    <div className="bg-popover text-popover-foreground rounded-md shadow-md border overflow-hidden min-w-[200px] max-h-[300px] flex flex-col z-50">
      {props.loading ? (
        <div className="p-3 flex items-center justify-center text-muted-foreground gap-2 text-sm">
           <Loader2 className="h-4 w-4 animate-spin" /> Searching...
        </div>
      ) : props.items.length > 0 ? (
        <div className="overflow-y-auto">
          {props.items.map((item: MentionNode, index: number) => (
            <button
              className={cn(
                "w-full text-left px-3 py-2 text-sm flex flex-col outline-none",
                index === selectedIndex ? "bg-muted font-medium" : "bg-transparent hover:bg-muted/50"
              )}
              key={index}
              onClick={() => selectItem(index)}
              onMouseEnter={() => setSelectedIndex(index)}
            >
              <span className="font-semibold">{item.name}</span>
              <span className="text-xs text-muted-foreground truncate">{item.email}</span>
            </button>
          ))}
        </div>
      ) : (
        <div className="p-3 text-sm text-center text-muted-foreground">
          No users found
        </div>
      )}
    </div>
  )
})

MentionList.displayName = 'MentionList'
