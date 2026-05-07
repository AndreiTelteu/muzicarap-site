<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import type { ComponentPublicInstance } from 'vue';

import PublicLayout from '@/components/PublicLayout.vue';
import type { LyricsSegment, PublicSongPageProps } from '@/types';

const props = defineProps<PublicSongPageProps>();

const audioElement = ref<HTMLAudioElement | null>(null);
const currentTimeMs = ref(0);
const frameId = ref<number | null>(null);
const segmentElements = ref<Array<HTMLElement | null>>([]);

const formatDuration = (seconds: number | null): string => {
    if (seconds === null) {
        return 'Durată necunoscută';
    }

    const minutes = Math.floor(seconds / 60);
    const remainder = seconds % 60;

    return `${minutes}:${remainder.toString().padStart(2, '0')}`;
};

const syncedSegments = computed(() => {
    return props.lyrics.segments.filter((segment) => segment.starts_at_ms !== null);
});

const activeSegmentIndex = computed(() => {
    return syncedSegments.value.findIndex((segment, index) => {
        const startsAt = segment.starts_at_ms ?? 0;
        const nextSegment = syncedSegments.value[index + 1];
        const endsAt = segment.ends_at_ms ?? nextSegment?.starts_at_ms ?? Number.POSITIVE_INFINITY;

        return currentTimeMs.value >= startsAt && currentTimeMs.value < endsAt;
    });
});

const activeSegmentId = computed(() => {
    const activeSegment = syncedSegments.value[activeSegmentIndex.value];

    return activeSegment?.id ?? null;
});

const plainLyricsLines = computed(() => {
    return props.lyrics.text
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0);
});

const stopAnimationLoop = (): void => {
    if (frameId.value !== null) {
        window.cancelAnimationFrame(frameId.value);
        frameId.value = null;
    }
};

const syncCurrentTime = (): void => {
    currentTimeMs.value = Math.round((audioElement.value?.currentTime ?? 0) * 1000);

    if (!audioElement.value?.paused && !audioElement.value?.ended) {
        frameId.value = window.requestAnimationFrame(syncCurrentTime);
    }
};

const startAnimationLoop = (): void => {
    stopAnimationLoop();
    syncCurrentTime();
};

const setSegmentElement = (element: Element | ComponentPublicInstance | null, index: number): void => {
    let resolvedElement: Element | null = null;

    if (element instanceof Element) {
        resolvedElement = element;
    } else if (element !== null) {
        resolvedElement = element.$el instanceof Element ? element.$el : null;
    }

    segmentElements.value[index] = resolvedElement instanceof HTMLElement ? resolvedElement : null;
};

const scrollActiveSegmentIntoView = async (): Promise<void> => {
    await nextTick();

    const element = segmentElements.value[activeSegmentIndex.value];

    if (element === undefined || element === null) {
        return;
    }

    element.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
    });
};

const segmentProgress = (segment: LyricsSegment): number => {
    if (segment.starts_at_ms === null) {
        return 0;
    }

    const endsAt = segment.ends_at_ms ?? segment.starts_at_ms + 1;
    const duration = Math.max(endsAt - segment.starts_at_ms, 1);
    const elapsed = currentTimeMs.value - segment.starts_at_ms;
    const ratio = Math.min(Math.max(elapsed / duration, 0), 1);

    return ratio * 100;
};

watch(activeSegmentId, () => {
    if (activeSegmentIndex.value < 0) {
        return;
    }

    void scrollActiveSegmentIntoView();
});

onBeforeUnmount(() => {
    stopAnimationLoop();
});
</script>

<template>
    <PublicLayout :title="props.song.title" :subtitle="`Artist: ${props.artist.name}`">
        <section class="grid gap-8 xl:grid-cols-[0.85fr_1.15fr]">
            <div class="space-y-6 rounded-3xl border border-white/10 bg-white/5 p-6">
                <div class="space-y-3 text-sm text-zinc-300">
                    <div class="flex flex-wrap items-center gap-3">
                        <Link :href="props.artist.url" class="font-medium text-amber-400 hover:text-amber-300">
                            {{ props.artist.name }}
                        </Link>

                        <template v-if="props.song.album && props.song.album_url">
                            <span>•</span>
                            <Link :href="props.song.album_url" class="text-zinc-200 hover:text-white">
                                {{ props.song.album }}
                            </Link>
                        </template>
                    </div>

                    <p>{{ props.song.parent_type }} · {{ formatDuration(props.song.duration_seconds) }}</p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-black/30 p-4">
                    <audio
                        v-if="props.routes.audio"
                        ref="audioElement"
                        :src="props.routes.audio"
                        class="w-full"
                        controls
                        preload="metadata"
                        @pause="stopAnimationLoop"
                        @play="startAnimationLoop"
                        @seeked="syncCurrentTime"
                        @timeupdate="syncCurrentTime"
                    />

                    <p v-else class="text-sm text-zinc-400">
                        Fișierul audio nu este disponibil pentru streaming public.
                    </p>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <h2 class="text-xl font-semibold text-white">Versuri</h2>
                    <span v-if="props.lyrics.is_synced" class="rounded-full bg-amber-400/15 px-3 py-1 text-xs font-semibold tracking-[0.2em] text-amber-300 uppercase">
                        sincronizate
                    </span>
                </div>

                <div
                    v-if="props.lyrics.is_synced && syncedSegments.length > 0"
                    class="max-h-[34rem] space-y-3 overflow-y-auto pr-2"
                >
                    <div
                        v-for="(segment, index) in syncedSegments"
                        :key="segment.id ?? `${segment.position}-${index}`"
                        :ref="(element) => setSegmentElement(element, index)"
                        class="relative overflow-hidden rounded-2xl border px-4 py-4 transition duration-300"
                        :class="activeSegmentId === segment.id
                            ? 'border-amber-400/60 bg-amber-400/10 text-white shadow-lg shadow-amber-500/10'
                            : 'border-white/8 bg-black/20 text-zinc-300'"
                    >
                        <div
                            class="absolute inset-y-0 left-0 bg-amber-400/12 transition-[width] duration-150"
                            :style="{ width: `${segmentProgress(segment)}%` }"
                        />

                        <div class="relative flex items-start gap-4">
                            <span class="mt-1 text-xs font-semibold tracking-[0.2em] text-zinc-500 uppercase">
                                {{ String(index + 1).padStart(2, '0') }}
                            </span>

                            <p class="text-lg leading-8">
                                {{ segment.text || 'Instrumental' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else-if="plainLyricsLines.length > 0" class="space-y-3">
                    <p
                        v-for="(line, index) in plainLyricsLines"
                        :key="`${line}-${index}`"
                        class="rounded-2xl border border-white/8 bg-black/20 px-4 py-3 text-lg leading-8 text-zinc-200"
                    >
                        {{ line }}
                    </p>
                </div>

                <p v-else class="text-zinc-400">Versurile nu sunt disponibile încă pentru această piesă.</p>
            </div>
        </section>
    </PublicLayout>
</template>
