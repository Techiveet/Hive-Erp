import { create } from 'zustand';

export type MailFolder = 'inbox' | 'sent' | 'drafts' | 'trash' | 'starred';

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

interface MailState {
    activeFolder: MailFolder;
    mails: MailParticipant[];
    selectedMailId: number | null;
    isComposeOpen: boolean;
    composeData: any | null;
    setActiveFolder: (folder: MailFolder) => void;
    setMails: (mails: MailParticipant[]) => void;
    appendMail: (mail: MailParticipant) => void;
    updateMail: (id: number, data: Partial<MailParticipant>) => void;
    deleteMail: (id: number) => void;
    selectMail: (id: number | null) => void;
    setComposeOpen: (isOpen: boolean, prefillData?: any) => void;
}

export const useMailStore = create<MailState>((set) => ({
    activeFolder: 'inbox',
    mails: [],
    selectedMailId: null,
    isComposeOpen: false,
    composeData: null,
    setActiveFolder: (folder) => set({ activeFolder: folder, selectedMailId: null }),
    setMails: (mails) => set({ mails }),
    appendMail: (mail) => set((state) => ({ mails: [mail, ...state.mails] })),
    updateMail: (id, data) => set((state) => ({
        mails: state.mails.map((m) => m.mail_message_id === id ? { ...m, ...data } : m)
    })),
    deleteMail: (id) => set((state) => ({
        mails: state.mails.filter((m) => m.mail_message_id !== id)
    })),
    selectMail: (id) => set({ selectedMailId: id }),
    setComposeOpen: (isOpen, prefillData = null) => set({ isComposeOpen: isOpen, composeData: prefillData }),
}));
