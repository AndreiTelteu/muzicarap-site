<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

import type { LyricsSegment, LyricsSyncPageProps } from '@/types';

const props = defineProps<LyricsSyncPageProps>();

const csrfToken = document
    .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
    ?.content;

const lyrics = ref(props.lyric.lyrics);
const segments = ref<LyricsSegment[]>(cloneSegments(props.segments));
const activeIndex = ref(segments.value.findIndex((segment) => segment.starts_at_ms === null));
const currentTimeMs = ref(0);
const zoom = ref(12);
const isSaving = ref(false);
const isResegmenting = ref(false);
const isCrawling = ref(false);
const notice = reactive({ type: 'idle', message: '' as string });
const audio = ref<HTMLAudioElement | null>(null);

const durationMs = computed(() => {
    const explicitDuration = props.song.duration_seconds ?? 0;
    const audioDuration = audio.value?.duration ? Math.round(audio.value.duration * 1000) : 0;

    return Math.max(explicitDuration * 1000, audioDuration, 1);
});

const timelineWidth = computed(() => Math.max(1400, Math.round((durationMs.value / 1000) * zoom.value * 8)));
const nextUntimedIndex = computed(() => segments.value.findIndex((segment) => segment.starts_at_ms === null));
const hasAudio = computed(() => props.routes.audio !== null);
const dirty = computed(
    () =>
        lyrics.value !== props.lyric.lyrics ||
        JSON.stringify(segments.value) !== JSON.stringify(props.segments),
);

watch(nextUntimedIndex, (index) => {
    if (index >= 0) {
        activeIndex.value = index;
    }
});

watch(activeIndex, async (index) => {
    if (index < 0) {
        return;
    }

    await nextTick();

    document
        .getElementById(`segment-${index}`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
});

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
});

function cloneSegments(source: LyricsSegment[]): LyricsSegment[] {
    return source.map((segment) => ({ ...segment }));
}

function msToClock(value: number | null): string {
    if (value === null) {
        return '--:--.---';
    }

    const totalSeconds = Math.floor(value / 1000);
    const minutes = Math.floor(totalSeconds / 60)
        .toString()
        .padStart(2, '0');
    const seconds = (totalSeconds % 60).toString().padStart(2, '0');
    const milliseconds = (value % 1000).toString().padStart(3, '0');

    return `${minutes}:${seconds}.${milliseconds}`;
}

function markerLeft(segment: LyricsSegment): string {
    if (segment.starts_at_ms === null) {
        return '0%';
    }

    return `${Math.min(100, (segment.starts_at_ms / durationMs.value) * 100)}%`;
}

function progressWidth(): string {
    return `${Math.min(100, (currentTimeMs.value / durationMs.value) * 100)}%`;
}

function syncFromCurrentTime(): void {
    if (!hasAudio.value || audio.value === null || activeIndex.value < 0) {
        return;
    }

    const timestamp = Math.max(0, Math.round(audio.value.currentTime * 1000));
    const segment = segments.value[activeIndex.value];

    if (!segment) {
        return;
    }

    segment.starts_at_ms = timestamp;

    const previousSegment = segments.value[activeIndex.value - 1];

    if (previousSegment && previousSegment.ends_at_ms === null) {
        previousSegment.ends_at_ms = timestamp;
    }

    const nextIndex = activeIndex.value + 1;
    activeIndex.value = nextIndex < segments.value.length ? nextIndex : segments.value.length - 1;
    notice.type = 'idle';
    notice.message = `Stamped line ${segment.position} at ${msToClock(timestamp)}.`;
}

function handleKeydown(event: KeyboardEvent): void {
    const target = event.target as HTMLElement | null;

    if (target && ['INPUT', 'TEXTAREA'].includes(target.tagName)) {
        return;
    }

    if (event.code === 'Space') {
        event.preventDefault();
        syncFromCurrentTime();
    }

    if (event.code === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = Math.min(activeIndex.value + 1, segments.value.length - 1);
    }

    if (event.code === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
    }
}

function updateCurrentTime(): void {
    currentTimeMs.value = Math.round((audio.value?.currentTime ?? 0) * 1000);
}

function selectSegment(index: number): void {
    activeIndex.value = index;

    const segment = segments.value[index];

    if (segment?.starts_at_ms !== null && audio.value !== null) {
        audio.value.currentTime = segment.starts_at_ms / 1000;
        updateCurrentTime();
    }
}

function resetSegment(index: number): void {
    const segment = segments.value[index];

    if (!segment) {
        return;
    }

    segment.starts_at_ms = null;
    segment.ends_at_ms = null;
    activeIndex.value = index;
}

async function requestJson(url: string, method: 'POST' | 'PUT', payload: unknown) {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken ?? '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        const fallbackMessage = `Request failed with status ${response.status}.`;
        const data = (await response.json().catch(() => null)) as { message?: string } | null;

        throw new Error(data?.message ?? fallbackMessage);
    }

    return response.json();
}

async function saveSegments(): Promise<void> {
    isSaving.value = true;
    notice.type = 'idle';
    notice.message = '';

    try {
        const data = (await requestJson(props.routes.save, 'PUT', {
            lyrics: lyrics.value,
            segments: segments.value,
        })) as {
            lyric: LyricsSyncPageProps['lyric'] & { updated_at: string | null };
            segments: LyricsSegment[];
        };

        segments.value = cloneSegments(data.segments);
        notice.type = 'success';
        notice.message = data.lyric.synced_at
            ? `Saved. Fully synced at ${new Date(data.lyric.synced_at).toLocaleString()}.`
            : 'Saved draft timestamps.';
    } catch (error) {
        notice.type = 'error';
        notice.message = error instanceof Error ? error.message : 'Could not save lyrics sync data.';
    } finally {
        isSaving.value = false;
    }
}

async function resegmentLyrics(): Promise<void> {
    isResegmenting.value = true;

    try {
        const data = (await requestJson(props.routes.resegment, 'POST', {
            lyrics: lyrics.value,
        })) as { lyrics: string; segments: LyricsSegment[] };

        lyrics.value = data.lyrics;
        segments.value = cloneSegments(data.segments);
        activeIndex.value = data.segments.length > 0 ? 0 : -1;
        notice.type = 'success';
        notice.message = 'Lyrics were re-segmented from the current text.';
    } catch (error) {
        notice.type = 'error';
        notice.message = error instanceof Error ? error.message : 'Could not re-segment lyrics.';
    } finally {
        isResegmenting.value = false;
    }
}

async function crawlLyrics(): Promise<void> {
    isCrawling.value = true;

    try {
        const data = (await requestJson(props.routes.crawl, 'POST', {})) as {
            run: { id: number; status: string; search_query: string };
        };

        notice.type = 'success';
        notice.message = `Manual crawl queued (${data.run.status}) for query: ${data.run.search_query}.`;
    } catch (error) {
        notice.type = 'error';
        notice.message = error instanceof Error ? error.message : 'Could not queue manual crawl.';
    } finally {
        isCrawling.value = false;
    }
}
</script>

<template>
    <Head :title="`Sync versuri · ${props.song.title}`" />

    <div class="min-h-screen bg-zinc-950 text-zinc-100">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 lg:px-8">
            <header class="flex flex-col gap-3 rounded-2xl border border-zinc-800 bg-zinc-900/80 p-5 shadow-2xl shadow-black/20 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-amber-400">MuzicaRap admin</p>
                    <h1 class="text-3xl font-semibold">{{ props.song.title }}</h1>
                    <p class="text-sm text-zinc-400">
                        {{ props.song.artist }}
                        <span v-if="props.song.album">· {{ props.song.album }}</span>
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button
                        class="rounded-xl border border-amber-500/50 bg-amber-500/15 px-4 py-2 text-sm font-medium text-amber-100 transition hover:bg-amber-500/25 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isCrawling"
                        type="button"
                        @click="crawlLyrics"
                    >
                        {{ isCrawling ? 'Queueing crawl…' : 'Manual crawl' }}
                    </button>
                    <button
                        class="rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm font-medium transition hover:bg-zinc-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isResegmenting"
                        type="button"
                        @click="resegmentLyrics"
                    >
                        {{ isResegmenting ? 'Re-segmenting…' : 'Re-segment lyrics' }}
                    </button>
                    <button
                        class="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isSaving"
                        type="button"
                        @click="saveSegments"
                    >
                        {{ isSaving ? 'Saving…' : 'Save sync' }}
                    </button>
                </div>
            </header>

            <div
                v-if="notice.message"
                :class="[
                    'rounded-xl border px-4 py-3 text-sm',
                    notice.type === 'error'
                        ? 'border-rose-500/40 bg-rose-500/10 text-rose-100'
                        : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100',
                ]"
            >
                {{ notice.message }}
            </div>

            <div class="grid gap-6 xl:grid-cols-[1.1fr,0.9fr]">
                <section class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-5">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold">Player + timeline</h2>
                            <p class="text-sm text-zinc-400">
                                Space = stamp start time for current line.
                            </p>
                        </div>
                        <label class="flex items-center gap-3 text-sm text-zinc-300">
                            <span>Zoom</span>
                            <input v-model="zoom" class="accent-amber-400" max="32" min="4" step="1" type="range" />
                            <span class="w-8 text-right text-zinc-500">{{ zoom }}x</span>
                        </label>
                    </div>

                    <div class="mt-5 rounded-2xl border border-zinc-800 bg-zinc-950/70 p-4">
                        <audio
                            v-if="hasAudio"
                            ref="audio"
                            class="w-full"
                            controls
                            :src="props.routes.audio ?? undefined"
                            @loadedmetadata="updateCurrentTime"
                            @timeupdate="updateCurrentTime"
                        />
                        <p v-else class="text-sm text-zinc-500">
                            Song has no audio file yet. You can still segment and save lyrics, but spacebar stamping is disabled.
                        </p>

                        <div class="mt-4 overflow-x-auto pb-3">
                            <div :style="{ width: `${timelineWidth}px` }" class="relative h-24 min-w-full">
                                <div class="absolute inset-x-0 top-8 h-3 rounded-full bg-zinc-800"></div>
                                <div class="absolute left-0 top-8 h-3 rounded-full bg-amber-400/80" :style="{ width: progressWidth() }"></div>
                                <div class="absolute left-0 top-0 text-xs text-zinc-500">
                                    {{ msToClock(currentTimeMs) }} / {{ msToClock(durationMs) }}
                                </div>
                                <div
                                    v-for="(segment, index) in segments"
                                    :key="segment.id ?? `${index}-${segment.text}`"
                                    class="absolute top-4 flex -translate-x-1/2 flex-col items-center gap-2"
                                    :style="{ left: markerLeft(segment) }"
                                >
                                    <button
                                        class="h-4 w-4 rounded-full border-2"
                                        :class="index === activeIndex ? 'border-amber-200 bg-amber-400' : 'border-zinc-400 bg-zinc-800'"
                                        type="button"
                                        @click="selectSegment(index)"
                                    />
                                    <div class="max-w-28 text-center text-[11px] leading-tight text-zinc-400">
                                        {{ segment.position }}. {{ segment.text }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-4">
                            <div class="text-xs uppercase tracking-[0.2em] text-zinc-500">Current line</div>
                            <div class="mt-2 text-sm font-medium">
                                {{ segments[activeIndex]?.text ?? 'No active line' }}
                            </div>
                        </div>
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-4">
                            <div class="text-xs uppercase tracking-[0.2em] text-zinc-500">Current stamp</div>
                            <div class="mt-2 text-sm font-medium">
                                {{ msToClock(segments[activeIndex]?.starts_at_ms ?? null) }}
                            </div>
                        </div>
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-4">
                            <div class="text-xs uppercase tracking-[0.2em] text-zinc-500">Status</div>
                            <div class="mt-2 text-sm font-medium">
                                {{ dirty ? 'Unsaved changes' : 'Saved / clean' }}
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-5">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold">Lyrics + segments</h2>
                            <p class="text-sm text-zinc-400">
                                Edit raw text, then re-segment or stamp lines manually.
                            </p>
                        </div>
                        <span class="rounded-full border border-zinc-700 px-3 py-1 text-xs uppercase tracking-[0.2em] text-zinc-400">
                            {{ props.lyric.source_status ?? 'manual' }}
                        </span>
                    </div>

                    <textarea
                        v-model="lyrics"
                        class="mt-4 h-40 w-full rounded-2xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-zinc-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-400/30"
                        placeholder="Paste or edit the canonical lyrics here…"
                    />

                    <div class="mt-4 max-h-[36rem] space-y-3 overflow-y-auto pr-1">
                        <article
                            v-for="(segment, index) in segments"
                            :id="`segment-${index}`"
                            :key="segment.id ?? `${index}-${segment.text}`"
                            :class="[
                                'rounded-2xl border p-4 transition',
                                index === activeIndex
                                    ? 'border-amber-400 bg-amber-500/10 shadow-lg shadow-amber-500/5'
                                    : 'border-zinc-800 bg-zinc-950/60 hover:border-zinc-700',
                            ]"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <button class="text-left" type="button" @click="selectSegment(index)">
                                    <div class="text-xs uppercase tracking-[0.2em] text-zinc-500">
                                        Line {{ segment.position }}
                                    </div>
                                    <div class="mt-1 text-base font-medium text-zinc-100">
                                        {{ segment.text }}
                                    </div>
                                </button>

                                <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-400">
                                    <button
                                        class="rounded-lg border border-zinc-700 px-3 py-1 transition hover:border-zinc-500 hover:text-zinc-200"
                                        type="button"
                                        @click="resetSegment(index)"
                                    >
                                        Reset
                                    </button>
                                    <button
                                        class="rounded-lg border border-amber-500/40 px-3 py-1 text-amber-100 transition hover:bg-amber-500/10"
                                        type="button"
                                        @click="selectSegment(index)"
                                    >
                                        Focus
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <label class="text-xs uppercase tracking-[0.16em] text-zinc-500">
                                    Starts at
                                    <input
                                        v-model.number="segment.starts_at_ms"
                                        class="mt-2 w-full rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 outline-none focus:border-amber-400"
                                        min="0"
                                        step="1"
                                        type="number"
                                    />
                                </label>
                                <label class="text-xs uppercase tracking-[0.16em] text-zinc-500">
                                    Ends at
                                    <input
                                        v-model.number="segment.ends_at_ms"
                                        class="mt-2 w-full rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 outline-none focus:border-amber-400"
                                        min="0"
                                        step="1"
                                        type="number"
                                    />
                                </label>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
