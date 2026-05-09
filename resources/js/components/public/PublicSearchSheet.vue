<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

import IconSymbol from '@/components/public/IconSymbol.vue';
import PublicArtistCard from '@/components/public/PublicArtistCard.vue';
import PublicSongCard from '@/components/public/PublicSongCard.vue';
import { search } from '@/routes';
import type { PublicSearchResults } from '@/types';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const query = ref('');
const loading = ref(false);
const results = ref<PublicSearchResults>({
    query: '',
    artists: [],
    albums: [],
    songs: [],
});

const hasResults = computed(() => {
    return (
        results.value.artists.length > 0 ||
        results.value.albums.length > 0 ||
        results.value.songs.length > 0
    );
});

const fetchResults = useDebounceFn(async (): Promise<void> => {
    const normalized = query.value.trim();

    if (normalized.length === 0) {
        results.value = {
            query: '',
            artists: [],
            albums: [],
            songs: [],
        };

        return;
    }

    loading.value = true;

    try {
        const response = await fetch(
            search.url({ query: { query: normalized } }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) {
            return;
        }

        results.value = (await response.json()) as PublicSearchResults;
    } finally {
        loading.value = false;
    }
}, 220);

watch(query, () => {
    void fetchResults();
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        requestAnimationFrame(() => {
            document.getElementById('public-search-input')?.focus();
        });
    },
);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 bg-black/70 backdrop-blur-md"
                @click="emit('close')"
            />
        </Transition>

        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-full opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-full opacity-0"
        >
            <section
                v-if="open"
                class="fixed inset-x-0 bottom-0 z-[60] mx-auto flex h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-t-[2.25rem] border border-white/10 bg-[#090909]/98 shadow-[0_-30px_90px_rgba(0,0,0,0.75)]"
            >
                <div
                    class="flex flex-1 flex-col overflow-hidden px-4 pt-4 sm:px-6"
                >
                    <div
                        class="mx-auto mb-4 h-1.5 w-14 rounded-full bg-white/15"
                    />

                    <div class="flex items-center justify-between gap-3 pb-4">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.3em] text-white/45 uppercase"
                            >
                                Search
                            </p>
                            <h2
                                class="mt-2 text-2xl font-semibold tracking-tight text-white"
                            >
                                Descopera instant
                            </h2>
                        </div>

                        <button
                            class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-full border border-white/10 bg-white/[0.06] text-white/75"
                            type="button"
                            @click="emit('close')"
                        >
                            <IconSymbol name="close" />
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto pb-36">
                        <div
                            v-if="query.trim().length === 0"
                            class="space-y-6 py-10 text-center text-white/58"
                        >
                            <p class="text-lg text-white/84">
                                Cauta o piesa, un artist sau un album.
                            </p>
                            <p class="mx-auto max-w-sm text-sm leading-6">
                                Rezultatele apar deasupra barei, ca intr-o
                                aplicatie de streaming.
                            </p>
                        </div>

                        <div v-else-if="loading" class="space-y-3 py-6">
                            <div
                                v-for="index in 4"
                                :key="index"
                                class="h-28 animate-pulse rounded-[1.75rem] bg-white/6"
                            />
                        </div>

                        <div v-else-if="hasResults" class="space-y-8 py-2">
                            <section
                                v-if="results.songs.length > 0"
                                class="space-y-4"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <h3
                                        class="text-sm font-semibold tracking-[0.3em] text-white/45 uppercase"
                                    >
                                        Piese
                                    </h3>
                                    <span class="text-xs text-white/35">{{
                                        results.songs.length
                                    }}</span>
                                </div>

                                <div class="space-y-4">
                                    <PublicSongCard
                                        v-for="song in results.songs"
                                        :key="song.url"
                                        :show-artist="true"
                                        :song="song"
                                        priority="compact"
                                    />
                                </div>
                            </section>

                            <section
                                v-if="results.artists.length > 0"
                                class="space-y-4"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <h3
                                        class="text-sm font-semibold tracking-[0.3em] text-white/45 uppercase"
                                    >
                                        Artisti
                                    </h3>
                                    <span class="text-xs text-white/35">{{
                                        results.artists.length
                                    }}</span>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <PublicArtistCard
                                        v-for="artist in results.artists"
                                        :key="artist.slug"
                                        :artist="artist"
                                    />
                                </div>
                            </section>

                            <section
                                v-if="results.albums.length > 0"
                                class="space-y-4"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <h3
                                        class="text-sm font-semibold tracking-[0.3em] text-white/45 uppercase"
                                    >
                                        Albume
                                    </h3>
                                    <span class="text-xs text-white/35">{{
                                        results.albums.length
                                    }}</span>
                                </div>

                                <div class="space-y-3">
                                    <Link
                                        v-for="album in results.albums"
                                        :key="album.url"
                                        :href="album.url"
                                        class="flex items-center gap-4 rounded-[1.75rem] border border-white/10 bg-white/[0.05] p-3"
                                    >
                                        <div
                                            class="h-18 w-18 overflow-hidden rounded-[1.35rem] bg-white/8"
                                        >
                                            <img
                                                v-if="album.cover_url"
                                                :alt="album.title"
                                                :src="album.cover_url"
                                                class="h-full w-full object-cover"
                                            />
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="truncate text-lg font-semibold tracking-tight text-white"
                                            >
                                                {{ album.title }}
                                            </p>
                                            <p
                                                class="mt-1 truncate text-sm text-white/55"
                                            >
                                                {{ album.artist?.name }} ·
                                                {{ album.type }}
                                            </p>
                                        </div>
                                    </Link>
                                </div>
                            </section>
                        </div>

                        <div v-else class="py-10 text-center text-white/58">
                            Nu am gasit nimic pentru “{{ query }}”.
                        </div>
                    </div>
                </div>

                <div
                    class="absolute inset-x-0 bottom-0 border-t border-white/8 bg-black/90 px-4 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom))] backdrop-blur-xl sm:px-6"
                >
                    <label
                        class="flex items-center gap-3 rounded-full border border-white/10 bg-white/[0.06] px-4 py-4"
                    >
                        <IconSymbol name="search" class="text-white/45" />
                        <input
                            id="public-search-input"
                            v-model="query"
                            autocomplete="off"
                            class="w-full bg-transparent text-base text-white outline-none placeholder:text-white/35"
                            placeholder="Cauta artisti, piese, albume"
                            type="search"
                        />
                    </label>
                </div>
            </section>
        </Transition>
    </Teleport>
</template>
