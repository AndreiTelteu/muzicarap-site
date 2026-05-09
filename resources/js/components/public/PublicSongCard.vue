<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import IconSymbol from '@/components/public/IconSymbol.vue';
import PublicFavoriteButton from '@/components/public/PublicFavoriteButton.vue';
import { usePublicPlayerLoader } from '@/composables/usePublicPlayerLoader';
import { formatDuration, toFavoriteSong } from '@/lib/public-catalog';
import type { PublicSongSummary } from '@/types';

const props = withDefaults(
    defineProps<{
        song: PublicSongSummary;
        priority?: 'feature' | 'default' | 'compact';
        showArtist?: boolean;
    }>(),
    {
        priority: 'default',
        showArtist: true,
    },
);

const { openFromSummary } = usePublicPlayerLoader();

const playSong = async (): Promise<void> => {
    if (!props.song.youtube_id) {
        return;
    }

    await openFromSummary(props.song);
};
</script>

<template>
    <article
        :class="[
            'overflow-hidden rounded-[2rem] border border-white/10 bg-white/[0.06] text-white shadow-[0_24px_80px_rgba(0,0,0,0.35)] backdrop-blur-xl',
            priority === 'feature' ? 'min-h-[24rem]' : '',
        ]"
    >
        <div class="relative">
            <Link :href="song.url" class="block">
                <div
                    class="aspect-[1.05/1] w-full bg-[radial-gradient(circle_at_top,rgba(251,191,36,0.28),transparent_46%),linear-gradient(180deg,rgba(255,255,255,0.12),rgba(255,255,255,0.02))]"
                >
                    <img
                        v-if="song.cover_url"
                        :alt="song.title"
                        :src="song.cover_url"
                        class="h-full w-full object-cover"
                    />

                    <div v-else class="flex h-full w-full items-end p-6">
                        <span
                            class="text-xs font-medium tracking-[0.35em] text-white/55 uppercase"
                        >
                            MuzicaRap Select
                        </span>
                    </div>
                </div>
            </Link>

            <div
                class="absolute inset-x-0 top-0 flex items-start justify-between p-4"
            >
                <span
                    class="rounded-full border border-white/10 bg-black/35 px-3 py-1 text-[0.65rem] font-semibold tracking-[0.28em] text-white/70 uppercase backdrop-blur-md"
                >
                    {{ song.parent_type }}
                </span>

                <PublicFavoriteButton
                    v-if="song.artist"
                    :song="toFavoriteSong(song, song.artist)"
                />
            </div>

            <button
                v-if="song.youtube_id"
                class="absolute right-4 bottom-4 flex h-14 w-14 cursor-pointer items-center justify-center rounded-full bg-white text-black shadow-[0_10px_30px_rgba(255,255,255,0.25)] transition hover:scale-[1.03]"
                type="button"
                @click.prevent="playSong"
            >
                <IconSymbol filled name="play" />
            </button>
        </div>

        <div class="space-y-4 p-5">
            <div class="space-y-2">
                <Link
                    :href="song.url"
                    class="block text-xl font-semibold tracking-tight text-white"
                >
                    {{ song.title }}
                </Link>

                <div
                    class="flex flex-wrap items-center gap-2 text-sm text-white/58"
                >
                    <Link
                        v-if="showArtist && song.artist?.url"
                        :href="song.artist.url"
                        class="text-white/82"
                    >
                        {{ song.artist.name }}
                    </Link>
                    <span v-if="showArtist && song.artist?.url && song.album"
                        >•</span
                    >
                    <Link
                        v-if="song.album && song.album_url"
                        :href="song.album_url"
                        class="text-white/58"
                    >
                        {{ song.album }}
                    </Link>
                    <span v-else-if="song.album">{{ song.album }}</span>
                </div>
            </div>

            <div
                class="flex items-center justify-between gap-3 text-sm text-white/48"
            >
                <span>{{ formatDuration(song.duration_seconds) }}</span>
                <span
                    v-if="song.youtube_id"
                    class="rounded-full bg-white/8 px-2.5 py-1 text-[0.68rem] font-medium tracking-[0.24em] uppercase"
                >
                    YouTube Ready
                </span>
            </div>
        </div>
    </article>
</template>
