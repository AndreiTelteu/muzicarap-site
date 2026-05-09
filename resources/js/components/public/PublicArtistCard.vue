<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import type { PublicArtistSummary } from '@/types';

defineProps<{
    artist: PublicArtistSummary & {
        songs_count?: number;
        albums_count?: number;
    };
}>();
</script>

<template>
    <Link
        :href="artist.url ?? '#'"
        class="group relative block overflow-hidden rounded-[2rem] border border-white/10 bg-white/[0.05] shadow-[0_24px_80px_rgba(0,0,0,0.3)]"
    >
        <div
            class="aspect-[0.95/1.15] bg-[radial-gradient(circle_at_top,rgba(255,255,255,0.18),transparent_42%),linear-gradient(180deg,rgba(255,255,255,0.12),rgba(255,255,255,0.04))]"
        >
            <img
                v-if="artist.image_url"
                :alt="artist.name"
                :src="artist.image_url"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
            />
        </div>

        <div
            class="absolute inset-x-0 bottom-0 space-y-3 bg-gradient-to-t from-black via-black/88 to-transparent px-5 pt-16 pb-5"
        >
            <div>
                <p class="text-2xl font-semibold tracking-tight text-white">
                    {{ artist.name }}
                </p>
                <p
                    v-if="artist.bio"
                    class="mt-2 line-clamp-3 text-sm leading-6 text-white/60"
                >
                    {{ artist.bio }}
                </p>
            </div>

            <div
                class="flex items-center gap-2 text-[0.7rem] font-medium tracking-[0.25em] text-white/55 uppercase"
            >
                <span v-if="artist.songs_count !== undefined"
                    >{{ artist.songs_count }} piese</span
                >
                <span
                    v-if="
                        artist.songs_count !== undefined &&
                        artist.albums_count !== undefined
                    "
                    >•</span
                >
                <span v-if="artist.albums_count !== undefined"
                    >{{ artist.albums_count }} albume</span
                >
            </div>
        </div>
    </Link>
</template>
