import { useStorage } from '@vueuse/core';
import { computed } from 'vue';

import type { FavoriteSongEntry } from '@/types';

const favoriteSongs = useStorage<FavoriteSongEntry[]>(
    'muzicarap.favorite-songs',
    [],
);

const favoriteKeys = computed(() => {
    return new Set(favoriteSongs.value.map((song) => song.key));
});

export const usePublicFavorites = () => {
    const isFavorite = (songKey: string): boolean => {
        return favoriteKeys.value.has(songKey);
    };

    const addFavorite = (song: FavoriteSongEntry): void => {
        favoriteSongs.value = [
            song,
            ...favoriteSongs.value.filter((entry) => entry.key !== song.key),
        ];
    };

    const removeFavorite = (songKey: string): void => {
        favoriteSongs.value = favoriteSongs.value.filter(
            (song) => song.key !== songKey,
        );
    };

    const toggleFavorite = (song: FavoriteSongEntry): void => {
        if (isFavorite(song.key)) {
            removeFavorite(song.key);

            return;
        }

        addFavorite(song);
    };

    return {
        favoriteSongs,
        isFavorite,
        addFavorite,
        removeFavorite,
        toggleFavorite,
    };
};
