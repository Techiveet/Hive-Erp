//components/ui/audio-player.tsx
"use client";

import React, { useState, useRef, useEffect, useCallback } from 'react';
import { 
  Play, Pause, Volume2, VolumeX, Music, 
  RotateCcw, RotateCw, Repeat, AlertCircle, Loader2,
  SkipBack, SkipForward, Shuffle, GripHorizontal, Minus, Maximize2, X
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface AudioPlayerProps {
  id?: string | number;
  src: string;
  title?: string;
  artist?: string;
  coverArt?: string;
  className?: string;
  autoPlay?: boolean;
  variant?: 'default' | 'mini';
  onToggleMinimize?: () => void;
  onClose?: () => void;
  dragProps?: React.HTMLAttributes<HTMLDivElement>;
  onNext?: () => void;
  onPrevious?: () => void;
  onShuffle?: (isShuffled: boolean) => void;
}

export function AudioPlayer({ 
  id, src, title = "Unknown Track", artist = "Unknown Artist",
  coverArt, className, autoPlay = false, variant = 'default',
  onToggleMinimize, onClose, dragProps,
  onNext, onPrevious, onShuffle
}: AudioPlayerProps) {
  const audioRef = useRef<HTMLAudioElement>(null);
  
  const [isPlaying, setIsPlaying] = useState(false);
  const [isBuffering, setIsBuffering] = useState(true);
  const [hasError, setHasError] = useState(false);
  const [progress, setProgress] = useState(0);
  const [currentTime, setCurrentTime] = useState("0:00");
  const [duration, setDuration] = useState("0:00");
  const [volume, setVolume] = useState(1);
  const [isMuted, setIsMuted] = useState(false);
  const [isLooping, setIsLooping] = useState(false);
  const [isShuffling, setIsShuffling] = useState(false);
  const [playbackRate, setPlaybackRate] = useState(1);

  const storageKey = `hive_audio_pos_${id || src}`;

  const formatTime = (time: number) => {
    if (isNaN(time) || !isFinite(time)) return "0:00";
    const minutes = Math.floor(time / 60);
    const seconds = Math.floor(time % 60);
    return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
  };

  useEffect(() => {
    if (audioRef.current) {
      audioRef.current.load();
      if (autoPlay || isPlaying) {
        audioRef.current.play()
          .then(() => setIsPlaying(true))
          .catch(() => {
            console.warn("Autoplay blocked. Waiting for user interaction.");
            setIsPlaying(false); 
          });
      }
    }
  }, [src, autoPlay]);

  useEffect(() => {
    if ('mediaSession' in navigator) {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: title,
        artist: artist,
        album: 'HIVE.OS',
        artwork: coverArt ? [{ src: coverArt, sizes: '512x512', type: 'image/jpeg' }] : []
      });

      navigator.mediaSession.setActionHandler('play', () => audioRef.current?.play());
      navigator.mediaSession.setActionHandler('pause', () => audioRef.current?.pause());
      if (onNext) navigator.mediaSession.setActionHandler('nexttrack', onNext);
      if (onPrevious) navigator.mediaSession.setActionHandler('previoustrack', onPrevious);
    }
  }, [title, artist, coverArt, onNext, onPrevious]);

  const handleTimeUpdate = () => {
    if (!audioRef.current) return;
    const current = audioRef.current.currentTime;
    setProgress((current / audioRef.current.duration) * 100);
    setCurrentTime(formatTime(current));
    if (Math.floor(current) % 5 === 0) localStorage.setItem(storageKey, current.toString());
  };

  const handleLoadedMetadata = () => {
    if (!audioRef.current) return;
    setDuration(formatTime(audioRef.current.duration));
    setIsBuffering(false);
    const savedPosition = localStorage.getItem(storageKey);
    if (savedPosition && !autoPlay) audioRef.current.currentTime = parseFloat(savedPosition);
  };

  const handleEnded = () => {
    if (isLooping) return;
    localStorage.removeItem(storageKey);
    if (onNext && !isLooping) {
      onNext();
    } else {
      setIsPlaying(false);
    }
  };

  const togglePlay = useCallback(() => {
    if (!audioRef.current || hasError) return;

    if (audioRef.current.paused) {
      audioRef.current.play()
        .then(() => setIsPlaying(true))
        .catch((err) => {
          if (err.name !== 'NotAllowedError' && err.name !== 'AbortError') setHasError(true);
          setIsPlaying(false);
        });
    } else {
      audioRef.current.pause();
      setIsPlaying(false);
    }
  }, [hasError]);

  const handleSeek = (e: React.ChangeEvent<HTMLInputElement>) => {
    const val = parseFloat(e.target.value);
    setProgress(val);
    if (audioRef.current) audioRef.current.currentTime = (val / 100) * audioRef.current.duration;
  };

  const skip = (seconds: number) => {
    if (audioRef.current) audioRef.current.currentTime += seconds;
  };

  const togglePlaybackRate = () => {
    const rates = [1, 1.25, 1.5, 2];
    const nextIndex = (rates.indexOf(playbackRate) + 1) % rates.length;
    const newRate = rates[nextIndex];
    setPlaybackRate(newRate);
    if (audioRef.current) audioRef.current.playbackRate = newRate;
  };

  // 🚀 VOLUME HANDLER
  const handleVolumeChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const val = parseFloat(e.target.value);
    setVolume(val);
    if (audioRef.current) { 
      audioRef.current.volume = val; 
      audioRef.current.muted = val === 0; 
      setIsMuted(val === 0); 
    }
  };

  const toggleMute = () => {
    const newMutedState = !isMuted;
    setIsMuted(newMutedState);
    if (audioRef.current) {
      audioRef.current.muted = newMutedState;
      if (!newMutedState && volume === 0) {
        setVolume(1);
        audioRef.current.volume = 1;
      }
    }
  };

  return (
    <div className={cn("relative transition-all shadow-2xl overflow-hidden", 
      variant === 'default' ? "w-full max-w-md bg-card/90 backdrop-blur-2xl border border-border/50 rounded-[2rem] p-5" 
      : "w-[400px] bg-card/95 backdrop-blur-2xl border border-border/50 rounded-full p-2 pr-3 flex items-center gap-3", 
      className
    )}>
      
      <audio 
        ref={audioRef} src={src}
        onTimeUpdate={handleTimeUpdate} onLoadedMetadata={handleLoadedMetadata} 
        onEnded={handleEnded} onWaiting={() => setIsBuffering(true)}
        onCanPlay={() => setIsBuffering(false)} onError={() => { setHasError(true); setIsBuffering(false); }}
      />

      {/* 🚀 THE MINI UI PILL */}
      <div className={cn(variant === 'mini' ? 'contents' : 'hidden')}>
        <div {...dragProps} className="cursor-grab active:cursor-grabbing text-muted-foreground hover:text-foreground touch-none pl-2 py-2">
          <GripHorizontal className="h-4 w-4" />
        </div>
        
        <div className="h-9 w-9 bg-pink-500/20 rounded-full shrink-0 flex items-center justify-center overflow-hidden border border-white/10 relative">
          {coverArt ? <img src={coverArt} alt="cover" className={cn("object-cover w-full h-full", isPlaying && "animate-[spin_4s_linear_infinite]")} /> : <Music className="h-4 w-4 text-pink-500" />}
        </div>
        
        <div className="flex-1 overflow-hidden select-none" {...dragProps}>
          <p className="text-xs font-bold truncate text-foreground leading-tight cursor-grab">{title}</p>
          <p className="text-[10px] text-muted-foreground truncate">{formatTime((progress / 100) * (audioRef.current?.duration || 0))} / {duration}</p>
        </div>

        <div className="flex items-center gap-2 shrink-0">
          <button onClick={togglePlay} className="h-8 w-8 bg-pink-500 hover:bg-pink-600 rounded-full flex items-center justify-center text-white transition-transform active:scale-95 shadow-md">
            {isPlaying ? <Pause className="h-3 w-3 fill-current" /> : <Play className="h-3 w-3 fill-current ml-0.5" />}
          </button>
          {onNext && <button onClick={onNext} className="text-foreground hover:text-pink-500 transition-colors"><SkipForward className="h-4 w-4 fill-current" /></button>}
        </div>

        {/* 🚀 Hover-to-expand Volume in Mini Mode */}
        <div className="flex items-center gap-1 group shrink-0 relative px-1">
          <button onClick={toggleMute} className="text-muted-foreground hover:text-foreground transition-colors p-1">
            {isMuted || volume === 0 ? <VolumeX className="h-3.5 w-3.5" /> : <Volume2 className="h-3.5 w-3.5" />}
          </button>
          <div className="w-0 overflow-hidden group-hover:w-16 transition-all duration-300 ease-out flex items-center">
            <input 
              type="range" min="0" max="1" step="0.05" value={isMuted ? 0 : volume} 
              onChange={handleVolumeChange}
              className="w-14 h-1 bg-muted rounded-lg appearance-none cursor-pointer accent-pink-500"
            />
          </div>
        </div>

        <div className="flex items-center gap-1.5 border-l border-border/50 pl-2 shrink-0">
          {onToggleMinimize && <button onClick={onToggleMinimize} className="text-muted-foreground hover:text-foreground transition-colors p-1"><Maximize2 className="h-3.5 w-3.5" /></button>}
          {onClose && <button onClick={onClose} className="text-muted-foreground hover:text-destructive transition-colors p-1"><X className="h-4 w-4" /></button>}
        </div>
      </div>

      {/* 🚀 THE FULL UI */}
      <div className={cn(variant === 'default' ? 'flex flex-col gap-5 relative z-10' : 'hidden')}>
        <div className="absolute top-0 right-0 flex items-center gap-2 z-50">
          {onToggleMinimize && (
            <button onClick={onToggleMinimize} className="p-1.5 text-muted-foreground/60 hover:text-foreground bg-background/50 backdrop-blur rounded-full transition-colors">
              <Minus className="h-4 w-4" />
            </button>
          )}
          {onClose && (
            <button onClick={onClose} className="p-1.5 text-muted-foreground/60 hover:text-white bg-background/50 hover:bg-destructive backdrop-blur rounded-full transition-colors">
              <X className="h-4 w-4" />
            </button>
          )}
        </div>

        <div {...dragProps} className="absolute -top-3 -left-3 -right-3 h-8 cursor-grab active:cursor-grabbing flex justify-center items-start pt-2 z-40 touch-none">
           <GripHorizontal className="h-5 w-5 text-muted-foreground/30 hover:text-muted-foreground transition-colors" />
        </div>

        {coverArt && (
          <div className="absolute inset-0 z-[-1] opacity-20 blur-3xl saturate-200 bg-cover bg-center pointer-events-none scale-150 transition-all duration-1000" style={{ backgroundImage: `url(${coverArt})` }} />
        )}

        <div className="flex items-center gap-4 mt-2">
          <div className="h-16 w-16 bg-gradient-to-br from-pink-500/20 to-purple-500/20 rounded-2xl flex items-center justify-center shrink-0 border border-white/10 shadow-lg overflow-hidden relative">
            {coverArt ? <img src={coverArt} alt="cover" className={cn("object-cover w-full h-full transition-transform duration-700", isPlaying ? "scale-100" : "scale-110")} /> : <Music className={cn("h-7 w-7 text-pink-500", isPlaying && "animate-pulse")} />}
            {isBuffering && !hasError && <div className="absolute inset-0 bg-background/50 flex items-center justify-center backdrop-blur-sm"><Loader2 className="h-5 w-5 text-pink-500 animate-spin" /></div>}
          </div>
          
          <div className="overflow-hidden flex-1 drop-shadow-md pr-12">
            <p className="text-base font-bold truncate text-foreground">{title}</p>
            <p className="text-xs text-muted-foreground truncate mt-0.5">{artist}</p>
          </div>
        </div>

        <div className="space-y-1.5">
          <div className="flex items-center gap-3">
            <span className="text-[10px] font-mono text-muted-foreground w-8 drop-shadow-sm">{currentTime}</span>
            <input 
              type="range" min="0" max="100" value={progress || 0} onChange={handleSeek} disabled={hasError}
              className="flex-1 h-1.5 bg-foreground/10 rounded-lg appearance-none cursor-pointer accent-pink-500 disabled:opacity-50" 
            />
            <span className="text-[10px] font-mono text-muted-foreground w-8 text-right drop-shadow-sm">{duration}</span>
          </div>
        </div>

        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3 w-[88px]">
            <button onClick={() => { setIsShuffling(!isShuffling); if(onShuffle) onShuffle(!isShuffling); }} className={cn("transition-colors hover:text-foreground", isShuffling ? "text-pink-500" : "text-muted-foreground")}><Shuffle className="h-4 w-4" /></button>
            <button onClick={() => setIsLooping(!isLooping)} className={cn("transition-colors hover:text-foreground", isLooping ? "text-pink-500" : "text-muted-foreground")}><Repeat className="h-4 w-4" /></button>
          </div>
          
          <div className="flex items-center gap-4">
            {onPrevious ? <button onClick={onPrevious} disabled={hasError} className="text-foreground hover:text-pink-500 transition-colors disabled:opacity-50 drop-shadow-sm"><SkipBack className="h-5 w-5 fill-current" /></button> : <button onClick={() => skip(-10)} disabled={hasError} className="text-foreground hover:text-pink-500 transition-colors disabled:opacity-50 drop-shadow-sm"><RotateCcw className="h-5 w-5" /></button>}

            <button onClick={togglePlay} disabled={hasError} className="h-12 w-12 bg-pink-500 hover:bg-pink-600 disabled:bg-muted disabled:text-muted-foreground rounded-full flex items-center justify-center text-white transition-transform active:scale-95 shadow-lg shadow-pink-500/20">
              {isPlaying ? <Pause className="h-5 w-5 fill-current" /> : <Play className="h-5 w-5 fill-current ml-1" />}
            </button>

            {onNext ? <button onClick={onNext} disabled={hasError} className="text-foreground hover:text-pink-500 transition-colors disabled:opacity-50 drop-shadow-sm"><SkipForward className="h-5 w-5 fill-current" /></button> : <button onClick={() => skip(10)} disabled={hasError} className="text-foreground hover:text-pink-500 transition-colors disabled:opacity-50 drop-shadow-sm"><RotateCw className="h-5 w-5" /></button>}
          </div>

          <div className="flex items-center justify-end gap-2 w-[88px] group">
            <button onClick={togglePlaybackRate} className="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-foreground/10 text-foreground hover:bg-foreground/20 transition-colors backdrop-blur-md mr-1">{playbackRate}x</button>
            
            {/* 🚀 Standard Default Volume Controls */}
            <button onClick={toggleMute} className="text-muted-foreground hover:text-foreground transition-colors shrink-0">
              {isMuted || volume === 0 ? <VolumeX className="h-4 w-4" /> : <Volume2 className="h-4 w-4" />}
            </button>
            <input 
              type="range" min="0" max="1" step="0.05" value={isMuted ? 0 : volume} 
              onChange={handleVolumeChange}
              className="w-16 h-1 bg-foreground/10 rounded-lg appearance-none cursor-pointer accent-pink-500 opacity-0 group-hover:opacity-100 transition-opacity focus:opacity-100"
            />
          </div>
        </div>
      </div>
    </div>
  );
}