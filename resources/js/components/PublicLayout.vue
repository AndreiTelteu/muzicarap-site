<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        subtitle?: string | null;
    }>(),
    {
        subtitle: null,
    },
);

const pageTitle = computed(() => {
    return props.title;
});
</script>

<template>
    <Head :title="pageTitle" />

    <div class="min-h-screen bg-zinc-950 text-zinc-100">
        <header class="border-b border-white/10 bg-black/30 backdrop-blur">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5">
                <Link class="text-lg font-semibold tracking-[0.2em] text-amber-400 uppercase" href="/">
                    MuzicaRap
                </Link>

                <p class="hidden text-sm text-zinc-400 md:block">
                    Catalog public cu player audio și versuri sincronizate
                </p>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-6xl flex-col gap-8 px-6 py-10">
            <section class="space-y-3">
                <h1 class="text-3xl font-bold tracking-tight text-white md:text-5xl">
                    {{ title }}
                </h1>

                <p v-if="subtitle" class="max-w-3xl text-base leading-7 text-zinc-300">
                    {{ subtitle }}
                </p>
            </section>

            <slot />
        </main>
    </div>
</template>
