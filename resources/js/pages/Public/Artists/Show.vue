<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import PublicLayout from '@/components/PublicLayout.vue';
import type { PublicArtistPageProps } from '@/types';

const props = defineProps<PublicArtistPageProps>();

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
    <PublicLayout :title="props.artist.name" :subtitle="props.artist.bio">
        <section class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-4 rounded-3xl border border-white/10 bg-white/5 p-6">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-xl font-semibold text-white">Piese publice</h2>
                    <span class="text-sm text-zinc-400">{{ props.songs.length }} rezultate</span>
                </div>

                <div v-if="props.songs.length > 0" class="space-y-3">
                    <Link
                        v-for="song in props.songs"
                        :key="song.slug"
                        :href="song.url"
                        class="flex items-center justify-between gap-4 rounded-2xl border border-white/8 bg-black/20 px-4 py-3 transition hover:border-amber-400/40 hover:bg-black/30"
                    >
                        <div>
                            <p class="font-medium text-white">{{ song.title }}</p>
                            <p class="text-sm text-zinc-400">
                                {{ song.album ?? 'Single' }} · {{ song.parent_type }}
                            </p>
                        </div>

                        <span class="text-sm text-zinc-400">{{ formatDuration(song.duration_seconds) }}</span>
                    </Link>
                </div>

                <p v-else class="text-zinc-400">Nu există piese publice pentru acest artist.</p>
            </div>

            <div class="space-y-4 rounded-3xl border border-white/10 bg-white/5 p-6">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-xl font-semibold text-white">Albume publice</h2>
                    <span class="text-sm text-zinc-400">{{ props.albums.length }} rezultate</span>
                </div>

                <div v-if="props.albums.length > 0" class="space-y-3">
                    <Link
                        v-for="album in props.albums"
                        :key="album.slug"
                        :href="album.url"
                        class="block rounded-2xl border border-white/8 bg-black/20 px-4 py-3 transition hover:border-amber-400/40 hover:bg-black/30"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="font-medium text-white">{{ album.title }}</p>
                                <p class="text-sm text-zinc-400">
                                    {{ album.type.toUpperCase() }}
                                    <span v-if="album.release_date"> · {{ album.release_date }}</span>
                                </p>
                            </div>

                            <span class="text-sm text-zinc-400">{{ album.songs_count }} piese</span>
                        </div>
                    </Link>
                </div>

                <p v-else class="text-zinc-400">Nu există albume publice pentru acest artist.</p>
            </div>
        </section>
    </PublicLayout>
</template>
