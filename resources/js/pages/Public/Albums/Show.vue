<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import PublicLayout from '@/components/PublicLayout.vue';
import type { PublicAlbumPageProps } from '@/types';

const props = defineProps<PublicAlbumPageProps>();

const formatDuration = (seconds: number | null): string => {
    if (seconds === null) {
        return '—';
    }

    const minutes = Math.floor(seconds / 60);
    const remainder = seconds % 60;

    return `${minutes}:${remainder.toString().padStart(2, '0')}`;
};
</script>

<template>
    <PublicLayout :title="props.album.title" :subtitle="props.album.description">
        <section class="space-y-6">
            <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-400">
                <Link :href="props.artist.url" class="font-medium text-amber-400 hover:text-amber-300">
                    {{ props.artist.name }}
                </Link>
                <span>•</span>
                <span>{{ props.album.type.toUpperCase() }}</span>
                <span v-if="props.album.release_date">• {{ props.album.release_date }}</span>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <h2 class="text-xl font-semibold text-white">Tracklist</h2>
                    <span class="text-sm text-zinc-400">{{ props.tracks.length }} piese</span>
                </div>

                <div v-if="props.tracks.length > 0" class="space-y-3">
                    <Link
                        v-for="track in props.tracks"
                        :key="track.slug"
                        :href="track.url"
                        class="flex items-center justify-between gap-4 rounded-2xl border border-white/8 bg-black/20 px-4 py-3 transition hover:border-amber-400/40 hover:bg-black/30"
                    >
                        <div class="flex items-center gap-4">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-amber-400/15 text-sm font-semibold text-amber-300">
                                {{ track.track_number ?? '•' }}
                            </span>

                            <span class="font-medium text-white">{{ track.title }}</span>
                        </div>

                        <span class="text-sm text-zinc-400">{{ formatDuration(track.duration_seconds) }}</span>
                    </Link>
                </div>

                <p v-else class="text-zinc-400">Albumul nu are încă piese publice.</p>
            </div>
        </section>
    </PublicLayout>
</template>
