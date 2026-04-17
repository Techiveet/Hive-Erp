"use client";

import React, { useEffect, useMemo, useRef, useState } from "react";

import { useGlobalAudio } from "@/context/global-audio-context";
import { AudioPlayer } from "./audio-player";

const POSITION_STORAGE_KEY = "hive_audio_player_pos_v2";
const MINIMIZED_STORAGE_KEY = "hive_audio_player_minimized";
const PADDING = 24;
const MINI_WIDTH = 340;
const FULL_WIDTH = 420;

type PlayerPosition = {
  x: number;
  y: number;
};

const getViewportBounds = (width: number, height: number) => ({
  minX: PADDING,
  maxX: Math.max(PADDING, window.innerWidth - width - PADDING),
  minY: PADDING,
  maxY: Math.max(PADDING, window.innerHeight - height - PADDING),
});

const clampPosition = (position: PlayerPosition, width: number, height: number): PlayerPosition => {
  const bounds = getViewportBounds(width, height);
  return {
    x: Math.min(bounds.maxX, Math.max(bounds.minX, position.x)),
    y: Math.min(bounds.maxY, Math.max(bounds.minY, position.y)),
  };
};

const getDefaultPosition = (width: number, height: number): PlayerPosition =>
  clampPosition(
    {
      x: window.innerWidth - width - PADDING,
      y: window.innerHeight - height - PADDING,
    },
    width,
    height,
  );

export function FloatingPlayer() {
  const { currentTrack, closePlayer } = useGlobalAudio();

  const [isMinimized, setIsMinimized] = useState(false);
  const [position, setPosition] = useState<PlayerPosition | null>(null);
  const wrapperRef = useRef<HTMLDivElement>(null);
  const dragStateRef = useRef<{
    pointerId: number | null;
    offsetX: number;
    offsetY: number;
  }>({
    pointerId: null,
    offsetX: 0,
    offsetY: 0,
  });

  const widthHint = useMemo(() => (isMinimized ? MINI_WIDTH : FULL_WIDTH), [isMinimized]);

  useEffect(() => {
    if (!currentTrack || typeof window === "undefined") {
      return;
    }

    const savedMinimized = window.localStorage.getItem(MINIMIZED_STORAGE_KEY);
    if (savedMinimized !== null) {
      setIsMinimized(savedMinimized === "true");
    }
  }, [currentTrack]);

  useEffect(() => {
    if (!currentTrack || typeof window === "undefined") {
      return;
    }

    const savedRaw = window.localStorage.getItem(POSITION_STORAGE_KEY);
    const rect = wrapperRef.current?.getBoundingClientRect();
    const heightHint = rect?.height ?? (isMinimized ? 68 : 520);

    if (savedRaw) {
      try {
        const saved = JSON.parse(savedRaw) as PlayerPosition;
        setPosition(clampPosition(saved, widthHint, heightHint));
        return;
      } catch {
        // Ignore bad saved position.
      }
    }

    setPosition(getDefaultPosition(widthHint, heightHint));
  }, [currentTrack, isMinimized, widthHint]);

  useEffect(() => {
    if (!currentTrack || typeof window === "undefined") {
      return;
    }

    const handleResize = () => {
      const rect = wrapperRef.current?.getBoundingClientRect();
      const nextHeight = rect?.height ?? (isMinimized ? 68 : 520);
      setPosition((previous) =>
        previous
          ? clampPosition(previous, widthHint, nextHeight)
          : getDefaultPosition(widthHint, nextHeight),
      );
    };

    window.addEventListener("resize", handleResize);
    return () => window.removeEventListener("resize", handleResize);
  }, [currentTrack, isMinimized, widthHint]);

  useEffect(() => {
    if (!position || typeof window === "undefined") {
      return;
    }

    window.localStorage.setItem(POSITION_STORAGE_KEY, JSON.stringify(position));
  }, [position]);

  const handlePointerDown = (event: React.PointerEvent<HTMLDivElement>) => {
    const target = event.target as HTMLElement;
    if (!target.closest("[data-audio-drag-handle]")) {
      return;
    }

    const rect = wrapperRef.current?.getBoundingClientRect();
    if (!rect) {
      return;
    }

    dragStateRef.current = {
      pointerId: event.pointerId,
      offsetX: event.clientX - rect.left,
      offsetY: event.clientY - rect.top,
    };

    event.currentTarget.setPointerCapture(event.pointerId);
  };

  const handlePointerMove = (event: React.PointerEvent<HTMLDivElement>) => {
    if (dragStateRef.current.pointerId !== event.pointerId) {
      return;
    }

    const rect = wrapperRef.current?.getBoundingClientRect();
    const nextWidth = rect?.width ?? widthHint;
    const nextHeight = rect?.height ?? (isMinimized ? 68 : 520);

    const nextPosition = clampPosition(
      {
        x: event.clientX - dragStateRef.current.offsetX,
        y: event.clientY - dragStateRef.current.offsetY,
      },
      nextWidth,
      nextHeight,
    );

    setPosition(nextPosition);
  };

  const handlePointerUp = (event: React.PointerEvent<HTMLDivElement>) => {
    if (dragStateRef.current.pointerId !== event.pointerId) {
      return;
    }

    dragStateRef.current.pointerId = null;
    if (event.currentTarget.hasPointerCapture(event.pointerId)) {
      event.currentTarget.releasePointerCapture(event.pointerId);
    }
  };

  const handleToggleMinimize = () => {
    setIsMinimized((previous) => {
      const nextState = !previous;
      if (typeof window !== "undefined") {
        window.localStorage.setItem(MINIMIZED_STORAGE_KEY, String(nextState));
      }
      return nextState;
    });
  };

  if (!currentTrack || !position) {
    return null;
  }

  return (
    <div
      ref={wrapperRef}
      onPointerDown={handlePointerDown}
      onPointerMove={handlePointerMove}
      onPointerUp={handlePointerUp}
      style={{ left: 0, top: 0, transform: `translate3d(${position.x}px, ${position.y}px, 0)` }}
      className="fixed z-[100] touch-none will-change-transform animate-in slide-in-from-bottom-10 fade-in duration-500"
    >
      <AudioPlayer
        variant={isMinimized ? "mini" : "default"}
        onToggleMinimize={handleToggleMinimize}
        onClose={closePlayer}
        dragProps={{ title: "Drag audio player" }}
      />
    </div>
  );
}
