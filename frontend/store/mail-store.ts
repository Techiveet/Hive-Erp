import { create } from 'zustand';

export type MailFolder = 'all' | 'inbox' | 'sent' | 'drafts' | 'trash' | 'starred' | 'archive' | 'spam' | 'important';

export interface MailParticipant {
    id: number;
    mail_message_id: number;
    user_id: number;
    type: string;
    folder: string;
    is_read: boolean;
    is_starred: boolean;
    created_at: string;
    message: {
        id: number;
        subject: string;
        body: string;
        sender_id: number;
        sender: {
            id: number;
            name: string;
            email: string;
            avatar_url: string;
        };
        participants: {
            user: {
                id: number;
                name: string;
                email: string;
            }
        }[];
        created_at: string;
    };
}

export interface MailCounts {
    inbox: number;
    inbox_unread: number;
    starred: number;
    sent: number;
    drafts: number;
    archive: number;
    trash: number;
    spam: number;
    important: number;
    storage_used: number;
    storage_limit: number;
    tenant_storage_used?: number;
    tenant_storage_limit?: number;
}

interface MailState {
    activeFolder: MailFolder;
    mails: MailParticipant[];
    selectedMailId: number | null;
    isComposeOpen: boolean;
    composeData: any | null;
    counts: MailCounts;
    checkedMailIds: number[];
    setActiveFolder: (folder: MailFolder) => void;
    setMails: (mails: MailParticipant[]) => void;
    appendMail: (mail: MailParticipant) => void;
    updateMail: (id: number, data: Partial<MailParticipant>) => void;
    deleteMail: (id: number) => void;
    selectMail: (id: number | null) => void;
    setComposeOpen: (isOpen: boolean, prefillData?: any) => void;
    setCounts: (counts: MailCounts) => void;
    toggleCheckMail: (id: number) => void;
    toggleCheckAll: (ids: number[]) => void;
    clearChecked: () => void;
    bulkUpdateMails: (ids: number[], data: Partial<MailParticipant>) => void;
    bulkDeleteMails: (ids: number[]) => void;
    searchQuery: string;
    setSearchQuery: (query: string) => void;
    isFullscreen: boolean;
    setFullscreen: (val: boolean) => void;
    adjustCounts: (updates: Partial<MailCounts>) => void;
    onlineUsers: any[];
    setOnlineUsers: (users: any[]) => void;
}

export const useMailStore = create<MailState>((set) => ({
    activeFolder: 'inbox',
    mails: [],
    selectedMailId: null,
    isComposeOpen: false,
    composeData: null,
    counts: {
        inbox: 0,
        inbox_unread: 0,
        starred: 0,
        sent: 0,
        drafts: 0,
        archive: 0,
        trash: 0,
        spam: 0,
        important: 0,
        storage_used: 0,
        storage_limit: 1048576, // fallback 1 GB
        tenant_storage_used: 0,
        tenant_storage_limit: 0,
    },
    checkedMailIds: [],
    searchQuery: '',
    isFullscreen: false,
    setActiveFolder: (folder) => set({ activeFolder: folder, selectedMailId: null, checkedMailIds: [], searchQuery: '', isFullscreen: false }),
    setMails: (mails) => set({ mails, checkedMailIds: [] }),
    appendMail: (mail) => set((state) => ({ mails: [mail, ...state.mails] })),
    updateMail: (id, data) => set((state) => ({
        mails: state.mails.map((m) => m.mail_message_id === id ? { ...m, ...data } : m)
    })),
    deleteMail: (id) => set((state) => ({
        mails: state.mails.filter((m) => m.mail_message_id !== id)
    })),
    selectMail: (id) => set({ selectedMailId: id }),
    setComposeOpen: (isOpen, prefillData = null) => set({ isComposeOpen: isOpen, composeData: prefillData }),
    setCounts: (counts) => set({ counts }),
    toggleCheckMail: (id) => set((state) => ({
        checkedMailIds: state.checkedMailIds.includes(id) 
            ? state.checkedMailIds.filter(i => i !== id) 
            : [...state.checkedMailIds, id]
    })),
    toggleCheckAll: (ids) => set((state) => ({
        checkedMailIds: state.checkedMailIds.length === ids.length ? [] : ids
    })),
    clearChecked: () => set({ checkedMailIds: [] }),
    bulkUpdateMails: (ids, data) => set((state) => ({
        mails: state.mails.map((m) => ids.includes(m.mail_message_id) ? { ...m, ...data } : m)
    })),
    bulkDeleteMails: (ids) => set((state) => ({
        mails: state.mails.filter((m) => !ids.includes(m.mail_message_id)),
        checkedMailIds: []
    })),
    setSearchQuery: (query) => set({ searchQuery: query }),
    setFullscreen: (val) => set({ isFullscreen: val }),
    adjustCounts: (updates) => set((state) => {
        const newCounts = { ...state.counts };
        for (const [key, value] of Object.entries(updates)) {
             newCounts[key as keyof MailCounts] = Math.max(0, (newCounts[key as keyof MailCounts] ?? 0) + (value as number));
        }
        return { counts: newCounts };
    }),
    onlineUsers: [],
    setOnlineUsers: (users) => set({ onlineUsers: users }),
}));
