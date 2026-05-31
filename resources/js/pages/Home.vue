<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import PublicArtistCard from '@/components/public/PublicArtistCard.vue';
import PublicSongCard from '@/components/public/PublicSongCard.vue';
import PublicLayout from '@/components/PublicLayout.vue';
import artists from '@/routes/artists';
import type { PublicHomePageProps } from '@/types';

const props = defineProps<PublicHomePageProps>();

const heroSong = props.latestSongs[0] ?? null;
const stackedSongs = props.latestSongs.slice(1, 4);
</script>

<template>
    <PublicLayout
        title="Muzica rap romaneasca"
    >
        <div class="space-y-10">
            <section
                v-if="heroSong"
                class="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]"
            >
                <PublicSongCard :song="heroSong" priority="feature" />

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <article
                        v-for="song in stackedSongs"
                        :key="song.url"
                        class="overflow-hidden rounded-[2rem] border border-white/10 bg-white/[0.05] p-5 shadow-[0_24px_80px_rgba(0,0,0,0.3)] backdrop-blur-xl"
                    >
                        <p
                            class="text-xs font-semibold tracking-[0.3em] text-white/38 uppercase"
                        >
                            Fresh Drop
                        </p>
                        <Link
                            :href="song.url"
                            class="mt-4 block text-2xl font-semibold tracking-tight text-white"
                        >
                            {{ song.title }}
                        </Link>
                        <p class="mt-2 text-sm text-white/55">
                            {{ song.artist.name }} · {{ song.parent_type }}
                        </p>
                    </article>

                    <Link
                        :href="artists.index.url()"
                        class="flex min-h-[11rem] flex-col justify-between rounded-[2rem] border border-amber-300/18 bg-[linear-gradient(180deg,rgba(251,191,36,0.14),rgba(255,255,255,0.03))] p-5 shadow-[0_24px_80px_rgba(0,0,0,0.3)]"
                    >
                        <span
                            class="text-xs font-semibold tracking-[0.3em] text-amber-100/68 uppercase"
                            >Explore</span
                        >
                        <div>
                            <p
                                class="text-2xl font-semibold tracking-tight text-white"
                            >
                                Intra in universul artistilor
                            </p>
                            <p class="mt-2 text-sm leading-6 text-white/56">
                                Deschide catalogul si navigheaza ca intr-o app
                                de streaming.
                            </p>
                        </div>
                    </Link>
                </div>
            </section>

            <section class="space-y-5">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.3em] text-white/38 uppercase"
                        >
                            Latest Tracks
                        </p>
                        <h2
                            class="mt-2 text-2xl font-semibold tracking-tight text-white"
                        >
                            Drop-uri noi
                        </h2>
                    </div>
                </div>

                <div
                    v-if="props.latestSongs.length > 0"
                    class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"
                >
                    <PublicSongCard
                        v-for="song in props.latestSongs"
                        :key="song.url"
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
                            Artists
                        </p>
                        <h2
                            class="mt-2 text-2xl font-semibold tracking-tight text-white"
                        >
                            Vocile care conduc feed-ul
                        </h2>
                    </div>

                    <Link
                        :href="artists.index.url()"
                        class="text-sm text-white/55"
                    >
                        Vezi toti artistii
                    </Link>
                </div>

                <div class="grid grid-cols-2 gap-5 lg:grid-cols-4">
                    <PublicArtistCard
                        v-for="artist in props.featuredArtists"
                        :key="artist.slug"
                        :artist="artist"
                    />
                </div>
            </section>
        </div>
    </PublicLayout>
</template>
