<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import PublicLayout from '@/components/PublicLayout.vue';
import type { PublicSongPageProps } from '@/types';

const props = defineProps<PublicSongPageProps>();

const formatDuration = (seconds: number | null): string => {
    if (seconds === null) {
        return 'Durată necunoscută';
    }

    const minutes = Math.floor(seconds / 60);
    const remainder = seconds % 60;

    return `${minutes}:${remainder.toString().padStart(2, '0')}`;
};

const syncedSegments = computed(() =>
    props.lyrics.segments.filter((segment) => segment.starts_at_ms !== null),
);

const youtubeEmbedUrl = computed(() => {
    if (!props.song.youtube_id) {
        return null;
    }

    return `https://www.youtube.com/embed/${props.song.youtube_id}`;
});

const plainLyricsLines = computed(() => {
    return props.lyrics.text
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0);
});
</script>

<template>
    <PublicLayout
        :title="props.song.title"
        :subtitle="`Artist: ${props.artist.name}`"
    >
        <section class="grid gap-8 xl:grid-cols-[0.85fr_1.15fr]">
            <div
                class="space-y-6 rounded-3xl border border-white/10 bg-white/5 p-6"
            >
                <div class="space-y-3 text-sm text-zinc-300">
                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            :href="props.artist.url"
                            class="font-medium text-amber-400 hover:text-amber-300"
                        >
                            {{ props.artist.name }}
                        </Link>

                        <template
                            v-if="props.song.album && props.song.album_url"
                        >
                            <span>•</span>
                            <Link
                                :href="props.song.album_url"
                                class="text-zinc-200 hover:text-white"
                            >
                                {{ props.song.album }}
                            </Link>
                        </template>
                    </div>

                    <p>
                        {{ props.song.parent_type }} ·
                        {{ formatDuration(props.song.duration_seconds) }}
                    </p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-black/30 p-4">
                    <iframe
                        v-if="youtubeEmbedUrl"
                        :src="youtubeEmbedUrl"
                        class="aspect-video w-full rounded-2xl"
                        allow="
                            accelerometer;
                            autoplay;
                            clipboard-write;
                            encrypted-media;
                            gyroscope;
                            picture-in-picture;
                            web-share;
                        "
                        allowfullscreen
                        referrerpolicy="strict-origin-when-cross-origin"
                        title="YouTube player"
                    />

                    <p v-else class="text-sm text-zinc-400">
                        Clipul YouTube nu este disponibil încă pentru această
                        piesă.
                    </p>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <h2 class="text-xl font-semibold text-white">Versuri</h2>
                    <span
                        v-if="props.lyrics.is_synced"
                        class="rounded-full bg-amber-400/15 px-3 py-1 text-xs font-semibold tracking-[0.2em] text-amber-300 uppercase"
                    >
                        sincronizate
                    </span>
                </div>

                <div
                    v-if="props.lyrics.is_synced && syncedSegments.length > 0"
                    class="max-h-[34rem] space-y-3 overflow-y-auto pr-2"
                >
                    <div
                        v-for="(segment, index) in syncedSegments"
                        :key="segment.id ?? `${segment.position}-${index}`"
                        class="rounded-2xl border border-white/8 bg-black/20 px-4 py-4 text-zinc-300"
                    >
                        <div class="flex items-start gap-4">
                            <span
                                class="mt-1 text-xs font-semibold tracking-[0.2em] text-zinc-500 uppercase"
                            >
                                {{ String(index + 1).padStart(2, '0') }}
                            </span>

                            <p class="text-lg leading-8">
                                {{ segment.text || 'Instrumental' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else-if="plainLyricsLines.length > 0" class="space-y-3">
                    <p
                        v-for="(line, index) in plainLyricsLines"
                        :key="`${line}-${index}`"
                        class="rounded-2xl border border-white/8 bg-black/20 px-4 py-3 text-lg leading-8 text-zinc-200"
                    >
                        {{ line }}
                    </p>
                </div>

                <p v-else class="text-zinc-400">
                    Versurile nu sunt disponibile încă pentru această piesă.
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
