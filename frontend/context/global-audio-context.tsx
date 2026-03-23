//components/providers/global-audio-provider.tsx
"use client";

import React, { createContext, useContext, useState, useCallback } from 'react';

export type Track = {
  id: string | number;
  src: string;
  title: string;
  artist?: string;
  coverArt?: string;
};

interface AudioContextType {
  currentTrack: Track | null;
  playlist: Track[];
  playTrack: (track: Track, newPlaylist?: Track[]) => void;
  closePlayer: () => void;
  playNext: () => void;
  playPrevious: () => void;
}

const AudioContext = createContext<AudioContextType | undefined>(undefined);

export function GlobalAudioProvider({ children }: { children: React.ReactNode }) {
  const [currentTrack, setCurrentTrack] = useState<Track | null>(null);
  const [playlist, setPlaylist] = useState<Track[]>([]);

  const playTrack = useCallback((track: Track, newPlaylist?: Track[]) => {
    setCurrentTrack(track);
    if (newPlaylist) setPlaylist(newPlaylist);
  }, []);

  const closePlayer = useCallback(() => setCurrentTrack(null), []);

  const playNext = useCallback(() => {
    if (!currentTrack || playlist.length === 0) return;
    const currentIndex = playlist.findIndex(t => t.id === currentTrack.id);
    if (currentIndex < playlist.length - 1) {
      setCurrentTrack(playlist[currentIndex + 1]);
    }
  }, [currentTrack, playlist]);

  const playPrevious = useCallback(() => {
    if (!currentTrack || playlist.length === 0) return;
    const currentIndex = playlist.findIndex(t => t.id === currentTrack.id);
    if (currentIndex > 0) {
      setCurrentTrack(playlist[currentIndex - 1]);
    }
  }, [currentTrack, playlist]);

  return (
    <AudioContext.Provider value={{ currentTrack, playlist, playTrack, closePlayer, playNext, playPrevious }}>
      {children}
    </AudioContext.Provider>
  );
}

export const useGlobalAudio = () => {
  const context = useContext(AudioContext);
  if (!context) throw new Error("useGlobalAudio must be used within GlobalAudioProvider");
  return context;
};