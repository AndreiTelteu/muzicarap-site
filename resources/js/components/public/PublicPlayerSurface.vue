<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import IconSymbol from '@/components/public/IconSymbol.vue';
import PublicFavoriteButton from '@/components/public/PublicFavoriteButton.vue';
import { usePublicPlayer } from '@/composables/usePublicPlayer';
import { formatDuration } from '@/lib/public-catalog';
import { loadYouTubeIframeApi } from '@/lib/youtube';
import type { YouTubePlayerInstance } from '@/lib/youtube';

const player = usePublicPlayer();

const host = ref<HTMLElement | null>(null);
const lyricRefs = ref<Record<string, HTMLElement | null>>({});

let instance: YouTubePlayerInstance | null = null;
let syncTimer: number | null = null;

const currentSong = computed(() => player.state.activeSong);
const segments = computed(() => currentSong.value?.lyrics.segments ?? []);
const activeSegmentKey = computed(() => {
    const currentTime = player.state.currentTimeMs;

    const currentSegment = segments.value.find((segment, index) => {
        if (segment.starts_at_ms === null) {
            return false;
        }

        const nextSegment = segments.value[index + 1];
        const nextStart = nextSegment?.starts_at_ms ?? Number.MAX_SAFE_INTEGER;
        const segmentEnd = segment.ends_at_ms ?? nextStart;

        return currentTime >= segment.starts_at_ms && currentTime < segmentEnd;
    });

    return currentSegment
        ? `${currentSegment.id ?? currentSegment.position}`
        : null;
});

const currentSecondsLabel = computed(() =>
    formatDuration(Math.floor(player.state.currentTimeMs / 1000)),
);
const durationLabel = computed(() =>
    formatDuration(Math.floor(player.state.durationMs / 1000)),
);

const scrollToActiveSegment = (): void => {
    if (!activeSegmentKey.value) {
        return;
    }

    lyricRefs.value[activeSegmentKey.value]?.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
    });
};

const bindPlayer = async (): Promise<void> => {
    if (!host.value || !currentSong.value?.youtube_id) {
        return;
    }

    const api = await loadYouTubeIframeApi();

    instance?.destroy();

    instance = new api.Player(host.value, {
        videoId: currentSong.value.youtube_id,
        playerVars: {
            autoplay: 1,
            controls: 0,
            playsinline: 1,
            rel: 0,
        },
        events: {
            onReady: (event: { target: YouTubePlayerInstance }) => {
                player.setController({
                    pause: () => event.target.pauseVideo(),
                    play: () => event.target.playVideo(),
                    seekTo: (milliseconds) =>
                        event.target.seekTo(milliseconds / 1000, true),
                    stop: () => event.target.stopVideo(),
                });
                player.setLoading(false);
                player.setDuration(event.target.getDuration() * 1000);
                event.target.playVideo();
            },
            onStateChange: (event: {
                data: number;
                target: YouTubePlayerInstance;
            }) => {
                player.setPlaying(event.data === api.PlayerState.PLAYING);

                if (event.data === api.PlayerState.PLAYING) {
                    if (syncTimer !== null) {
                        window.clearInterval(syncTimer);
                    }

                    syncTimer = window.setInterval(() => {
                        player.setCurrentTime(
                            event.target.getCurrentTime() * 1000,
                        );
                        player.setDuration(event.target.getDuration() * 1000);
                    }, 250);
                }

                if (
                    event.data === api.PlayerState.PAUSED ||
                    event.data === api.PlayerState.ENDED
                ) {
                    if (syncTimer !== null) {
                        window.clearInterval(syncTimer);
                        syncTimer = null;
                    }
                }
            },
        },
    });
};

const onSeek = (event: Event): void => {
    const target = event.target as HTMLInputElement;
    player.seekTo(Number(target.value));
};

watch(
    () => player.state.loadNonce,
    () => {
        void bindPlayer();
    },
);

watch(activeSegmentKey, () => {
    scrollToActiveSegment();
});

onMounted(() => {
    if (currentSong.value?.youtube_id) {
        void bindPlayer();
    }
});

onBeforeUnmount(() => {
    if (syncTimer !== null) {
        window.clearInterval(syncTimer);
    }

    instance?.destroy();
    player.setController(null);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-full opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-full opacity-0"
        >
            <section
                v-if="currentSong && player.state.isExpanded"
                class="fixed inset-0 z-[70] flex flex-col bg-[radial-gradient(circle_at_top,rgba(251,191,36,0.18),transparent_28%),#050505]"
            >
                <div
                    class="mx-auto flex h-full w-full max-w-3xl flex-col px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-8 sm:px-6"
                >
                    <div class="flex items-center justify-between gap-4 pb-5">
                        <button
                            class="cursor-pointer text-sm font-medium text-white/65"
                            type="button"
                            @click="player.collapse()"
                        >
                            Inchide
                        </button>

                        <button
                            class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-full border border-white/10 bg-white/[0.06] text-white/75"
                            type="button"
                            @click="player.dismiss()"
                        >
                            <IconSymbol name="close" />
                        </button>
                    </div>

                    <div
                        class="grid flex-1 gap-6 overflow-hidden lg:grid-cols-[0.95fr_1.05fr]"
                    >
                        <div
                            class="space-y-5 overflow-hidden rounded-[2.5rem] border border-white/10 bg-white/[0.05] p-4 backdrop-blur-xl"
                        >
                            <div
                                class="overflow-hidden rounded-[2rem] bg-black"
                            >
                                <div ref="host" class="aspect-video w-full" />
                            </div>

                            <div class="space-y-4 px-1">
                                <div
                                    class="flex items-start justify-between gap-4"
                                >
                                    <div>
                                        <p
                                            class="text-3xl font-semibold tracking-tight text-white"
                                        >
                                            {{ currentSong.title }}
                                        </p>
                                        <p class="mt-2 text-sm text-white/58">
                                            {{ currentSong.artist.name }}
                                            <span v-if="currentSong.album">
                                                · {{ currentSong.album }}</span
                                            >
                                        </p>
                                    </div>

                                    <PublicFavoriteButton :song="currentSong" />
                                </div>

                                <div class="space-y-3">
                                    <input
                                        :max="player.state.durationMs"
                                        :value="player.state.currentTimeMs"
                                        class="h-2 w-full cursor-pointer appearance-none rounded-full bg-white/12 accent-amber-300"
                                        min="0"
                                        type="range"
                                        @input="onSeek"
                                    />

                                    <div
                                        class="flex items-center justify-between text-xs font-medium tracking-[0.24em] text-white/42 uppercase"
                                    >
                                        <span>{{ currentSecondsLabel }}</span>
                                        <span>{{ durationLabel }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4">
                                    <button
                                        class="flex h-14 w-14 cursor-pointer items-center justify-center rounded-full bg-white text-black"
                                        type="button"
                                        @click="player.togglePlayback()"
                                    >
                                        <IconSymbol
                                            :filled="true"
                                            :name="
                                                player.state.isPlaying
                                                    ? 'pause'
                                                    : 'play'
                                            "
                                        />
                                    </button>

                                    <p class="text-sm leading-6 text-white/55">
                                        Player YouTube integrat in interfata ta,
                                        cu control custom si versuri
                                        sincronizate.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="overflow-hidden rounded-[2.5rem] border border-white/10 bg-white/[0.04] p-4 backdrop-blur-xl"
                        >
                            <div class="flex h-full flex-col overflow-hidden">
                                <div
                                    class="mb-4 flex items-center justify-between gap-4 px-1"
                                >
                                    <div>
                                        <p
                                            class="text-xs font-semibold tracking-[0.3em] text-white/42 uppercase"
                                        >
                                            Lyrics Sync
                                        </p>
                                        <h2
                                            class="mt-2 text-2xl font-semibold tracking-tight text-white"
                                        >
                                            Versuri live
                                        </h2>
                                    </div>
                                    <span
                                        class="rounded-full bg-amber-400/10 px-3 py-1 text-[0.68rem] font-semibold tracking-[0.24em] text-amber-200 uppercase"
                                    >
                                        {{
                                            currentSong.lyrics.is_synced
                                                ? 'Sincronizate'
                                                : 'Text'
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="flex-1 space-y-3 overflow-y-auto pr-1"
                                >
                                    <div
                                        v-for="(segment, index) in segments"
                                        :key="
                                            segment.id ??
                                            `${segment.position}-${index}`
                                        "
                                        :ref="
                                            (element) => {
                                                lyricRefs[
                                                    `${segment.id ?? segment.position}`
                                                ] =
                                                    element as HTMLElement | null;
                                            }
                                        "
                                        class="rounded-[1.7rem] border px-4 py-4 transition"
                                        :class="
                                            activeSegmentKey ===
                                            `${segment.id ?? segment.position}`
                                                ? 'border-amber-300/35 bg-amber-300/10 text-white'
                                                : 'border-white/8 bg-black/22 text-white/62'
                                        "
                                    >
                                        <div class="flex items-start gap-4">
                                            <span
                                                class="pt-1 text-[0.65rem] font-semibold tracking-[0.28em] text-white/30 uppercase"
                                            >
                                                {{
                                                    String(index + 1).padStart(
                                                        2,
                                                        '0',
                                                    )
                                                }}
                                            </span>
                                            <p class="text-lg leading-8">
                                                {{
                                                    segment.text ||
                                                    'Instrumental'
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <p
                                        v-if="segments.length === 0"
                                        class="rounded-[1.7rem] border border-white/8 bg-black/22 px-4 py-4 text-white/58"
                                    >
                                        Nu exista segmente sincronizate pentru
                                        piesa asta.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </Transition>

        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-24 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-24 opacity-0"
        >
            <button
                v-if="currentSong && !player.state.isExpanded"
                class="fixed inset-x-4 bottom-28 z-[55] mx-auto flex w-auto max-w-md cursor-pointer items-center gap-4 rounded-[1.75rem] border border-white/10 bg-black/82 px-4 py-3 text-left shadow-[0_16px_45px_rgba(0,0,0,0.58)] backdrop-blur-2xl"
                type="button"
                @click="player.expand()"
            >
                <div
                    class="h-14 w-14 overflow-hidden rounded-[1.25rem] bg-white/10"
                >
                    <img
                        v-if="currentSong.cover_url"
                        :alt="currentSong.title"
                        :src="currentSong.cover_url"
                        class="h-full w-full object-cover"
                    />
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-white">
                        {{ currentSong.title }}
                    </p>
                    <p class="truncate text-xs text-white/52">
                        {{ currentSong.artist.name }}
                    </p>
                    <div class="mt-2 h-1.5 rounded-full bg-white/10">
                        <div
                            class="h-full rounded-full bg-amber-300 transition-all"
                            :style="{ width: `${player.progress.value}%` }"
                        />
                    </div>
                </div>

                <button
                    class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-full bg-white text-black"
                    type="button"
                    @click.stop="player.togglePlayback()"
                >
                    <IconSymbol
                        :filled="true"
                        :name="player.state.isPlaying ? 'pause' : 'play'"
                    />
                </button>
            </button>
        </Transition>
    </Teleport>
</template>
