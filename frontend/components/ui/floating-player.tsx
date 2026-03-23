//components/ui/floating-player.tsx
"use client";

import React, { useState, useRef, useEffect } from "react";
import { useGlobalAudio } from "@/context/global-audio-context";
import { AudioPlayer } from "./audio-player";

export function FloatingPlayer() {
  const { currentTrack, closePlayer, playNext, playPrevious, playlist } = useGlobalAudio();
  
  const [isMinimized, setIsMinimized] = useState(false);
  const dragRef = useRef<HTMLDivElement>(null);
  const startPos = useRef({ x: 0, y: 0, pointerX: 0, pointerY: 0 });

  // 🚀 ZERO-LATENCY DRAG LOGIC (Bypasses React State)
  const handlePointerDown = (e: React.PointerEvent<HTMLDivElement>) => {
    e.currentTarget.setPointerCapture(e.pointerId);
    const rect = dragRef.current?.getBoundingClientRect();
    if (rect) {
      startPos.current = {
        x: rect.left,
        y: rect.top,
        pointerX: e.clientX,
        pointerY: e.clientY,
      };
    }
  };

  const handlePointerMove = (e: React.PointerEvent<HTMLDivElement>) => {
    if (e.currentTarget.hasPointerCapture(e.pointerId) && dragRef.current) {
      const dx = e.clientX - startPos.current.pointerX;
      const dy = e.clientY - startPos.current.pointerY;
      
      // Directly mutating the DOM element's style is 100x faster than setState
      dragRef.current.style.left = `${startPos.current.x + dx}px`;
      dragRef.current.style.top = `${startPos.current.y + dy}px`;
      dragRef.current.style.bottom = 'auto';
      dragRef.current.style.right = 'auto';
    }
  };

  const handlePointerUp = (e: React.PointerEvent<HTMLDivElement>) => {
    e.currentTarget.releasePointerCapture(e.pointerId);
  };

  const dragProps = {
    onPointerDown: handlePointerDown,
    onPointerMove: handlePointerMove,
    onPointerUp: handlePointerUp,
  };

  if (!currentTrack) return null;

  const currentIndex = playlist.findIndex(t => t.id === currentTrack.id);
  const hasNext = currentIndex !== -1 && currentIndex < playlist.length - 1;
  const hasPrev = currentIndex !== -1 && currentIndex > 0;

  return (
    <div 
      ref={dragRef} 
      // Initial starting position
      style={{ bottom: '24px', right: '24px' }} 
      className="fixed z-[100] animate-in slide-in-from-bottom-10 fade-in duration-500 will-change-transform"
    >
      <AudioPlayer 
        id={currentTrack.id}
        src={currentTrack.src} 
        title={currentTrack.title}
        artist={currentTrack.artist}
        coverArt={currentTrack.coverArt}
        autoPlay={true}
        variant={isMinimized ? 'mini' : 'default'}
        onToggleMinimize={() => setIsMinimized(!isMinimized)}
        onClose={closePlayer}
        dragProps={dragProps}
        onNext={hasNext ? playNext : undefined}
        onPrevious={hasPrev ? playPrevious : undefined}
      />
    </div>
  );
}