<script setup lang="ts">
import { computed } from 'vue';

import PublicSongCard from '@/components/public/PublicSongCard.vue';
import PublicLayout from '@/components/PublicLayout.vue';
import { usePublicFavorites } from '@/composables/usePublicFavorites';

const { favoriteSongs } = usePublicFavorites();

const songs = computed(() => favoriteSongs.value);
</script>

<template>
    <PublicLayout
        eyebrow="Saved Locally"
        title="Favorite"
        subtitle="Piesele salvate raman in browserul tau si apar instant in ecranul dedicat de jos din aplicatie."
    >
        <div
            v-if="songs.length > 0"
            class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"
        >
            <PublicSongCard
                v-for="song in songs"
                :key="song.key"
                :song="song"
            />
        </div>

        <div
            v-else
            class="rounded-[2.5rem] border border-dashed border-white/12 bg-white/[0.04] px-6 py-14 text-center text-white/58"
        >
            Nu ai piese favorite inca. Salveaza-le din player, din Home sau din
            paginile de artist si album.
        </div>
    </PublicLayout>
</template>
