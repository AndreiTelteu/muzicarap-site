<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import PublicLayout from '@/components/PublicLayout.vue';
import type { PublicHomePageProps } from '@/types';

const props = defineProps<PublicHomePageProps>();

const formatDuration = (seconds: number | null): string => {
    if (seconds === null) {
        return 'Durată necunoscută';
    }

    const minutes = Math.floor(seconds / 60);
    const remainder = seconds % 60;

    return `${minutes}:${remainder.toString().padStart(2, '0')}`;
};
</script>

<template>
    <PublicLayout
        title="Ultimele piese publicate"
        subtitle="Ascultă cele mai recente lansări din catalogul public și intră direct pe pagina fiecărei piese."
    >
        <section v-if="props.latestSongs.length > 0" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="song in props.latestSongs"
                :key="`${song.artist.slug}-${song.slug}`"
                class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/20 transition hover:-translate-y-1 hover:border-amber-400/40"
            >
                <div class="mb-4 flex items-center justify-between gap-3 text-xs uppercase tracking-[0.25em] text-zinc-400">
                    <span>{{ song.parent_type }}</span>
                    <span>{{ formatDuration(song.duration_seconds) }}</span>
                </div>

                <Link :href="song.url" class="block text-2xl font-semibold text-white transition hover:text-amber-300">
                    {{ song.title }}
                </Link>

                <div class="mt-3 space-y-2 text-sm text-zinc-300">
                    <Link :href="song.artist.url ?? '#'" class="font-medium text-amber-400 hover:text-amber-300">
                        {{ song.artist.name }}
                    </Link>

                    <p v-if="song.album">
                        Album: {{ song.album }}
                    </p>
                </div>
            </article>
        </section>

        <section v-else class="rounded-3xl border border-dashed border-white/15 bg-white/5 p-10 text-zinc-300">
            Nu există încă piese publicate.
        </section>
    </PublicLayout>
</template>
