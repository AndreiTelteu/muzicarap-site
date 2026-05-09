<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import PublicBottomNav from '@/components/public/PublicBottomNav.vue';
import PublicPlayerSurface from '@/components/public/PublicPlayerSurface.vue';
import PublicSearchSheet from '@/components/public/PublicSearchSheet.vue';

const props = withDefaults(
    defineProps<{
        title: string;
        subtitle?: string | null;
        eyebrow?: string | null;
    }>(),
    {
        subtitle: null,
        eyebrow: null,
    },
);

const searchOpen = ref(false);

const pageTitle = computed(() => props.title);
</script>

<template>
    <Head :title="pageTitle" />

    <div class="min-h-screen bg-[#050505] text-white">
        <div
            class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,rgba(251,191,36,0.14),transparent_24%),radial-gradient(circle_at_bottom,rgba(255,255,255,0.08),transparent_24%)]"
        />

        <main
            class="relative mx-auto flex min-h-screen w-full max-w-6xl flex-col px-4 pt-[max(1.25rem,env(safe-area-inset-top))] pb-36 sm:px-6 lg:px-8"
        >
            <section class="mb-7 flex flex-col gap-3 pt-3">
                <p
                    v-if="eyebrow"
                    class="text-xs font-semibold tracking-[0.35em] text-white/42 uppercase"
                >
                    {{ eyebrow }}
                </p>

                <h1
                    class="max-w-4xl text-4xl font-semibold tracking-[-0.04em] text-white sm:text-5xl md:text-6xl"
                >
                    {{ title }}
                </h1>

                <p
                    v-if="subtitle"
                    class="max-w-2xl text-base leading-7 text-white/58 sm:text-lg"
                >
                    {{ subtitle }}
                </p>
            </section>

            <slot />
        </main>

        <PublicSearchSheet :open="searchOpen" @close="searchOpen = false" />
        <PublicPlayerSurface />
        <PublicBottomNav @search="searchOpen = true" />
    </div>
</template>
