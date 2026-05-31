<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name:
            | 'artists'
            | 'close'
            | 'favorite'
            | 'heart'
            | 'home'
            | 'note'
            | 'pause'
            | 'play'
            | 'search';
        filled?: boolean;
    }>(),
    {
        filled: false,
    },
);

const icon = computed(() => {
    switch (props.name) {
        case 'artists':
            return {
                paths: [
                    'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2',
                    'M9 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
                    'M22 21v-2a4 4 0 0 0-3-3.87',
                    'M16 3.13a4 4 0 0 1 0 7.75',
                ],
            };
        case 'close':
            return {
                paths: ['M18 6 6 18', 'M6 6l12 12'],
            };
        case 'favorite':
        case 'heart':
            return {
                paths: [
                    'M12 21.35 10.55 20C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09A6 6 0 0 1 16.5 3C19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35Z',
                ],
            };
        case 'note':
            return {
                paths: [
                    'M9 18V5l12-2v13',
                    'M9 9l12-2',
                    'M6 20a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
                    'M18 18a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
                ],
            };
        case 'pause':
            return {
                paths: ['M10 5H6v14h4V5Zm8 0h-4v14h4V5Z'],
            };
        case 'play':
            return {
                paths: ['m8 5 11 7-11 7V5Z'],
            };
        case 'search':
            return {
                paths: [
                    'm21 21-4.35-4.35',
                    'M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z',
                ],
            };
        case 'home':
        default:
            return {
                paths: ['M3 11.5 12 4l9 7.5', 'M5 10.5V20h14v-9.5'],
            };
    }
});
</script>

<template>
    <svg
        aria-hidden="true"
        :class="[
            'h-5 w-5 shrink-0',
            props.name === 'artists' ? 'translate-y-px' : '',
        ]"
        fill="none"
        style="overflow: visible"
        stroke="currentColor"
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="1.75"
        viewBox="0 0 24 24"
    >
        <path
            v-for="path in icon.paths"
            :key="path"
            :d="path"
            :fill="
                filled &&
                (props.name === 'favorite' ||
                    props.name === 'heart' ||
                    props.name === 'pause' ||
                    props.name === 'play')
                    ? 'currentColor'
                    : 'none'
            "
        />
    </svg>
</template>
