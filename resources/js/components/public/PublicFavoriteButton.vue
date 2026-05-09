<script setup lang="ts">
import { computed } from 'vue';

import IconSymbol from '@/components/public/IconSymbol.vue';
import { usePublicFavorites } from '@/composables/usePublicFavorites';
import type { FavoriteSongEntry } from '@/types';

const props = defineProps<{
    song: FavoriteSongEntry;
}>();

const { isFavorite, toggleFavorite } = usePublicFavorites();

const favorite = computed(() => isFavorite(props.song.key));
</script>

<template>
    <button
        :aria-label="favorite ? 'Scoate din favorite' : 'Adaugă la favorite'"
        class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-full border border-white/10 bg-white/8 text-white/80 backdrop-blur-sm transition hover:border-rose-300/40 hover:text-white"
        type="button"
        @click.stop="toggleFavorite(song)"
    >
        <IconSymbol
            :filled="favorite"
            name="favorite"
            :class="favorite ? 'text-rose-300' : ''"
        />
    </button>
</template>
