<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import PublicSongCard from '@/components/public/PublicSongCard.vue';
import PublicLayout from '@/components/PublicLayout.vue';
import type { PublicAlbumPageProps } from '@/types';

const props = defineProps<PublicAlbumPageProps>();
</script>

<template>
    <PublicLayout
        eyebrow="Album Experience"
        :subtitle="
            props.album.description ??
            'Tracklist complet, mobile first, pregatit pentru listening flow.'
        "
        :title="props.album.title"
    >
        <div class="space-y-8">
            <section class="grid gap-5 lg:grid-cols-[0.78fr_1.22fr]">
                <div
                    class="overflow-hidden rounded-[2.5rem] border border-white/10 bg-white/[0.05] shadow-[0_24px_80px_rgba(0,0,0,0.35)]"
                >
                    <div
                        class="aspect-square bg-[radial-gradient(circle_at_top,rgba(251,191,36,0.18),transparent_36%),linear-gradient(180deg,rgba(255,255,255,0.12),rgba(255,255,255,0.03))]"
                    >
                        <img
                            v-if="props.album.cover_url"
                            :alt="props.album.title"
                            :src="props.album.cover_url"
                            class="h-full w-full object-cover"
                        />
                    </div>
                </div>

                <div class="space-y-5">
                    <div
                        class="rounded-[2.5rem] border border-white/10 bg-white/[0.05] p-6 backdrop-blur-xl"
                    >
                        <div
                            class="flex flex-wrap items-center gap-3 text-sm text-white/55"
                        >
                            <Link
                                :href="props.artist.url ?? '#'"
                                class="text-white"
                            >
                                {{ props.artist.name }}
                            </Link>
                            <span>•</span>
                            <span>{{ props.album.type }}</span>
                            <span v-if="props.album.release_date"
                                >• {{ props.album.release_date }}</span
                            >
                        </div>

                        <p class="mt-5 text-lg leading-8 text-white/64">
                            {{
                                props.album.description ??
                                'Albumul nu are descriere publica, dar flow-ul de ascultare si tracklistul sunt aici.'
                            }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-5">
                        <article
                            class="rounded-[2rem] border border-white/10 bg-white/[0.05] p-5"
                        >
                            <p
                                class="text-xs font-semibold tracking-[0.3em] text-white/38 uppercase"
                            >
                                Tracks
                            </p>
                            <p
                                class="mt-3 text-4xl font-semibold tracking-tight text-white"
                            >
                                {{ props.tracks.length }}
                            </p>
                        </article>
                        <article
                            class="rounded-[2rem] border border-white/10 bg-white/[0.05] p-5"
                        >
                            <p
                                class="text-xs font-semibold tracking-[0.3em] text-white/38 uppercase"
                            >
                                Format
                            </p>
                            <p
                                class="mt-3 text-2xl font-semibold tracking-tight text-white"
                            >
                                {{ props.album.type }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="space-y-5">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.3em] text-white/38 uppercase"
                        >
                            Tracklist
                        </p>
                        <h2
                            class="mt-2 text-2xl font-semibold tracking-tight text-white"
                        >
                            Asculta fiecare piesa
                        </h2>
                    </div>
                    <span class="text-sm text-white/45"
                        >{{ props.tracks.length }} piese</span
                    >
                </div>

                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="track in props.tracks"
                        :key="track.url"
                        class="space-y-3"
                    >
                        <div
                            class="flex items-center gap-3 px-1 text-xs font-semibold tracking-[0.28em] text-white/35 uppercase"
                        >
                            <span
                                >#{{
                                    String(track.track_number ?? 0).padStart(
                                        2,
                                        '0',
                                    )
                                }}</span
                            >
                            <span>{{ track.parent_type }}</span>
                        </div>
                        <PublicSongCard :song="track" :show-artist="false" />
                    </div>
                </div>
            </section>
        </div>
    </PublicLayout>
</template>
