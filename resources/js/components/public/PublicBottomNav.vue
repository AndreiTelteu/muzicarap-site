<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import Public from '@/actions/App/Http/Controllers/Public';
import IconSymbol from '@/components/public/IconSymbol.vue';
import { home } from '@/routes';
import artists from '@/routes/artists';

const emit = defineEmits<{
    search: [];
}>();

const page = usePage();

const currentUrl = computed(() => page.url);

const items = computed(() => [
    {
        icon: 'home' as const,
        isActive: currentUrl.value === home.url(),
        label: 'Acasa',
        type: 'link' as const,
        url: home.url(),
    },
    {
        icon: 'search' as const,
        isActive: false,
        label: 'Search',
        type: 'button' as const,
    },
    {
        icon: 'artists' as const,
        isActive:
            currentUrl.value.startsWith(artists.index.url()) ||
            currentUrl.value.startsWith('/artisti/'),
        label: 'Artisti',
        type: 'link' as const,
        url: artists.index.url(),
    },
    {
        icon: 'heart' as const,
        isActive: currentUrl.value.startsWith(
            Public.FavoritesIndexController.url(),
        ),
        label: 'Favorite',
        type: 'link' as const,
        url: Public.FavoritesIndexController.url(),
    },
]);
</script>

<template>
    <nav
        class="pointer-events-auto fixed inset-x-0 bottom-4 z-40 px-4 pb-[max(env(safe-area-inset-bottom),0.25rem)] sm:px-6"
    >
        <div
            class="mx-auto flex max-w-md items-center justify-between rounded-full border border-white/10 bg-black/78 px-3 py-2 shadow-[0_18px_60px_rgba(0,0,0,0.55)] backdrop-blur-2xl"
        >
            <template v-for="item in items" :key="item.label">
                <button
                    v-if="item.type === 'button'"
                    class="flex min-w-[4.5rem] cursor-pointer flex-col items-center gap-1 rounded-full px-3 py-2 text-[0.68rem] font-medium text-white/68 transition hover:text-white"
                    type="button"
                    @click="emit('search')"
                >
                    <IconSymbol :name="item.icon" />
                    <span>{{ item.label }}</span>
                </button>

                <Link
                    v-else
                    :href="item.url"
                    class="flex min-w-[4.5rem] cursor-pointer flex-col items-center gap-1 rounded-full px-3 py-2 text-[0.68rem] font-medium transition"
                    :class="
                        item.isActive
                            ? 'bg-white text-black'
                            : 'text-white/68 hover:text-white'
                    "
                >
                    <IconSymbol :name="item.icon" />
                    <span>{{ item.label }}</span>
                </Link>
            </template>
        </div>
    </nav>
</template>
