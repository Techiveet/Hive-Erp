// components/ui/video-player.tsx
"use client";

import React, { useState, useRef, useEffect, MouseEvent as ReactMouseEvent } from 'react';
import Hls from 'hls.js';
import { toast } from 'sonner';
import { 
  Play, Pause, Volume2, VolumeX, Maximize, Minimize, Loader2, 
  SkipBack, SkipForward, Subtitles, Settings, Gauge, Check, 
  AlertCircle, PictureInPicture
} from 'lucide-react';
import { cn } from '@/lib/utils';

export interface SubtitleTrack {
  src: string;
  srcLang: string;
  label: string;
  default?: boolean;
}

export interface VideoVersion {
  label: string;
  url: string;
}

interface VideoPlayerProps {
  src: string;
  poster?: string;
  className?: string;
  watermark?: React.ReactNode; 
  onPrevious?: () => void;
  onNext?: () => void;
  subtitles?: SubtitleTrack[];
  videoVersions?: VideoVersion[];
  authToken?: string | null; 
  initialTime?: number;
  isMiniplayer?: boolean;
  onMiniplayerRequest?: (time: number) => void;
  onMaximizeRequest?: (time: number) => void;
  onCloseRequest?: () => void;
}

export function VideoPlayer({ 
  src, 
  poster, 
  className, 
  watermark, 
  onPrevious, 
  onNext, 
  subtitles = [], 
  videoVersions = [],
  authToken = null,
  initialTime = 0,
  isMiniplayer = false,
  onMiniplayerRequest,
  onMaximizeRequest,
  onCloseRequest
}: VideoPlayerProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const videoRef = useRef<HTMLVideoElement>(null);
  const hlsRef = useRef<Hls | null>(null);
  const controlsTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const clickTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  const [isPlaying, setIsPlaying] = useState(false);
  const [progress, setProgress] = useState(0);
  const [currentTime, setCurrentTime] = useState("0:00");
  const [duration, setDuration] = useState("0:00");
  const [volume, setVolume] = useState(1);
  const [isMuted, setIsMuted] = useState(false);
  const [isFullscreen, setIsFullscreen] = useState(false);
  
  const [isBuffering, setIsBuffering] = useState(true);
  const [hasError, setHasError] = useState(false); 
  const [showControls, setShowControls] = useState(true);
  
  const [activeSubtitle, setActiveSubtitle] = useState<number>(-1);
  const [playbackRate, setPlaybackRate] = useState(1);
  
  // Quality State
  const [qualityLevels, setQualityLevels] = useState<any[]>([]);
  const [activeQuality, setActiveQuality] = useState<number>(-1);
  
  const [showSpeed, setShowSpeed] = useState(false);
  const [showQuality, setShowQuality] = useState(false);
  const [showCC, setShowCC] = useState(false);

  const [showPlayAnim, setShowPlayAnim] = useState(false);
  const [showPauseAnim, setShowPauseAnim] = useState(false);
  const [seekAnimDir, setSeekAnimDir] = useState<'forward' | 'backward' | null>(null);
  const [hoverTime, setHoverTime] = useState<string | null>(null);
  const [hoverProgress, setHoverProgress] = useState<number>(0);

  const playAnimTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const seekAnimTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  const [localSubtitles, setLocalSubtitles] = useState<SubtitleTrack[]>([]);
  const subtitlesString = JSON.stringify(subtitles);

  // 1. Fetch Subtitles securely using the provided Auth Token
  useEffect(() => {
    let objectUrls: string[] = [];

    const loadSubtitlesAsBlobs = async () => {
      const processedSubs = await Promise.all(
        subtitles.map(async (sub) => {
          try {
            const headers: Record<string, string> = {};
            if (authToken) headers['Authorization'] = `Bearer ${authToken}`;

            const res = await fetch(sub.src, { headers });
            if (!res.ok) throw new Error(`Fetch failed: ${res.status}`);
            
            const text = await res.text();
            const blob = new Blob([text], { type: 'text/vtt' });
            const localUrl = URL.createObjectURL(blob);
            objectUrls.push(localUrl);

            return { ...sub, src: localUrl };
          } catch (error) {
            return sub; 
          }
        })
      );
      
      setLocalSubtitles(processedSubs);
      const defaultIdx = processedSubs.findIndex(s => s.default);
      if (defaultIdx !== -1) setActiveSubtitle(defaultIdx);
    };

    if (subtitles.length > 0) loadSubtitlesAsBlobs();
    else { setLocalSubtitles([]); setActiveSubtitle(-1); }

    return () => objectUrls.forEach(url => URL.revokeObjectURL(url));
  }, [subtitlesString, authToken]);

  // 2. Load Fallback MP4 Qualities if not using HLS
  useEffect(() => {
    if (!src.includes('.m3u8') && videoVersions.length > 0) {
      setQualityLevels(videoVersions);
      setActiveQuality(0); 
    }
  }, [src, videoVersions]);

  // 3. Mount Video Source (HLS vs Native)
  useEffect(() => {
    const video = videoRef.current;
    if (!video) return;

    // Apply initial time if provided
    if (initialTime && initialTime > 0) {
      video.currentTime = initialTime;
      setProgress((initialTime / video.duration) * 100);
      setCurrentTime(formatTime(initialTime));
    }

    setIsBuffering(true);
    setIsPlaying(false);
    setHasError(false);
    setProgress(0);

    if (src.includes('.m3u8')) {
      if (Hls.isSupported()) {
        const hls = new Hls({ renderTextTracksNatively: true });
        hlsRef.current = hls;
        hls.loadSource(src);
        hls.attachMedia(video);
        
        hls.on(Hls.Events.MANIFEST_PARSED, (event, data) => {
           setIsBuffering(false);
           setQualityLevels(data.levels); 
        });
        hls.on(Hls.Events.LEVEL_SWITCHED, (event, data) => setActiveQuality(data.level));
        hls.on(Hls.Events.ERROR, (event, data) => {
           if (data.fatal) {
             switch(data.type) {
               case Hls.ErrorTypes.NETWORK_ERROR:
                 console.warn("HLS Network Error, attempting recovery...");
                 hls.startLoad();
                 break;
               case Hls.ErrorTypes.MEDIA_ERROR:
                 console.warn("HLS Media Error, attempting recovery...");
                 hls.recoverMediaError();
                 break;
               default:
                 hls.destroy();
                 setIsBuffering(false); 
                 setHasError(true);
                 break;
             }
           }
        });
      } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = src;
        video.addEventListener('loadedmetadata', () => setIsBuffering(false));
      }
    } else {
      video.src = src;
      video.load(); 
      const handleCanPlay = () => setIsBuffering(false);
      const handleError = () => { setIsBuffering(false); setHasError(true); };
      
      video.addEventListener('canplay', handleCanPlay);
      video.addEventListener('error', handleError);
      return () => {
          video.removeEventListener('canplay', handleCanPlay);
          video.removeEventListener('error', handleError);
      };
    }

    return () => { if (hlsRef.current) hlsRef.current.destroy(); };
  }, [src]);

  // 4. Inject Subtitles
  useEffect(() => {
    const video = videoRef.current;
    if (!video) return;
    while (video.firstChild) video.removeChild(video.firstChild);

    localSubtitles.forEach((sub, index) => {
      const track = document.createElement('track');
      track.kind = 'subtitles';
      track.src = sub.src;
      track.srclang = sub.srcLang;
      track.label = sub.label;
      if (index === activeSubtitle) track.default = true;
      video.appendChild(track);
    });

    const updateTracks = () => {
        const textTracks = video.textTracks;
        for (let i = 0; i < textTracks.length; i++) {
          textTracks[i].mode = i === activeSubtitle ? 'showing' : 'hidden';
        }
    };
    updateTracks();
    const timer = setTimeout(updateTracks, 250); 
    return () => clearTimeout(timer);
  }, [activeSubtitle, localSubtitles]);

  // Native PiP logic removed in favor of In-Tab Miniplayer

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (document.activeElement?.tagName === 'INPUT' || document.activeElement?.tagName === 'TEXTAREA') return;
      if (!videoRef.current) return;
      
      if (e.key >= '0' && e.key <= '9') {
        const percentage = parseInt(e.key) * 10;
        videoRef.current.currentTime = (percentage / 100) * videoRef.current.duration;
        return;
      }

      switch(e.key.toLowerCase()) {
        case ' ': 
        case 'k': e.preventDefault(); togglePlay(); break;
        case 'f': e.preventDefault(); toggleFullscreen(); break;
        case 'm': e.preventDefault(); toggleMute(); break;
        case 'arrowright': e.preventDefault(); videoRef.current.currentTime += 5; break;
        case 'arrowleft': e.preventDefault(); videoRef.current.currentTime -= 5; break;
        case 'arrowup': 
          e.preventDefault(); 
          const newVolUp = Math.min(1, volume + 0.05);
          setVolume(newVolUp);
          videoRef.current.volume = newVolUp;
          if (newVolUp > 0) { setIsMuted(false); videoRef.current.muted = false; }
          break;
        case 'arrowdown': 
          e.preventDefault(); 
          const newVolDown = Math.max(0, volume - 0.05);
          setVolume(newVolDown);
          videoRef.current.volume = newVolDown;
          if (newVolDown === 0) { setIsMuted(true); videoRef.current.muted = true; }
          break;
        case 'c': e.preventDefault(); setShowCC(prev => !prev); break;
        case 'i': 
          e.preventDefault(); 
          if (isMiniplayer && onMaximizeRequest) onMaximizeRequest(videoRef.current.currentTime);
          else if (!isMiniplayer && onMiniplayerRequest) onMiniplayerRequest(videoRef.current.currentTime);
          break;
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isPlaying, isFullscreen, volume, showCC]);

  const handleMouseMove = () => {
    setShowControls(true);
    if (controlsTimeoutRef.current) clearTimeout(controlsTimeoutRef.current);
    if (isPlaying) {
      controlsTimeoutRef.current = setTimeout(() => {
        if (!showSpeed && !showQuality && !showCC) setShowControls(false);
      }, 2500);
    }
  };

  const handleMouseLeave = () => {
    if (isPlaying && !showSpeed && !showQuality && !showCC) setShowControls(false);
  };

  const formatTime = (time: number) => {
    if (isNaN(time) || !isFinite(time)) return "0:00";
    const minutes = Math.floor(time / 60);
    const seconds = Math.floor(time % 60);
    return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
  };

  const handleTimeUpdate = () => {
    if (!videoRef.current) return;
    setProgress((videoRef.current.currentTime / videoRef.current.duration) * 100);
    setCurrentTime(formatTime(videoRef.current.currentTime));
  };

  const handleProgressMouseMove = (e: ReactMouseEvent<HTMLDivElement>) => {
    if (!videoRef.current || !videoRef.current.duration) return;
    const rect = e.currentTarget.getBoundingClientRect();
    const hoverX = e.clientX - rect.left;
    let percent = hoverX / rect.width;
    if (percent < 0) percent = 0;
    if (percent > 1) percent = 1;
    setHoverProgress(percent * 100);
    const time = percent * videoRef.current.duration;
    setHoverTime(formatTime(time));
  };

  const handleProgressMouseLeave = () => {
    setHoverTime(null);
  };

  const togglePlay = () => {
    if (hasError) return;
    if (videoRef.current?.paused) {
      setShowPlayAnim(true);
      if (playAnimTimeoutRef.current) clearTimeout(playAnimTimeoutRef.current);
      playAnimTimeoutRef.current = setTimeout(() => setShowPlayAnim(false), 500);

      videoRef.current.play().then(() => setIsPlaying(true)).catch(() => setIsPlaying(false));
    } else {
      setShowPauseAnim(true);
      if (playAnimTimeoutRef.current) clearTimeout(playAnimTimeoutRef.current);
      playAnimTimeoutRef.current = setTimeout(() => setShowPauseAnim(false), 500);

      videoRef.current?.pause();
      setIsPlaying(false);
    }
  };

  const triggerSeekAnim = (dir: 'forward' | 'backward') => {
    setSeekAnimDir(dir);
    if (seekAnimTimeoutRef.current) clearTimeout(seekAnimTimeoutRef.current);
    seekAnimTimeoutRef.current = setTimeout(() => setSeekAnimDir(null), 500);
  };

  const toggleMute = () => {
    const newMuted = !isMuted;
    setIsMuted(newMuted);
    if (videoRef.current) videoRef.current.muted = newMuted;
  };

  const handleSeek = (e: React.ChangeEvent<HTMLInputElement>) => {
    const val = parseFloat(e.target.value);
    setProgress(val);
    if (videoRef.current) videoRef.current.currentTime = (val / 100) * videoRef.current.duration;
  };

  const handleVideoClick = (e: ReactMouseEvent<HTMLVideoElement>) => {
    if (e.detail === 1) {
        clickTimeoutRef.current = setTimeout(() => togglePlay(), 200);
    } else if (e.detail === 2) {
        if (clickTimeoutRef.current) clearTimeout(clickTimeoutRef.current);
        if (!videoRef.current) return;
        const rect = e.currentTarget.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        if (clickX > rect.width / 2) {
          videoRef.current.currentTime += 10; 
          triggerSeekAnim('forward');
        } else {
          videoRef.current.currentTime -= 10; 
          triggerSeekAnim('backward');
        }
    }
  };

  const changePlaybackRate = (rate: number) => {
    if (videoRef.current) {
      videoRef.current.playbackRate = rate;
      setPlaybackRate(rate);
      setShowSpeed(false);
    }
  };

  // 5. Change Quality
  const changeQuality = (levelIndex: number) => {
      if (hlsRef.current) {
          hlsRef.current.currentLevel = levelIndex;
          setActiveQuality(levelIndex);
          setShowQuality(false);
          const qualityLabel = levelIndex === -1 ? 'Auto' : `${qualityLevels[levelIndex]?.height}p`;
          toast.success(`Quality changed to ${qualityLabel}`);
      } 
      else if (qualityLevels.length > 0 && qualityLevels[levelIndex]?.url) {
          const video = videoRef.current;
          if (!video) return;

          const currentPos = video.currentTime;
          const wasPlaying = !video.paused;

          video.src = qualityLevels[levelIndex].url;
          video.currentTime = currentPos;
          
          if (wasPlaying) {
              video.play().catch(() => setIsPlaying(false));
          }

          setActiveQuality(levelIndex);
          setShowQuality(false);
          toast.success(`Quality changed to ${qualityLevels[levelIndex].label}`);
      }
  };

  const toggleFullscreen = () => {
    if (!containerRef.current) return;
    if (!document.fullscreenElement) {
      containerRef.current.requestFullscreen().then(() => setIsFullscreen(true)).catch(() => {});
    } else {
      document.exitFullscreen().then(() => setIsFullscreen(false)).catch(() => {});
    }
  };

  const handleMenuToggle = (menu: 'cc' | 'speed' | 'quality') => {
      setShowCC(menu === 'cc' ? !showCC : false);
      setShowSpeed(menu === 'speed' ? !showSpeed : false);
      setShowQuality(menu === 'quality' ? !showQuality : false);
  };

  const cursorStateClass = (showControls || !isPlaying) 
      ? "!cursor-default [&_*]:!cursor-default" 
      : "!cursor-none [&_*]:!cursor-none";

  return (
    <div 
      ref={containerRef} 
      onMouseMove={handleMouseMove}
      onMouseLeave={handleMouseLeave}
      className={cn(
        "relative group bg-black overflow-hidden flex items-center justify-center w-full focus:outline-none transition-all duration-300", 
        isFullscreen ? "rounded-none border-none" : "rounded-[2rem] border border-border/50 shadow-inner",
        cursorStateClass,
        className
      )}
      tabIndex={0}
    >
      {watermark && !hasError && (
        <div className="absolute top-4 right-4 z-20 opacity-40 pointer-events-none select-none drop-shadow-md">
          {typeof watermark === 'string' ? <span className="text-white font-black tracking-widest text-sm opacity-70">{watermark}</span> : watermark}
        </div>
      )}

      {isBuffering && !hasError && (
        <div className="absolute inset-0 z-10 flex items-center justify-center bg-black/60 backdrop-blur-sm pointer-events-none">
          <Loader2 className="h-10 w-10 text-primary animate-spin drop-shadow-[0_0_15px_hsl(var(--primary)/0.5)]" />
        </div>
      )}

      {hasError && (
        <div className="absolute inset-0 z-20 flex flex-col items-center justify-center bg-black/90 p-6 text-center">
          <AlertCircle className="h-12 w-12 text-destructive mb-4 opacity-80" />
          <p className="text-white font-bold">Media Playback Error</p>
          <p className="text-zinc-400 text-xs mt-2 max-w-sm">The browser cannot play this video format directly. Try downloading the raw file instead.</p>
        </div>
      )}

      {!isPlaying && !isBuffering && !hasError && (
        <div 
            className="absolute inset-0 z-10 flex items-center justify-center bg-black/30 backdrop-blur-[2px] transition-all hover:bg-black/10"
            onClick={togglePlay}
        >
            <div className="h-20 w-20 bg-primary/90 backdrop-blur-md rounded-2xl flex items-center justify-center shadow-[0_0_40px_hsl(var(--primary)_/_0.4)] border border-primary/50 transition-transform duration-300 hover:scale-110 hover:shadow-[0_0_60px_hsl(var(--primary)_/_0.6)]">
                <Play className="h-10 w-10 text-primary-foreground fill-primary-foreground ml-1" />
            </div>
        </div>
      )}

      {/* Center Play/Pause Animations */}
      <div className={cn(
        "absolute inset-0 z-20 pointer-events-none flex items-center justify-center transition-opacity duration-300",
        showPlayAnim || showPauseAnim ? "opacity-100" : "opacity-0"
      )}>
        {showPlayAnim && (
          <div className="bg-black/60 backdrop-blur-sm rounded-full p-6 animate-out fade-out zoom-out-50 duration-500 fill-mode-forwards text-white drop-shadow-xl scale-150">
            <Play className="h-10 w-10 fill-current ml-1" />
          </div>
        )}
        {showPauseAnim && (
          <div className="bg-black/60 backdrop-blur-sm rounded-full p-6 animate-out fade-out zoom-out-50 duration-500 fill-mode-forwards text-white drop-shadow-xl scale-150">
            <Pause className="h-10 w-10 fill-current" />
          </div>
        )}
      </div>

      {/* Floating Hover PiP Removed in favor of Miniplayer */}

      {/* Double Click Seek Animations */}
      <div className="absolute inset-x-0 top-1/2 -translate-y-1/2 z-20 pointer-events-none flex items-center justify-between px-10 sm:px-20 overflow-hidden">
        <div className={cn(
          "flex flex-col items-center bg-black/40 backdrop-blur-sm rounded-full p-5 text-white transition-all duration-300",
          seekAnimDir === 'backward' ? "opacity-100 animate-in fade-in slide-in-from-right-8 scale-110" : "opacity-0 translate-x-12"
        )}>
          <SkipBack className="h-8 w-8 mb-1 fill-current" />
          <span className="font-bold text-sm">-10s</span>
        </div>
        
        <div className={cn(
          "flex flex-col items-center bg-black/40 backdrop-blur-sm rounded-full p-5 text-white transition-all duration-300",
          seekAnimDir === 'forward' ? "opacity-100 animate-in fade-in slide-in-from-left-8 scale-110" : "opacity-0 -translate-x-12"
        )}>
          <SkipForward className="h-8 w-8 mb-1 fill-current" />
          <span className="font-bold text-sm">+10s</span>
        </div>
      </div>

      <video
        ref={videoRef}
        poster={poster}
        preload="metadata"
        className={cn("w-full max-h-[100vh] object-contain", hasError && "opacity-0")}
        onClick={handleVideoClick}
        onTimeUpdate={handleTimeUpdate}
        onLoadedMetadata={() => setDuration(formatTime(videoRef.current?.duration || 0))}
        onWaiting={() => setIsBuffering(true)}
        onPlay={() => setIsPlaying(true)}
        onPause={() => setIsPlaying(false)}
        onPlaying={() => setIsBuffering(false)}
        onEnded={() => { setIsPlaying(false); if (onNext) onNext(); }}
        playsInline
      />

      {!hasError && (
          <div className={cn(
            "absolute bottom-0 left-0 right-0 p-4 sm:px-6 pb-6 bg-gradient-to-t from-black/95 via-black/80 to-transparent transition-opacity duration-300 z-30",
            showControls || !isPlaying ? "opacity-100" : "opacity-0 pointer-events-none"
          )}>
            <div className="flex items-center gap-3 mb-4">
              <span className="text-xs font-mono text-white/80 w-10 text-center">{currentTime}</span>
              <div 
                className="relative flex-1 flex items-center group/scrubber h-6 cursor-pointer"
              >
                {hoverTime && (
                  <div 
                    className="absolute bottom-5 flex flex-col items-center transform -translate-x-1/2 pointer-events-none z-50 animate-in fade-in zoom-in-95 duration-100"
                    style={{ left: `${hoverProgress}%` }}
                  >
                    <div className="bg-black/95 text-white text-[10px] font-bold px-2 py-1 rounded shadow-xl text-center border border-white/10 whitespace-nowrap">
                      {hoverTime}
                    </div>
                  </div>
                )}
                
                <input 
                  type="range" min="0" max="100" value={progress || 0} 
                  onChange={handleSeek}
                  onMouseMove={handleProgressMouseMove}
                  onMouseLeave={handleProgressMouseLeave}
                  className="w-full h-1.5 bg-white/20 rounded-full appearance-none accent-primary hover:h-2.5 transition-all duration-300 z-10 relative shadow-[0_0_10px_hsl(var(--primary)_/_0.5)] !cursor-pointer [&::-webkit-slider-thumb]:cursor-pointer [&::-moz-range-thumb]:cursor-pointer" 
                />
              </div>
              <span className="text-xs font-mono text-white/80 w-10 text-center">{duration}</span>
            </div>

            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2 sm:gap-5">
                
                <button type="button" onClick={onPrevious} disabled={!onPrevious} className={cn("transition-colors", onPrevious ? "text-white/80 hover:text-white" : "text-white/20 cursor-not-allowed")} title="Previous">
                  <SkipBack className="h-5 w-5 fill-current" />
                </button>

                <button type="button" onClick={togglePlay} className="text-white hover:text-primary transition-all drop-shadow-[0_0_8px_hsl(var(--primary)/0.5)] mx-1 transform hover:scale-110" title={isPlaying ? "Pause (Space)" : "Play (Space)"}>
                  {isPlaying ? <Pause className="h-8 w-8 fill-current" /> : <Play className="h-8 w-8 fill-current" />}
                </button>

                <button type="button" onClick={onNext} disabled={!onNext} className={cn("transition-colors", onNext ? "text-white/80 hover:text-white" : "text-white/20 cursor-not-allowed")} title="Next">
                  <SkipForward className="h-5 w-5 fill-current" />
                </button>

                <div className="flex items-center gap-2 group/vol ml-4">
                  <button type="button" onClick={toggleMute} className="text-white hover:text-primary transition-colors" title="Mute (m)">
                    {isMuted || volume === 0 ? <VolumeX className="h-6 w-6" /> : <Volume2 className="h-6 w-6" />}
                  </button>
                  <input 
                    type="range" min="0" max="1" step="0.05" value={isMuted ? 0 : volume} 
                    onChange={(e) => {
                      const val = parseFloat(e.target.value);
                      setVolume(val);
                      if (videoRef.current) { videoRef.current.volume = val; videoRef.current.muted = val === 0; setIsMuted(val === 0); }
                    }}
                    className="w-0 opacity-0 group-hover/vol:w-20 group-hover/vol:opacity-100 h-1.5 bg-white/20 rounded-full appearance-none accent-primary transition-all duration-300"
                  />
                </div>
              </div>

              <div className="flex items-center gap-5 relative">
                
                {localSubtitles.length > 0 && (
                  <div className="relative">
                    <button type="button" onClick={() => handleMenuToggle('cc')} className={cn("transition-colors", activeSubtitle !== -1 ? "text-primary drop-shadow-[0_0_8px_hsl(var(--primary)/0.6)]" : "text-white hover:text-white/80")} title="Subtitles/CC">
                      <Subtitles className="h-6 w-6" />
                    </button>
                    {showCC && (
                      <div className="absolute bottom-full right-0 mb-4 w-44 bg-background/90 backdrop-blur-2xl border border-border/50 rounded-2xl shadow-2xl p-2 flex flex-col gap-1 z-50 animate-in slide-in-from-bottom-2">
                        <h4 className="text-[10px] font-black text-muted-foreground uppercase tracking-widest px-3 py-2 mb-1 border-b border-border/50">Subtitles</h4>
                        <button type="button" onClick={() => { setActiveSubtitle(-1); setShowCC(false); }} className="flex items-center justify-between px-3 py-2 text-xs font-bold text-foreground hover:bg-muted/80 rounded-xl transition-colors">
                          Off {activeSubtitle === -1 && <Check className="h-4 w-4 text-primary" />}
                        </button>
                        {localSubtitles.map((sub, idx) => (
                          <button type="button" key={idx} onClick={() => { setActiveSubtitle(idx); setShowCC(false); }} className="flex items-center justify-between px-3 py-2 text-xs font-bold text-foreground hover:bg-muted/80 rounded-xl transition-colors">
                            {sub.label} {activeSubtitle === idx && <Check className="h-4 w-4 text-primary" />}
                          </button>
                        ))}
                      </div>
                    )}
                  </div>
                )}

                {(qualityLevels.length > 0 || !src.includes('.m3u8')) && (
                  <div className="relative">
                    <button type="button" onClick={() => handleMenuToggle('quality')} className={cn("transition-colors flex items-center gap-1", activeQuality !== -1 ? "text-primary drop-shadow-[0_0_8px_hsl(var(--primary)/0.6)]" : "text-white hover:text-white/80")} title="Settings">
                      <Settings className="h-6 w-6" />
                      {activeQuality !== -1 && qualityLevels[activeQuality] ? (
                        <span className="text-[10px] font-black bg-primary/20 text-primary px-1.5 rounded-md">
                          {qualityLevels[activeQuality]?.label || `${qualityLevels[activeQuality]?.height}p`}
                        </span>
                      ) : !src.includes('.m3u8') ? (
                        <span className="text-[10px] font-black bg-primary/20 text-primary px-1.5 rounded-md">MP4</span>
                      ) : null}
                    </button>
                    {showQuality && (
                      <div className="absolute bottom-full right-0 mb-4 w-44 bg-background/90 backdrop-blur-2xl border border-border/50 rounded-2xl shadow-2xl p-2 flex flex-col gap-1 z-50 animate-in slide-in-from-bottom-2">
                        <h4 className="text-[10px] font-bold text-muted-foreground uppercase tracking-widest px-3 py-2 mb-1 border-b border-border/50">Quality</h4>
                        
                        {src.includes('.m3u8') ? (
                            <>
                                <button type="button" onClick={() => changeQuality(-1)} className="flex items-center justify-between px-3 py-2 text-xs font-bold text-foreground hover:bg-muted/80 rounded-xl transition-colors">
                                  Auto {activeQuality === -1 && <Check className="h-4 w-4 text-primary" />}
                                </button>
                                {qualityLevels.map((level, idx) => (
                                  <button type="button" key={idx} onClick={() => changeQuality(idx)} className="flex items-center justify-between px-3 py-2 text-xs font-bold text-foreground hover:bg-muted/80 rounded-xl transition-colors">
                                    {level.label || `${level.height}p`} {activeQuality === idx && <Check className="h-4 w-4 text-primary" />}
                                  </button>
                                ))}
                            </>
                        ) : (
                            <button type="button" className="flex items-center justify-between px-3 py-2 text-xs font-bold text-foreground hover:bg-muted/80 rounded-xl transition-colors opacity-70 cursor-default">
                                Original (Processing...) <Check className="h-4 w-4 text-primary" />
                            </button>
                        )}
                      </div>
                    )}
                  </div>
                )}

                <div className="relative">
                  <button type="button" onClick={() => handleMenuToggle('speed')} className={cn("transition-colors", playbackRate !== 1 ? "text-primary drop-shadow-[0_0_8px_hsl(var(--primary)/0.6)]" : "text-white hover:text-white/80")} title="Playback Speed">
                    <Gauge className="h-6 w-6" />
                  </button>
                  {showSpeed && (
                    <div className="absolute bottom-full right-0 mb-4 w-40 bg-background/90 backdrop-blur-2xl border border-border/50 rounded-2xl shadow-2xl p-2 flex flex-col gap-1 z-50 animate-in slide-in-from-bottom-2">
                      <h4 className="text-[10px] font-black text-muted-foreground uppercase tracking-widest px-3 py-2 mb-1 border-b border-border/50">Speed</h4>
                      {[0.5, 1, 1.25, 1.5, 2].map((rate) => (
                        <button type="button" key={rate} onClick={() => changePlaybackRate(rate)} className="flex items-center justify-between px-3 py-2 text-xs font-bold text-foreground hover:bg-muted/80 rounded-xl transition-colors">
                          {rate === 1 ? 'Normal' : `${rate}x`}
                          {playbackRate === rate && <Check className="h-4 w-4 text-primary" />}
                        </button>
                      ))}
                    </div>
                  )}
                </div>

                {/* Miniplayer Toggle Button */}
                {!isMiniplayer ? (
                  <button type="button" onClick={() => onMiniplayerRequest && onMiniplayerRequest(videoRef.current?.currentTime || 0)} className="text-white hover:text-white/80 transition-colors" title="Miniplayer (i)">
                    <PictureInPicture className="h-5 w-5" />
                  </button>
                ) : null}

                <button type="button" onClick={toggleFullscreen} className="text-white hover:text-primary transition-transform hover:scale-110 ml-1" title="Fullscreen (f)">
                  {isFullscreen ? <Minimize className="h-6 w-6" /> : <Maximize className="h-6 w-6" />}
                </button>
              </div>
            </div>
          </div>
      )}
    </div>
  );
}