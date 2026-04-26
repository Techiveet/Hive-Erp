import type {
  ChatAttachmentMetadata,
  ChatConversation,
  ChatMessage,
  ChatMessageMetadata,
  ChatReplyMetadata,
} from '@/store/chat-store';
import { getEncryptedChatFallback, isEncryptedChatBody } from '@/lib/chat-e2ee';

type PreviewableMessage = {
  body?: string | null;
  type?: 'text' | 'image' | 'file' | 'audio' | null;
  metadata?: ChatMessageMetadata | null;
};

export const getStoredChatUser = <T = { id: number; name: string }>() => {
  if (typeof window === 'undefined') {
    return null as T | null;
  }

  const userStr = localStorage.getItem('hive_user') || localStorage.getItem('user');
  if (!userStr) {
    return null as T | null;
  }

  try {
    return JSON.parse(userStr) as T;
  } catch {
    return null as T | null;
  }
};

export const getChatMessageFallback = (type?: string | null) => {
  switch (type) {
    case 'image':
      return 'Sent an image';
    case 'file':
      return 'Sent a file';
    case 'audio':
      return 'Sent an audio message';
    default:
      return 'New message';
  }
};

export const getChatAttachmentLabel = (attachment?: ChatAttachmentMetadata | null) => {
  return attachment?.title || attachment?.name || attachment?.download_name || null;
};

export const getChatMessagePreview = (message?: PreviewableMessage | null) => {
  if (!message) {
    return 'New message';
  }

  const trimmedBody = message.body?.trim();
  if (trimmedBody && isEncryptedChatBody(trimmedBody)) {
    return getEncryptedChatFallback();
  }

  if (trimmedBody) {
    return trimmedBody;
  }

  const attachmentLabel = getChatAttachmentLabel(message.metadata?.attachment);
  if (attachmentLabel) {
    return attachmentLabel;
  }

  return getChatMessageFallback(message.type);
};

export const getChatReplyPreview = (reply?: ChatReplyMetadata | null) => {
  if (!reply) {
    return 'Reply';
  }

  return getChatMessagePreview({
    body: reply.body,
    type: reply.type,
  });
};

export const getChatConversationTitle = (
  conversation: Pick<ChatConversation, 'type' | 'title' | 'participants'>,
  currentUserId?: number | null
) => {
  if (conversation.type === 'group') {
    return conversation.title || 'Group';
  }

  return conversation.participants.find((participant) => String(participant.id) !== String(currentUserId))?.name || 'Private Chat';
};

export const createReplyMetadata = (message: ChatMessage): ChatReplyMetadata => ({
  id: message.id,
  conversation_id: message.conversation_id,
  sender_id: message.sender_id,
  body: message.body || getChatAttachmentLabel(message.metadata?.attachment),
  type: message.type,
  sender: message.sender
    ? {
        id: message.sender.id,
        name: message.sender.name,
      }
    : null,
});
