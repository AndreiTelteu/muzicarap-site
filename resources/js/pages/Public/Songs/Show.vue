<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import PublicFavoriteButton from '@/components/public/PublicFavoriteButton.vue';
import PublicLayout from '@/components/PublicLayout.vue';
import { usePublicPlayer } from '@/composables/usePublicPlayer';
import {
    formatDuration,
    toFavoriteSong,
    toPlayerSong,
} from '@/lib/public-catalog';
import type { PublicSongPageProps } from '@/types';

const props = defineProps<PublicSongPageProps>();

const player = usePublicPlayer();

const syncedSegments = computed(() =>
    props.lyrics.segments.filter((segment) => segment.starts_at_ms !== null),
);
const plainLyricsLines = computed(() => {
    return props.lyrics.text
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0);
});

const openPlayer = (): void => {
    if (!props.song.youtube_id) {
        return;
    }

    player.openSong(toPlayerSong(props.song, props.artist, props.lyrics), {
        autoplay: true,
        expanded: true,
    });
};
</script>

<template>
    <PublicLayout
        eyebrow="Immersive Player"
        :subtitle="`Un ecran dedicat pentru ${props.song.title}, cu player embedded in UI si versuri sincronizate.`"
        :title="props.song.title"
    >
        <div class="space-y-8">
            <section class="grid gap-5 lg:grid-cols-[0.92fr_1.08fr]">
                <div
                    class="space-y-5 rounded-[2.5rem] border border-white/10 bg-white/[0.05] p-5 shadow-[0_24px_80px_rgba(0,0,0,0.35)] backdrop-blur-xl"
                >
                    <div
                        class="overflow-hidden rounded-[2rem] bg-[radial-gradient(circle_at_top,rgba(251,191,36,0.18),transparent_38%),linear-gradient(180deg,rgba(255,255,255,0.12),rgba(255,255,255,0.04))]"
                    >
                        <div class="aspect-square">
                            <img
                                v-if="props.song.cover_url"
                                :alt="props.song.title"
                                :src="props.song.cover_url"
                                class="h-full w-full object-cover"
                            />
                        </div>
                    </div>

                    <div class="space-y-4 px-1">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p
                                    class="text-3xl font-semibold tracking-tight text-white"
                                >
                                    {{ props.song.title }}
                                </p>
                                <div
                                    class="mt-3 flex flex-wrap items-center gap-2 text-sm text-white/58"
                                >
                                    <Link
                                        :href="props.artist.url ?? '#'"
                                        class="text-white"
                                    >
                                        {{ props.artist.name }}
                                    </Link>
                                    <span v-if="props.song.album">•</span>
                                    <Link
                                        v-if="
                                            props.song.album &&
                                            props.song.album_url
                                        "
                                        :href="props.song.album_url"
                                        class="text-white/58"
                                    >
                                        {{ props.song.album }}
                                    </Link>
                                </div>
                            </div>

                            <PublicFavoriteButton
                                :song="toFavoriteSong(props.song, props.artist)"
                            />
                        </div>

                        <div
                            class="flex flex-wrap items-center gap-3 text-xs font-semibold tracking-[0.28em] text-white/38 uppercase"
                        >
                            <span>{{ props.song.parent_type }}</span>
                            <span>•</span>
                            <span>{{
                                formatDuration(props.song.duration_seconds)
                            }}</span>
                            <span v-if="props.lyrics.is_synced"
                                >• Lyrics Sync</span
                            >
                        </div>

                        <button
                            :disabled="!props.song.youtube_id"
                            class="flex w-full cursor-pointer items-center justify-center gap-3 rounded-full px-5 py-4 text-base font-semibold transition"
                            :class="
                                props.song.youtube_id
                                    ? 'bg-white text-black'
                                    : 'cursor-not-allowed bg-white/8 text-white/38'
                            "
                            type="button"
                            @click="openPlayer"
                        >
                            Ruleaza in playerul app-ului
                        </button>

                        <p
                            v-if="!props.song.youtube_id"
                            class="text-sm leading-6 text-white/45"
                        >
                            Piesa nu are inca sursa YouTube publica, deci
                            playerul nu poate fi pornit.
                        </p>
                    </div>
                </div>

                <div
                    class="rounded-[2.5rem] border border-white/10 bg-white/[0.05] p-5 backdrop-blur-xl"
                >
                    <div
                        class="mb-5 flex items-center justify-between gap-4 px-1"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.3em] text-white/38 uppercase"
                            >
                                Lyrics
                            </p>
                            <h2
                                class="mt-2 text-2xl font-semibold tracking-tight text-white"
                            >
                                Versuri
                            </h2>
                        </div>

                        <span
                            class="rounded-full bg-amber-400/10 px-3 py-1 text-[0.68rem] font-semibold tracking-[0.24em] text-amber-200 uppercase"
                        >
                            {{
                                props.lyrics.is_synced
                                    ? 'Sincronizate'
                                    : 'Text simplu'
                            }}
                        </span>
                    </div>

                    <div
                        v-if="
                            props.lyrics.is_synced && syncedSegments.length > 0
                        "
                        class="space-y-3"
                    >
                        <div
                            v-for="(segment, index) in syncedSegments"
                            :key="segment.id ?? `${segment.position}-${index}`"
                            class="rounded-[1.7rem] border border-white/8 bg-black/25 px-4 py-4 text-white/68"
                        >
                            <div class="flex items-start gap-4">
                                <span
                                    class="pt-1 text-[0.65rem] font-semibold tracking-[0.28em] text-white/30 uppercase"
                                >
                                    {{ String(index + 1).padStart(2, '0') }}
                                </span>
                                <p class="text-lg leading-8 text-white">
                                    {{ segment.text || 'Instrumental' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else-if="plainLyricsLines.length > 0"
                        class="space-y-3"
                    >
                        <p
                            v-for="(line, index) in plainLyricsLines"
                            :key="`${line}-${index}`"
                            class="rounded-[1.7rem] border border-white/8 bg-black/25 px-4 py-4 text-lg leading-8 text-white"
                        >
                            {{ line }}
                        </p>
                    </div>

                    <p
                        v-else
                        class="rounded-[1.7rem] border border-white/8 bg-black/25 px-4 py-4 text-white/52"
                    >
                        Versurile nu sunt disponibile inca pentru aceasta piesa.
                    </p>
                </div>
            </section>
        </div>
    </PublicLayout>
</template>
