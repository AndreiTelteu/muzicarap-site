import { computed, reactive } from 'vue';

import type { PublicPlayerSong } from '@/types';

type PlayerController = {
    pause: () => void;
    play: () => void;
    seekTo: (milliseconds: number) => void;
    stop: () => void;
};

const state = reactive({
    activeSong: null as PublicPlayerSong | null,
    currentTimeMs: 0,
    durationMs: 0,
    isExpanded: false,
    isLoading: false,
    isPlaying: false,
    loadNonce: 0,
});

let controller: PlayerController | null = null;

export const usePublicPlayer = () => {
    const hasActiveSong = computed(() => state.activeSong !== null);
    const progress = computed(() => {
        if (state.durationMs <= 0) {
            return 0;
        }

        return Math.min(100, (state.currentTimeMs / state.durationMs) * 100);
    });

    const openSong = (
        song: PublicPlayerSong,
        options: { autoplay?: boolean; expanded?: boolean } = {},
    ): void => {
        state.activeSong = song;
        state.currentTimeMs = 0;
        state.durationMs = (song.duration_seconds ?? 0) * 1000;
        state.isExpanded = options.expanded ?? true;
        state.isLoading = true;
        state.isPlaying = options.autoplay ?? true;
        state.loadNonce += 1;
    };

    const collapse = (): void => {
        state.isExpanded = false;
    };

    const expand = (): void => {
        if (state.activeSong === null) {
            return;
        }

        state.isExpanded = true;
    };

    const dismiss = (): void => {
        controller?.stop();
        state.activeSong = null;
        state.currentTimeMs = 0;
        state.durationMs = 0;
        state.isExpanded = false;
        state.isLoading = false;
        state.isPlaying = false;
    };

    const setController = (nextController: PlayerController | null): void => {
        controller = nextController;
    };

    const setCurrentTime = (milliseconds: number): void => {
        state.currentTimeMs = Math.max(0, milliseconds);
    };

    const setDuration = (milliseconds: number): void => {
        state.durationMs = Math.max(0, milliseconds);
    };

    const setLoading = (value: boolean): void => {
        state.isLoading = value;
    };

    const setPlaying = (value: boolean): void => {
        state.isPlaying = value;
    };

    const togglePlayback = (): void => {
        if (state.activeSong === null) {
            return;
        }

        if (state.isPlaying) {
            controller?.pause();

            return;
        }

        controller?.play();
    };

    const seekTo = (milliseconds: number): void => {
        if (state.activeSong === null) {
            return;
        }

        controller?.seekTo(milliseconds);
        setCurrentTime(milliseconds);
    };

    return {
        state,
        hasActiveSong,
        progress,
        openSong,
        collapse,
        expand,
        dismiss,
        setController,
        setCurrentTime,
        setDuration,
        setLoading,
        setPlaying,
        togglePlayback,
        seekTo,
    };
};
