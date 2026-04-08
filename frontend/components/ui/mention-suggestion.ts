import { ReactRenderer } from '@tiptap/react'
import tippy from 'tippy.js'
import { MentionList } from './mention-list'
import api from '@/lib/api'

export default {
  items: async ({ query }: { query: string }) => {
    try {
      const { data } = await api.get(`/directory/users?search=${encodeURIComponent(query)}`);
      return Array.isArray(data.data) ? data.data : (Array.isArray(data) ? data : []);
    } catch (err) {
      console.error('Failed to fetch users for suggestion:', err);
      return [];
    }
  },

  render: () => {
    let component: ReactRenderer
    let popup: any

    return {
      onStart: (props: any) => {
        component = new ReactRenderer(MentionList, {
          props: { ...props, loading: true },
          editor: props.editor,
        })

        if (!props.clientRect) {
          return
        }

        popup = tippy('body', {
          getReferenceClientRect: props.clientRect,
          appendTo: () => document.body,
          content: component.element,
          showOnCreate: true,
          interactive: true,
          trigger: 'manual',
          placement: 'bottom-start',
        })
      },

      onUpdate(props: any) {
        component.updateProps({ ...props, loading: false })

        if (!props.clientRect) {
          return
        }

        popup[0].setProps({
          getReferenceClientRect: props.clientRect,
        })
      },

      onKeyDown(props: any) {
        if (props.event.key === 'Escape') {
          popup[0].hide()

          return true
        }

        return (component.ref as any)?.onKeyDown(props)
      },

      onExit() {
        popup[0].destroy()
        component.destroy()
      },
    }
  },
}
