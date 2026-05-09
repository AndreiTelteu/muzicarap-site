<script setup lang="ts">
import PublicArtistCard from '@/components/public/PublicArtistCard.vue';
import PublicLayout from '@/components/PublicLayout.vue';
import type { PublicArtistsIndexPageProps } from '@/types';

const props = defineProps<PublicArtistsIndexPageProps>();
</script>

<template>
    <PublicLayout
        eyebrow="Artists Directory"
        title="Artisti"
        subtitle="O galerie mobile-first pentru tot catalogul public, gandita ca un ecran de browse dintr-o aplicatie de muzica."
    >
        <div class="grid grid-cols-2 gap-5 lg:grid-cols-4">
            <PublicArtistCard
                v-for="artist in props.artists"
                :key="artist.slug"
                :artist="artist"
            />
        </div>
    </PublicLayout>
</template>
