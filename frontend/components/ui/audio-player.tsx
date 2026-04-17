"use client";

import React, { useEffect, useMemo, useRef, useState } from "react";
import {
  Check,
  Download,
  FolderPlus,
  GripHorizontal,
  Heart,
  ListMusic,
  Loader2,
  Maximize2,
  MoreHorizontal,
  Music,
  Pause,
  Play,
  Plus,
  Repeat,
  Shuffle,
  SkipBack,
  SkipForward,
  Trash2,
  Volume2,
  VolumeX,
  X,
} from "lucide-react";

import { cn } from "@/lib/utils";
import { Track, useGlobalAudio } from "@/context/global-audio-context";

interface AudioPlayerProps {
  id?: string | number;
  src?: string;
  title?: string;
  artist?: string;
  coverArt?: string;
  className?: string;
  autoPlay?: boolean;
  variant?: "default" | "mini";
  onToggleMinimize?: () => void;
  onClose?: () => void;
  dragProps?: React.HTMLAttributes<HTMLDivElement>;
  onNext?: () => void;
  onPrevious?: () => void;
  isFavorite?: boolean;
  trackList?: Track[];
  downloadUrl?: string;
}

const formatTime = (time: number) => {
  if (!Number.isFinite(time) || time < 0) {
    return "0:00";
  }

  const minutes = Math.floor(time / 60);
  const seconds = Math.floor(time % 60);
  return `${minutes}:${seconds < 10 ? "0" : ""}${seconds}`;
};

const sameTrack = (left: Track | null | undefined, right: Track | null | undefined) =>
  Boolean(left && right && String(left.id) === String(right.id) && left.src === right.src);

export function AudioPlayer({
  id: propId,
  src: propSrc,
  title: propTitle,
  artist: propArtist,
  coverArt: propCoverArt,
  className,
  autoPlay = false,
  variant = "default",
  onToggleMinimize,
  onClose,
  dragProps,
  onNext: propNext,
  onPrevious: propPrevious,
  isFavorite: propIsFavorite,
  trackList,
  downloadUrl,
}: AudioPlayerProps) {
  const {
    currentTrack,
    isPlaying,
    playTrack,
    playNext,
    playPrevious,
    togglePlay,
    isShuffle,
    toggleShuffle,
    repeatMode,
    setRepeatMode,
    volume,
    setVolume,
    isMuted,
    toggleMute,
    queue,
    removeFromQueue,
    clearQueue,
    addToQueue,
    playbackRate,
    setPlaybackRate,
    toggleFavorite,
    addToPlaylist,
    removeFromPlaylist,
    playlists,
    createPlaylist,
    deletePlaylist,
    currentTime,
    duration,
    progress,
    seekToPercent,
    seekBy,
    isBuffering,
    hasError,
    downloadCurrentTrack,
    downloadTrack,
  } = useGlobalAudio();

  const externalTrack = useMemo<Track | null>(() => {
    if (!propSrc || propId === undefined) {
      return null;
    }

    return {
      id: propId,
      src: propSrc,
      title: propTitle || "Unknown Track",
      artist: propArtist || "HIVE.OS Audio",
      coverArt: propCoverArt || null,
      isFavorite: Boolean(propIsFavorite),
      downloadUrl,
    };
  }, [downloadUrl, propArtist, propCoverArt, propId, propIsFavorite, propSrc, propTitle]);

  const resolvedTrack = externalTrack || currentTrack;
  const resolvedPlaylist = useMemo(
    () => (trackList?.length ? trackList : externalTrack ? [externalTrack] : undefined),
    [externalTrack, trackList],
  );
  const isExternalMode = Boolean(externalTrack);
  const isCurrentResolvedTrack = sameTrack(currentTrack, externalTrack || currentTrack);
  const isTrackPlaying = isExternalMode ? Boolean(isCurrentResolvedTrack && isPlaying) : isPlaying;
  const effectiveProgress = isCurrentResolvedTrack ? progress : 0;
  const effectiveCurrentTime = isCurrentResolvedTrack ? currentTime : 0;
  const effectiveDuration = isCurrentResolvedTrack ? duration : 0;
  const effectiveBuffering = isCurrentResolvedTrack ? isBuffering : false;
  const effectiveError = isCurrentResolvedTrack ? hasError : false;

  const numericTrackId =
    resolvedTrack && Number.isFinite(Number(resolvedTrack.id))
      ? Number(resolvedTrack.id)
      : null;
  const isInAnyPlaylist = useMemo(
    () =>
      numericTrackId !== null &&
      playlists.some((playlist) => playlist.trackIds.includes(numericTrackId)),
    [numericTrackId, playlists],
  );

  const [showQueue, setShowQueue] = useState(false);
  const [showPlaylists, setShowPlaylists] = useState(false);
  const [showMenu, setShowMenu] = useState(false);
  const [newPlaylistName, setNewPlaylistName] = useState("");
  const [localFavorite, setLocalFavorite] = useState(Boolean(resolvedTrack?.isFavorite));
  const previousExternalTrackRef = useRef<Track | null>(externalTrack);

  useEffect(() => {
    setLocalFavorite(Boolean(resolvedTrack?.isFavorite ?? propIsFavorite));
  }, [propIsFavorite, resolvedTrack?.id, resolvedTrack?.isFavorite]);

  useEffect(() => {
    if (!externalTrack) {
      previousExternalTrackRef.current = null;
      return;
    }

    const previousExternalTrack = previousExternalTrackRef.current;
    const wasDrivingPlayback = sameTrack(previousExternalTrack, currentTrack) && isPlaying;

    if (autoPlay && !currentTrack) {
      playTrack(externalTrack, resolvedPlaylist);
    } else if (
      previousExternalTrack &&
      !sameTrack(previousExternalTrack, externalTrack) &&
      wasDrivingPlayback
    ) {
      playTrack(externalTrack, resolvedPlaylist);
    }

    previousExternalTrackRef.current = externalTrack;
  }, [autoPlay, currentTrack, externalTrack, isPlaying, playTrack, resolvedPlaylist]);

  const activeFavorite = resolvedTrack
    ? isCurrentResolvedTrack && currentTrack
      ? Boolean(currentTrack.isFavorite)
      : localFavorite
    : false;

  const cycleRepeatMode = () => {
    const modes: Array<"none" | "playlist" | "track"> = ["none", "playlist", "track"];
    const nextMode = modes[(modes.indexOf(repeatMode) + 1) % modes.length];
    setRepeatMode(nextMode);
  };

  const cyclePlaybackRate = () => {
    const rates = [1, 1.25, 1.5, 1.75, 2];
    const nextRate = rates[(rates.indexOf(playbackRate) + 1) % rates.length];
    setPlaybackRate(nextRate);
  };

  const handlePlayPause = () => {
    if (!resolvedTrack) {
      return;
    }

    if (isExternalMode && !isCurrentResolvedTrack) {
      playTrack(resolvedTrack, resolvedPlaylist);
      return;
    }

    togglePlay();
  };

  const handlePrevious = () => {
    if (propPrevious) {
      propPrevious();
      return;
    }

    playPrevious();
  };

  const handleNext = () => {
    if (propNext) {
      propNext();
      return;
    }

    playNext();
  };

  const handleFavorite = async () => {
    if (!resolvedTrack) {
      return;
    }

    const nextFavorite = !activeFavorite;
    setLocalFavorite(nextFavorite);
    await toggleFavorite(resolvedTrack.id, activeFavorite);
  };

  const handleDownload = async () => {
    if (!resolvedTrack) {
      return;
    }

    if (isCurrentResolvedTrack) {
      await downloadCurrentTrack();
      return;
    }

    await downloadTrack(resolvedTrack);
  };

  const handleAddToQueue = () => {
    if (!resolvedTrack) {
      return;
    }

    addToQueue(resolvedTrack);
    setShowMenu(false);
  };

  const handleQueuePick = (track: Track) => {
    playTrack(track);
    setShowQueue(false);
  };

  const handlePlaylistToggle = async (playlistId: number, included: boolean) => {
    if (numericTrackId === null) {
      return;
    }

    if (included) {
      await removeFromPlaylist(numericTrackId, playlistId);
    } else {
      await addToPlaylist(numericTrackId, playlistId);
    }
  };

  const handleCreatePlaylist = async () => {
    if (!newPlaylistName.trim()) {
      return;
    }

    await createPlaylist(newPlaylistName);
    setNewPlaylistName("");
  };

  if (!resolvedTrack?.src) {
    return null;
  }

  const queueBadgeLabel =
    queue.length > 0 ? `${queue.length} in queue` : "Queue is ready for your next tracks";
  const canManipulateTimeline = !isExternalMode || isCurrentResolvedTrack;

  return (
    <div
      className={cn(
        "relative overflow-hidden border border-white/10 bg-card/85 text-foreground shadow-2xl backdrop-blur-3xl transition-all",
        variant === "default"
          ? "w-full max-w-md rounded-[2.4rem] p-6"
          : "flex w-[340px] items-center gap-2 rounded-full p-2",
        className,
      )}
    >
      {resolvedTrack.coverArt ? (
        <div
          className="pointer-events-none absolute inset-0 z-0 scale-150 opacity-25 blur-[100px]"
          style={{
            backgroundImage: `url(${resolvedTrack.coverArt})`,
            backgroundPosition: "center",
            backgroundSize: "cover",
          }}
        />
      ) : null}

      {variant === "mini" ? (
        <div className="relative z-10 flex w-full items-center gap-3">
          {dragProps ? (
            <div
              data-audio-drag-handle
              {...dragProps}
              className={cn(
                "flex h-9 w-9 shrink-0 cursor-grab items-center justify-center rounded-full bg-white/5 text-muted-foreground active:cursor-grabbing",
                dragProps.className,
              )}
            >
              <GripHorizontal className="h-4 w-4" />
            </div>
          ) : null}

          <div className="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-white/10">
            {resolvedTrack.coverArt ? (
              <img
                src={resolvedTrack.coverArt}
                alt={resolvedTrack.title}
                className={cn(
                  "h-full w-full object-cover transition-transform duration-500",
                  isTrackPlaying && "scale-110",
                )}
              />
            ) : (
              <Music className="h-4 w-4 text-emerald-500" />
            )}
            {effectiveBuffering ? (
              <div className="absolute inset-0 flex items-center justify-center bg-black/45 backdrop-blur-sm">
                <Loader2 className="h-4 w-4 animate-spin text-white" />
              </div>
            ) : null}
          </div>

          <div className="min-w-0 flex-1">
            <p className="truncate text-xs font-black leading-tight">{resolvedTrack.title}</p>
            <p className="truncate text-[10px] font-bold uppercase tracking-tight text-muted-foreground/70">
              {resolvedTrack.artist || "HIVE.OS Audio"}
            </p>
          </div>

          <div className="flex items-center gap-1.5">
            <button
              onClick={handlePrevious}
              className="rounded-full p-1 text-muted-foreground transition-colors hover:text-foreground"
              title="Previous"
            >
              <SkipBack className="h-3.5 w-3.5 fill-current" />
            </button>
            <button
              onClick={handlePlayPause}
              className="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-500 text-emerald-950 shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-400 active:scale-90"
              title={isTrackPlaying ? "Pause" : "Play"}
            >
              {isTrackPlaying ? (
                <Pause className="h-4 w-4 fill-current" />
              ) : (
                <Play className="ml-0.5 h-4 w-4 fill-current" />
              )}
            </button>
            <button
              onClick={handleNext}
              className="rounded-full p-1 text-muted-foreground transition-colors hover:text-foreground"
              title="Next"
            >
              <SkipForward className="h-3.5 w-3.5 fill-current" />
            </button>
            <button
              onClick={() => void handleFavorite()}
              className={cn(
                "rounded-full p-1 transition-colors",
                activeFavorite ? "text-emerald-500" : "text-muted-foreground hover:text-emerald-500",
              )}
              title={activeFavorite ? "Unfavorite" : "Favorite"}
            >
              <Heart className={cn("h-3.5 w-3.5", activeFavorite && "fill-current")} />
            </button>
          </div>

          <div className="flex items-center gap-1 border-l border-white/10 pl-2">
            {onToggleMinimize ? (
              <button
                onClick={onToggleMinimize}
                className="rounded-full p-1 text-muted-foreground transition-colors hover:text-foreground"
                title="Expand player"
              >
                <Maximize2 className="h-3.5 w-3.5" />
              </button>
            ) : null}
            {onClose ? (
              <button
                onClick={onClose}
                className="rounded-full p-1 text-muted-foreground transition-colors hover:text-destructive"
                title="Close player"
              >
                <X className="h-4 w-4" />
              </button>
            ) : null}
          </div>
        </div>
      ) : (
        <div className="relative z-10 flex flex-col gap-6">
          <div className="flex items-start justify-between gap-3">
            <div className="flex items-center gap-2">
              {dragProps ? (
                <div
                  data-audio-drag-handle
                  {...dragProps}
                  className={cn(
                    "flex h-9 w-9 cursor-grab items-center justify-center rounded-2xl bg-white/5 text-muted-foreground active:cursor-grabbing",
                    dragProps.className,
                  )}
                  title="Move player"
                >
                  <GripHorizontal className="h-4 w-4" />
                </div>
              ) : null}
              <div>
                <div className="flex items-center gap-2">
                  <div className="h-2 w-2 rounded-full bg-emerald-500" />
                  <span className="text-[10px] font-black uppercase tracking-[0.24em] text-emerald-500/80">
                    Premium Audio
                  </span>
                </div>
                <p className="mt-1 text-[11px] font-bold uppercase tracking-[0.16em] text-muted-foreground/70">
                  {queueBadgeLabel}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <button
                onClick={() => setShowQueue((previous) => !previous)}
                className={cn(
                  "relative rounded-2xl p-2 transition-all",
                  showQueue
                    ? "bg-emerald-500/15 text-emerald-500"
                    : "text-muted-foreground hover:bg-white/5 hover:text-foreground",
                )}
                title="Queue"
              >
                <ListMusic className="h-5 w-5" />
                {queue.length > 0 ? (
                  <span className="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-emerald-500 px-1 text-[10px] font-black text-emerald-950">
                    {queue.length}
                  </span>
                ) : null}
              </button>
              {onToggleMinimize ? (
                <button
                  onClick={onToggleMinimize}
                  className="rounded-2xl p-2 text-muted-foreground transition-all hover:bg-white/5 hover:text-foreground"
                  title="Minimize"
                >
                  <Maximize2 className="h-4 w-4 rotate-180" />
                </button>
              ) : null}
              {onClose ? (
                <button
                  onClick={onClose}
                  className="rounded-2xl p-2 text-muted-foreground transition-all hover:bg-destructive hover:text-white"
                  title="Close"
                >
                  <X className="h-5 w-5" />
                </button>
              ) : null}
            </div>
          </div>

          <div className="flex items-center gap-5">
            <div className="relative h-28 w-28 shrink-0 overflow-hidden rounded-[2rem] border border-white/10 shadow-2xl">
              {resolvedTrack.coverArt ? (
                <img
                  src={resolvedTrack.coverArt}
                  alt={resolvedTrack.title}
                  className={cn(
                    "h-full w-full object-cover transition-transform duration-700",
                    isTrackPlaying ? "scale-105" : "scale-110 grayscale-[0.15]",
                  )}
                />
              ) : (
                <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-emerald-500/20 to-sky-500/20">
                  <Music className="h-10 w-10 text-emerald-500" />
                </div>
              )}
              {effectiveBuffering ? (
                <div className="absolute inset-0 flex items-center justify-center bg-black/55 backdrop-blur-sm">
                  <Loader2 className="h-6 w-6 animate-spin text-emerald-500" />
                </div>
              ) : null}
            </div>

            <div className="min-w-0 flex-1">
              <h2 className="truncate text-xl font-black tracking-tight" title={resolvedTrack.title}>
                {resolvedTrack.title}
              </h2>
              <p className="truncate text-sm font-medium text-muted-foreground">
                {resolvedTrack.artist || "HIVE.OS Audio"}
              </p>

              <div className="mt-4 flex items-center gap-2">
                <button
                  onClick={toggleShuffle}
                  className={cn(
                    "rounded-xl p-1.5 transition-all",
                    isShuffle
                      ? "bg-emerald-500/10 text-emerald-500"
                      : "text-muted-foreground hover:bg-white/5 hover:text-foreground",
                  )}
                  title="Shuffle"
                >
                  <Shuffle className="h-4 w-4" />
                </button>
                <button
                  onClick={cycleRepeatMode}
                  className={cn(
                    "relative rounded-xl p-1.5 transition-all",
                    repeatMode !== "none"
                      ? "bg-emerald-500/10 text-emerald-500"
                      : "text-muted-foreground hover:bg-white/5 hover:text-foreground",
                  )}
                  title="Repeat"
                >
                  <Repeat className="h-4 w-4" />
                  {repeatMode === "track" ? (
                    <span className="absolute -right-1 -top-1 rounded-full bg-emerald-500 px-1 text-[8px] font-black text-emerald-950">
                      1
                    </span>
                  ) : null}
                </button>
                <button
                  onClick={cyclePlaybackRate}
                  className="rounded-xl bg-white/5 px-2 py-1 text-[10px] font-black text-muted-foreground transition-all hover:bg-white/10 hover:text-foreground"
                  title="Playback speed"
                >
                  {playbackRate}x
                </button>
                <button
                  onClick={() => seekBy(-10)}
                  disabled={!canManipulateTimeline}
                  className="rounded-xl p-1.5 text-muted-foreground transition-all hover:bg-white/5 hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                  title="Back 10 seconds"
                >
                  <SkipBack className="h-4 w-4" />
                </button>
                <button
                  onClick={() => seekBy(10)}
                  disabled={!canManipulateTimeline}
                  className="rounded-xl p-1.5 text-muted-foreground transition-all hover:bg-white/5 hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                  title="Forward 10 seconds"
                >
                  <SkipForward className="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>

          <div className="space-y-2">
            <input
              type="range"
              min="0"
              max="100"
              step="0.1"
              value={effectiveProgress}
              disabled={!canManipulateTimeline}
              onChange={(event) => seekToPercent(Number(event.target.value))}
              className="h-1.5 w-full cursor-pointer appearance-none rounded-full bg-white/10 accent-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
            />
            <div className="flex items-center justify-between px-1 text-[10px] font-black uppercase tracking-[0.18em] text-muted-foreground/70">
              <span>{formatTime(effectiveCurrentTime)}</span>
              <span>{formatTime(effectiveDuration)}</span>
            </div>
          </div>

          <div className="flex items-center justify-between gap-4">
            <div className="flex w-24 items-center gap-2">
              <button
                onClick={toggleMute}
                className="text-muted-foreground transition-colors hover:text-foreground"
                title={isMuted ? "Unmute" : "Mute"}
              >
                {isMuted || volume === 0 ? (
                  <VolumeX className="h-5 w-5" />
                ) : (
                  <Volume2 className="h-5 w-5" />
                )}
              </button>
              <input
                type="range"
                min="0"
                max="1"
                step="0.01"
                value={isMuted ? 0 : volume}
                onChange={(event) => setVolume(Number(event.target.value))}
                className="h-1 w-16 cursor-pointer appearance-none rounded-full bg-white/10 accent-emerald-500"
              />
            </div>

            <div className="flex items-center gap-8">
              <button
                onClick={handlePrevious}
                className="text-white/60 transition-all hover:scale-110 hover:text-emerald-500 active:scale-90"
                title="Previous"
              >
                <SkipBack className="h-6 w-6 fill-current" />
              </button>
              <button
                onClick={handlePlayPause}
                className="group flex h-16 w-16 items-center justify-center rounded-[2rem] bg-emerald-500 text-emerald-950 shadow-[0_0_40px_rgba(16,185,129,0.3)] transition-all hover:scale-105 hover:bg-emerald-400 active:scale-95"
                title={isTrackPlaying ? "Pause" : "Play"}
              >
                {isTrackPlaying ? (
                  <Pause className="h-7 w-7 fill-current transition-transform group-hover:scale-110" />
                ) : (
                  <Play className="ml-1 h-7 w-7 fill-current transition-transform group-hover:scale-110" />
                )}
              </button>
              <button
                onClick={handleNext}
                className="text-white/60 transition-all hover:scale-110 hover:text-emerald-500 active:scale-90"
                title="Next"
              >
                <SkipForward className="h-6 w-6 fill-current" />
              </button>
            </div>

            <div className="flex w-32 items-center justify-end gap-1">
              <button
                onClick={() => void handleFavorite()}
                className={cn(
                  "rounded-xl p-2 transition-all",
                  activeFavorite
                    ? "scale-110 text-rose-500 drop-shadow-[0_0_8px_rgba(244,63,94,0.45)]"
                    : "text-muted-foreground hover:bg-white/5 hover:text-foreground",
                )}
                title={activeFavorite ? "Unfavorite" : "Favorite"}
              >
                <Heart className={cn("h-5 w-5", activeFavorite && "fill-current")} />
              </button>
              <button
                onClick={() => setShowPlaylists((previous) => !previous)}
                className={cn(
                  "rounded-xl p-2 transition-all",
                  showPlaylists
                    ? "bg-emerald-500/15 text-emerald-500"
                    : "text-muted-foreground hover:bg-white/5 hover:text-foreground",
                )}
                title="Playlists"
              >
                <Plus className="h-5 w-5" />
              </button>
              <button
                onClick={() => setShowMenu((previous) => !previous)}
                className={cn(
                  "rounded-xl p-2 transition-all",
                  showMenu
                    ? "bg-white/10 text-foreground"
                    : "text-muted-foreground hover:bg-white/5 hover:text-foreground",
                )}
                title="Track options"
              >
                <MoreHorizontal className="h-5 w-5" />
              </button>
            </div>
          </div>
        </div>
      )}

      {effectiveError ? (
        <div className="absolute inset-x-4 bottom-4 z-20 rounded-2xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-xs font-bold text-destructive">
          This track could not be played. Try another file or re-upload the audio.
        </div>
      ) : null}

      {showMenu ? (
        <div className="absolute inset-0 z-30 flex flex-col justify-center bg-black/95 p-6 backdrop-blur-2xl animate-in fade-in zoom-in duration-300">
          <div className="mb-8 flex items-center justify-between">
            <h3 className="text-sm font-black uppercase tracking-[0.2em] text-white/50">
              Track Options
            </h3>
            <button
              onClick={() => setShowMenu(false)}
              className="rounded-full p-1 text-muted-foreground transition-colors hover:bg-white/10 hover:text-white"
            >
              <X className="h-4 w-4" />
            </button>
          </div>

          <div className="grid gap-3">
            <button
              onClick={() => {
                setShowMenu(false);
                setShowPlaylists(true);
              }}
              className="group flex items-center gap-4 rounded-3xl border border-white/5 bg-white/5 p-5 transition-all hover:border-emerald-500/20 hover:bg-emerald-500/10"
            >
              <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-500/20 text-emerald-500 transition-transform group-hover:scale-110">
                <FolderPlus className="h-5 w-5" />
              </div>
              <div className="flex flex-col items-start">
                <span className="text-sm font-bold text-foreground">
                  {isInAnyPlaylist ? "Manage Playlists" : "Add to Playlist"}
                </span>
                <span className="text-[10px] font-black uppercase tracking-[0.18em] text-muted-foreground">
                  Stable library organization
                </span>
              </div>
            </button>

            <button
              onClick={handleAddToQueue}
              className="group flex items-center gap-4 rounded-3xl border border-white/5 bg-white/5 p-5 transition-all hover:border-sky-500/20 hover:bg-sky-500/10"
            >
              <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-sky-500/20 text-sky-400 transition-transform group-hover:scale-110">
                <ListMusic className="h-5 w-5" />
              </div>
              <div className="flex flex-col items-start">
                <span className="text-sm font-bold text-foreground">Add to Queue</span>
                <span className="text-[10px] font-black uppercase tracking-[0.18em] text-muted-foreground">
                  Keep the flow going
                </span>
              </div>
            </button>

            <button
              onClick={() => {
                void handleDownload();
                setShowMenu(false);
              }}
              className="group flex items-center gap-4 rounded-3xl border border-white/5 bg-white/5 p-5 transition-all hover:border-blue-500/20 hover:bg-blue-500/10"
            >
              <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-500/20 text-blue-400 transition-transform group-hover:scale-110">
                <Download className="h-5 w-5" />
              </div>
              <div className="flex flex-col items-start">
                <span className="text-sm font-bold text-foreground">Download Track</span>
                <span className="text-[10px] font-black uppercase tracking-[0.18em] text-muted-foreground">
                  Offline listening
                </span>
              </div>
            </button>
          </div>
        </div>
      ) : null}

      {showPlaylists ? (
        <div className="absolute inset-0 z-30 flex flex-col bg-black/95 p-6 backdrop-blur-2xl animate-in fade-in slide-in-from-right-10 duration-300">
          <div className="mb-6 flex items-center justify-between">
            <h3 className="text-sm font-black uppercase tracking-[0.2em] text-emerald-500">
              Playlists
            </h3>
            <button
              onClick={() => setShowPlaylists(false)}
              className="rounded-full p-1 text-muted-foreground transition-colors hover:bg-white/10 hover:text-white"
            >
              <X className="h-4 w-4" />
            </button>
          </div>

          <div className="custom-scrollbar flex-1 space-y-3 overflow-y-auto pr-2">
            {playlists.length === 0 ? (
              <div className="flex h-32 flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-white/10 text-muted-foreground">
                <FolderPlus className="h-7 w-7" />
                <p className="text-[10px] font-bold uppercase tracking-[0.16em]">
                  No playlists yet
                </p>
              </div>
            ) : (
              playlists.map((playlistItem) => {
                const included =
                  numericTrackId !== null &&
                  playlistItem.trackIds.includes(numericTrackId);

                return (
                  <div key={playlistItem.id} className="flex items-center gap-2">
                    <button
                      onClick={() => void handlePlaylistToggle(playlistItem.id, included)}
                      className={cn(
                        "flex flex-1 items-center justify-between rounded-2xl border p-4 transition-all",
                        included
                          ? "border-emerald-500/30 bg-emerald-500/20 text-emerald-500"
                          : "border-white/5 bg-white/5 hover:border-emerald-500/20 hover:bg-emerald-500/10",
                      )}
                    >
                      <span className="text-sm font-bold text-left text-foreground">
                        {playlistItem.name}
                      </span>
                      {included ? (
                        <Check className="h-4 w-4 shrink-0" />
                      ) : (
                        <Plus className="h-4 w-4 shrink-0 text-muted-foreground" />
                      )}
                    </button>
                    <button
                      onClick={() => void deletePlaylist(playlistItem.id)}
                      className="rounded-2xl border border-white/5 bg-white/5 p-4 text-muted-foreground transition-all hover:border-rose-500/20 hover:bg-rose-500/10 hover:text-rose-500"
                      title="Delete playlist"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                );
              })
            )}
          </div>

          <div className="mt-6 border-t border-white/10 pt-5">
            <div className="flex gap-2">
              <input
                value={newPlaylistName}
                onChange={(event) => setNewPlaylistName(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === "Enter") {
                    void handleCreatePlaylist();
                  }
                }}
                placeholder="New playlist name..."
                className="flex-1 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm outline-none transition-all focus:border-emerald-500/40"
              />
              <button
                onClick={() => void handleCreatePlaylist()}
                disabled={!newPlaylistName.trim()}
                className="rounded-xl bg-emerald-500 p-2 text-emerald-950 transition-all hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <Plus className="h-5 w-5" />
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {showQueue ? (
        <div
          className={cn(
            "custom-scrollbar absolute inset-0 z-30 flex flex-col bg-black/85 p-6 backdrop-blur-2xl animate-in fade-in zoom-in duration-300",
            variant === "mini" &&
              "fixed inset-auto bottom-20 left-1/2 max-h-[420px] w-[360px] -translate-x-1/2 rounded-[2rem] border border-white/10 shadow-2xl",
          )}
        >
          <div className="mb-4 flex items-center justify-between">
            <div>
              <h3 className="text-sm font-black uppercase tracking-[0.2em] text-emerald-500">
                Up Next
              </h3>
              <p className="mt-1 text-[10px] font-black uppercase tracking-[0.16em] text-muted-foreground">
                {queueBadgeLabel}
              </p>
            </div>
            <div className="flex items-center gap-2">
              {queue.length > 0 ? (
                <button
                  onClick={clearQueue}
                  className="rounded-xl px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-muted-foreground transition-colors hover:bg-white/10 hover:text-foreground"
                >
                  Clear
                </button>
              ) : null}
              <button
                onClick={() => setShowQueue(false)}
                className="rounded-full p-1 text-muted-foreground transition-colors hover:bg-white/10 hover:text-white"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
          </div>

          <div className="flex-1 space-y-2 overflow-y-auto">
            {queue.length === 0 ? (
              <div className="flex h-full flex-col items-center justify-center gap-3 text-muted-foreground">
                <Music className="h-8 w-8" />
                <p className="text-xs font-bold uppercase tracking-[0.16em]">
                  Queue is empty
                </p>
              </div>
            ) : (
              queue.map((trackItem, index) => (
                <div
                  key={`${trackItem.id}-${index}`}
                  className="group flex items-center gap-3 rounded-2xl border border-transparent p-2 transition-all hover:border-white/10 hover:bg-white/5"
                >
                  <button
                    onClick={() => handleQueuePick(trackItem)}
                    className="flex min-w-0 flex-1 items-center gap-3 text-left"
                  >
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-white/10 bg-muted">
                      {trackItem.coverArt ? (
                        <img
                          src={trackItem.coverArt}
                          alt={trackItem.title}
                          className="h-full w-full object-cover"
                        />
                      ) : (
                        <Music className="h-4 w-4 text-emerald-500" />
                      )}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-bold text-foreground">
                        {trackItem.title}
                      </p>
                      <p className="truncate text-[10px] font-black uppercase tracking-[0.16em] text-muted-foreground">
                        {trackItem.artist || "HIVE.OS Audio"}
                      </p>
                    </div>
                  </button>
                  <button
                    onClick={() => removeFromQueue(trackItem.id)}
                    className="rounded-full p-1 text-muted-foreground opacity-0 transition-all hover:bg-rose-500/10 hover:text-rose-500 group-hover:opacity-100"
                    title="Remove from queue"
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                </div>
              ))
            )}
          </div>
        </div>
      ) : null}

      <style jsx>{`
        .custom-scrollbar::-webkit-scrollbar {
          width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
          background: rgba(255, 255, 255, 0.12);
          border-radius: 999px;
        }
      `}</style>
    </div>
  );
}
