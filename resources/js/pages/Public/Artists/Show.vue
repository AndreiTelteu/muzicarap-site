<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import PublicSongCard from '@/components/public/PublicSongCard.vue';
import PublicLayout from '@/components/PublicLayout.vue';
import type { PublicArtistPageProps } from '@/types';

const props = defineProps<PublicArtistPageProps>();
</script>

<template>
    <PublicLayout
        eyebrow="Artist View"
        :subtitle="
            props.artist.bio ??
            'Discografie publica, albume si piese gata de ascultat cu playerul integrat.'
        "
        :title="props.artist.name"
    >
        <div class="space-y-8">
            <section class="grid gap-5 lg:grid-cols-[0.86fr_1.14fr]">
                <div
                    class="overflow-hidden rounded-[2.5rem] border border-white/10 bg-white/[0.05] shadow-[0_24px_80px_rgba(0,0,0,0.35)]"
                >
                    <div
                        class="aspect-[0.95/1.1] bg-[radial-gradient(circle_at_top,rgba(251,191,36,0.2),transparent_38%),linear-gradient(180deg,rgba(255,255,255,0.12),rgba(255,255,255,0.02))]"
                    >
                        <img
                            v-if="props.artist.image_url"
                            :alt="props.artist.name"
                            :src="props.artist.image_url"
                            class="h-full w-full object-cover"
                        />
                    </div>
                </div>

                <div class="space-y-5">
                    <div
                        class="rounded-[2.5rem] border border-white/10 bg-white/[0.05] p-6 backdrop-blur-xl"
                    >
                        <p
                            class="text-xs font-semibold tracking-[0.3em] text-white/38 uppercase"
                        >
                            Snapshot
                        </p>
                        <p class="mt-4 text-lg leading-8 text-white/68">
                            {{
                                props.artist.bio ??
                                'Artistul nu are inca bio public, dar catalogul e live si gata de explorat.'
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
                                Piese
                            </p>
                            <p
                                class="mt-3 text-4xl font-semibold tracking-tight text-white"
                            >
                                {{ props.songs.length }}
                            </p>
                        </article>
                        <article
                            class="rounded-[2rem] border border-white/10 bg-white/[0.05] p-5"
                        >
                            <p
                                class="text-xs font-semibold tracking-[0.3em] text-white/38 uppercase"
                            >
                                Albume
                            </p>
                            <p
                                class="mt-3 text-4xl font-semibold tracking-tight text-white"
                            >
                                {{ props.albums.length }}
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
                            Songs
                        </p>
                        <h2
                            class="mt-2 text-2xl font-semibold tracking-tight text-white"
                        >
                            Piese publice
                        </h2>
                    </div>
                    <span class="text-sm text-white/45"
                        >{{ props.songs.length }} rezultate</span
                    >
                </div>

                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <PublicSongCard
                        v-for="song in props.songs"
                        :key="song.url"
                        :show-artist="false"
                        :song="song"
                    />
                </div>
            </section>

            <section class="space-y-5">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.3em] text-white/38 uppercase"
                        >
                            Albums
                        </p>
                        <h2
                            class="mt-2 text-2xl font-semibold tracking-tight text-white"
                        >
                            Albume publice
                        </h2>
                    </div>
                    <span class="text-sm text-white/45"
                        >{{ props.albums.length }} rezultate</span
                    >
                </div>

                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="album in props.albums"
                        :key="album.url"
                        :href="album.url"
                        class="overflow-hidden rounded-[2rem] border border-white/10 bg-white/[0.05] shadow-[0_24px_80px_rgba(0,0,0,0.3)]"
                    >
                        <div
                            class="aspect-square bg-[radial-gradient(circle_at_top,rgba(255,255,255,0.12),transparent_42%),linear-gradient(180deg,rgba(255,255,255,0.12),rgba(255,255,255,0.04))]"
                        >
                            <img
                                v-if="album.cover_url"
                                :alt="album.title"
                                :src="album.cover_url"
                                class="h-full w-full object-cover"
                            />
                        </div>
                        <div class="space-y-2 p-5">
                            <p
                                class="text-xl font-semibold tracking-tight text-white"
                            >
                                {{ album.title }}
                            </p>
                            <p class="text-sm text-white/55">
                                {{ album.type }} ·
                                {{ album.songs_count ?? 0 }} piese
                            </p>
                        </div>
                    </Link>
                </div>
            </section>
        </div>
    </PublicLayout>
</template>
