import { create } from 'zustand';
import { getBackendApiRoot } from '@/lib/runtime-context';

interface TranslationState {
  locale: string;
  dictionary: Record<string, string>;
  isReady: boolean;
  initLocale: () => Promise<void>;
  t: (key: string, fallback?: string) => string;
}

const getApiUrl = (endpoint: string) => `${getBackendApiRoot()}${endpoint}`;

export const useTranslation = create<TranslationState>((set, get) => ({
  locale: 'en', 
  dictionary: {},
  isReady: false,

  initLocale: async () => {
    const savedLocale = typeof window !== 'undefined' ? localStorage.getItem('hive_locale') || 'en' : 'en';
    
    try {
      const res = await fetch(getApiUrl(`/translations/${savedLocale}`), {
        headers: { 'Accept': 'application/json' }
      });
      
      if (res.ok) {
        const data = await res.json();
        
        // 🚀 THE FIX: Handle direct objects or nested data, no more 'data.messages'
        const cleanDictionary = data.data || data || {};
        
        set({ locale: savedLocale, dictionary: cleanDictionary, isReady: true });
      } else {
        set({ locale: savedLocale, isReady: true });
      }
    } catch (err) {
      console.error("Failed to load dictionary:", err);
      set({ locale: savedLocale, isReady: true });
    }
  },

  t: (key: string, fallback?: string) => {
    const { dictionary } = get();
    // 🚀 It will now successfully find the translated key!
    return dictionary[key] || fallback || key;
  }
}));
