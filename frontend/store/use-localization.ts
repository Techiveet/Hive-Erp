import { create } from 'zustand';

interface Language {
  id: number;
  name: string;
  code: string;
  is_default?: boolean;
}

interface LocalizationState {
  languages: Language[];
  baseTranslations: Record<string, string>; 
  targetTranslations: Record<string, string>;
  activeTargetCode: string | null;
  isLoading: boolean;
  error: string | null;
  
  fetchLanguages: () => Promise<void>;
  fetchBaseTranslations: (sourceCode?: string) => Promise<void>;
  loadTargetLanguage: (code: string) => Promise<void>;
  addLanguage: (name: string, code: string) => Promise<void>;
  deleteLanguage: (code: string) => Promise<void>;
  saveTranslation: (key: string, value: string) => Promise<void>;
  addSystemKey: (key: string, value: string) => Promise<void>;
  deleteSystemKey: (key: string) => Promise<void>;
  setDefaultLanguage: (code: string) => Promise<void>;
  publish: () => Promise<void>;
}

const getApiUrl = (endpoint: string) => `http://${window.location.hostname}:8085/api/v1${endpoint}`;
const getHeaders = () => ({
  'Accept': 'application/json',
  'Content-Type': 'application/json',
  'Authorization': `Bearer ${typeof window !== 'undefined' ? localStorage.getItem('hive_token') : ''}`
});

export const useLocalization = create<LocalizationState>((set, get) => ({
  languages: [],
  baseTranslations: {},
  targetTranslations: {},
  activeTargetCode: null,
  isLoading: false,
  error: null,

  fetchLanguages: async () => {
    set({ isLoading: true, error: null });
    try {
      const res = await fetch(getApiUrl('/localization/languages'), { headers: getHeaders() });
      if (!res.ok) throw new Error("Failed to fetch languages");
      const data = await res.json();
      set({ languages: data });
      
      const source = Array.isArray(data) 
        ? (data.find((l: Language) => l.is_default) || data[0])
        : (data.data?.find((l: Language) => l.is_default) || data.data?.[0]);
        
      if (source) await get().fetchBaseTranslations(source.code);
    } catch (err: any) { 
      set({ error: err.message }); 
    } finally { 
      set({ isLoading: false }); 
    }
  },

  fetchBaseTranslations: async (sourceCode = 'en') => {
    try {
      // Use the internal endpoint for the editor matrix
      const res = await fetch(getApiUrl(`/localization/languages/${sourceCode}/translations`), { headers: getHeaders() });
      if (!res.ok) throw new Error("Failed to fetch base dictionary");
      const data = await res.json();
      // 🚀 THE FIX: Removed .messages
      set({ baseTranslations: data.data || data || {} });
    } catch (err: any) { 
      console.error(err); 
    }
  },

  loadTargetLanguage: async (code) => {
    set({ isLoading: true, activeTargetCode: code, error: null });
    try {
      // Use the internal endpoint for the editor matrix
      const res = await fetch(getApiUrl(`/localization/languages/${code}/translations`), { headers: getHeaders() });
      if (!res.ok) throw new Error("Failed to load target dictionary");
      const data = await res.json();
      // 🚀 THE FIX: Removed .messages
      set({ targetTranslations: data.data || data || {} });
    } catch (err: any) {
      set({ error: err.message });
    } finally { 
      set({ isLoading: false }); 
    }
  },

  addLanguage: async (name, code) => {
    const res = await fetch(getApiUrl('/localization/languages'), {
      method: 'POST', headers: getHeaders(), body: JSON.stringify({ name, code })
    });
    if (!res.ok) {
      const errorData = await res.json();
      throw new Error(errorData.message || "Failed to add language");
    }
    await get().fetchLanguages();
  },

  deleteLanguage: async (code: string) => {
    try {
      const res = await fetch(getApiUrl(`/localization/languages/${code}`), {
        method: 'DELETE',
        headers: getHeaders()
      });

      if (!res.ok) {
        let errorMessage = `Server returned ${res.status}`;
        try {
          const errorData = await res.json();
          errorMessage = errorData.message || errorMessage;
        } catch (parseError) {
          errorMessage = `Backend Crash (Status ${res.status}). Check Laravel Logs.`;
        }
        throw new Error(errorMessage);
      }
      
      await get().fetchLanguages();
    } catch (err: any) {
      throw new Error(err.message || "Network connection failed");
    }
  },

  saveTranslation: async (key, value) => {
    const code = get().activeTargetCode;
    if (!code) return;
    
    const res = await fetch(getApiUrl('/localization/translations/update'), {
      method: 'POST', headers: getHeaders(), body: JSON.stringify({ code, key, value })
    });
    
    if (!res.ok) throw new Error("Failed to save translation");
    set((state) => ({ targetTranslations: { ...state.targetTranslations, [key]: value } }));
  },

  addSystemKey: async (key, value) => {
    const res = await fetch(getApiUrl('/localization/translations/source'), {
      method: 'POST', headers: getHeaders(), body: JSON.stringify({ key, value })
    });
    if (!res.ok) throw new Error("Failed to add system key");
    
    set((state) => ({ baseTranslations: { ...state.baseTranslations, [key]: value } }));
  },

  deleteSystemKey: async (key) => {
    // 🚀 THE FIX: Now hits the correct endpoint to completely destroy a source key globally
    const res = await fetch(getApiUrl('/localization/translations/source/delete'), {
      method: 'POST', headers: getHeaders(), body: JSON.stringify({ key })
    });
    
    if (!res.ok) throw new Error("Failed to delete system key");

    set((state) => {
      const newBase = { ...state.baseTranslations };
      const newTarget = { ...state.targetTranslations };
      delete newBase[key];
      delete newTarget[key]; 
      return { baseTranslations: newBase, targetTranslations: newTarget };
    });
  },

  setDefaultLanguage: async (code) => {
    const res = await fetch(getApiUrl('/localization/languages/default'), {
      method: 'POST', headers: getHeaders(), body: JSON.stringify({ code })
    });
    if (!res.ok) throw new Error("Failed to set default language");
    await get().fetchLanguages();
  },

  publish: async () => {
    const res = await fetch(getApiUrl('/localization/publish'), { method: 'POST', headers: getHeaders() });
    if (!res.ok) throw new Error("Failed to compile matrix files");
  }
}));