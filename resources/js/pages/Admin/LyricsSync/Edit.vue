<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
    type ComponentPublicInstance,
} from 'vue';

import type { LyricsSegment, LyricsSyncPageProps } from '@/types';

const TIMELINE_SIDE_PADDING = 28;
const TIMELINE_MIN_WIDTH = 960;
const ZOOM_MIN = 6;
const ZOOM_MAX = 28;
const NUDGE_STEP_MS = 250;
const AUDIO_VOLUME_STORAGE_KEY = 'muzicarap-admin-lyrics-sync-volume';

const props = defineProps<LyricsSyncPageProps>();

const csrfToken =
    typeof document !== 'undefined'
        ? (document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
              ?.content ?? null)
        : null;

const initialActiveIndex =
    props.segments.length === 0
        ? -1
        : Math.max(
              props.segments.findIndex(
                  (segment) => segment.starts_at_ms === null,
              ),
              0,
          );

const lyrics = ref(props.lyric.lyrics);
const savedLyrics = ref(props.lyric.lyrics);
const segments = ref<LyricsSegment[]>(cloneSegments(props.segments));
const savedSegments = ref<LyricsSegment[]>(cloneSegments(props.segments));
const activeIndex = ref(initialActiveIndex);
const currentTimeMs = ref(0);
const audioDurationMs = ref(0);
const zoom = ref(12);
const volume = ref(0.85);
const isSaving = ref(false);
const isResegmenting = ref(false);
const isCrawling = ref(false);
const isPlaying = ref(false);
const notice = reactive({ type: 'info', message: '' as string });
const audio = ref<HTMLAudioElement | null>(null);
const timelineViewport = ref<HTMLElement | null>(null);
const lyricsList = ref<HTMLElement | null>(null);
const segmentElements = new Map<number, HTMLElement>();
const markerDrag = reactive({
    index: null as number | null,
    pointerId: null as number | null,
    moved: false,
});

let timelineFollowFrame: number | null = null;
let audioSyncFrame: number | null = null;

const durationMs = computed(() => {
    const explicitDuration = props.song.duration_seconds ?? 0;
    const knownDuration = Math.max(
        explicitDuration * 1000,
        audioDurationMs.value,
        currentTimeMs.value + 1000,
    );

    return Math.max(knownDuration, 1);
});

const trackWidth = computed(() =>
    Math.max(1, timelineWidth.value - TIMELINE_SIDE_PADDING * 2),
);
const timelineWidth = computed(() =>
    Math.max(
        TIMELINE_MIN_WIDTH,
        Math.round((durationMs.value / 1000) * zoom.value * 1.6),
    ),
);
const nextUntimedIndex = computed(() =>
    segments.value.findIndex((segment) => segment.starts_at_ms === null),
);
const hasAudio = computed(() => props.routes.audio !== null);
const activeSegment = computed(() => segments.value[activeIndex.value] ?? null);
const timedSegmentsCount = computed(
    () =>
        segments.value.filter((segment) => segment.starts_at_ms !== null)
            .length,
);
const completionPercentage = computed(() => {
    if (segments.value.length === 0) {
        return 0;
    }

    return Math.round((timedSegmentsCount.value / segments.value.length) * 100);
});
const timelineSegments = computed(() =>
    segments.value.filter((segment) => segment.starts_at_ms !== null),
);
const timelineMarkers = computed(() =>
    segments.value
        .map((segment, index) => ({ segment, index }))
        .filter(({ segment }) => segment.starts_at_ms !== null),
);
const dirty = computed(
    () =>
        lyrics.value !== savedLyrics.value ||
        JSON.stringify(segments.value) !== JSON.stringify(savedSegments.value),
);
const tickIntervalMs = computed(() => {
    const pixelsPerSecond =
        trackWidth.value / Math.max(1, durationMs.value / 1000);

    if (pixelsPerSecond >= 88) {
        return 1000;
    }

    if (pixelsPerSecond >= 52) {
        return 2000;
    }

    if (pixelsPerSecond >= 24) {
        return 5000;
    }

    return 10000;
});
const timelineTicks = computed(() => {
    const ticks: number[] = [];

    for (
        let value = 0;
        value <= durationMs.value;
        value += tickIntervalMs.value
    ) {
        ticks.push(value);
    }

    if (ticks[ticks.length - 1] !== durationMs.value) {
        ticks.push(durationMs.value);
    }

    return ticks;
});

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

    scrollActiveSegmentIntoView();

    const segment = segments.value[index];

    queueTimelineFollow(segment?.starts_at_ms ?? currentTimeMs.value);
});

watch(currentTimeMs, (time) => {
    queueTimelineFollow(time);
});

watch(zoom, async () => {
    await nextTick();
    queueTimelineFollow(currentTimeMs.value, true);
});

watch(volume, (value) => {
    applyAudioVolume(value);

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(AUDIO_VOLUME_STORAGE_KEY, value.toString());
    }
});

watch(audio, (element) => {
    if (element === null) {
        stopAudioSyncLoop();

        return;
    }

    applyAudioVolume(volume.value);
    element.load();
    syncAudioState();
});

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    hydrateStoredVolume();
    applyAudioVolume(volume.value);
    queueTimelineFollow(currentTimeMs.value, true);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
    stopAudioSyncLoop();

    if (timelineFollowFrame !== null) {
        window.cancelAnimationFrame(timelineFollowFrame);
    }
});

function cloneSegments(source: LyricsSegment[]): LyricsSegment[] {
    return source.map((segment) => ({ ...segment }));
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
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

function msToCompactClock(value: number): string {
    const totalSeconds = Math.floor(value / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = (totalSeconds % 60).toString().padStart(2, '0');

    return `${minutes}:${seconds}`;
}

function hydrateStoredVolume(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const storedValue = window.localStorage.getItem(AUDIO_VOLUME_STORAGE_KEY);

    if (storedValue === null) {
        return;
    }

    const parsedValue = Number.parseFloat(storedValue);

    if (Number.isNaN(parsedValue)) {
        return;
    }

    volume.value = clamp(parsedValue, 0, 1);
}

function applyAudioVolume(value: number): void {
    if (audio.value === null) {
        return;
    }

    audio.value.volume = clamp(value, 0, 1);
}

function readAudioDurationMs(): number {
    if (audio.value === null) {
        return 0;
    }

    if (Number.isFinite(audio.value.duration) && audio.value.duration > 0) {
        return Math.round(audio.value.duration * 1000);
    }

    if (audio.value.seekable.length > 0) {
        const seekableEnd = audio.value.seekable.end(
            audio.value.seekable.length - 1,
        );

        if (Number.isFinite(seekableEnd) && seekableEnd > 0) {
            return Math.round(seekableEnd * 1000);
        }
    }

    return 0;
}

function syncAudioState(): void {
    if (audio.value === null) {
        currentTimeMs.value = 0;
        audioDurationMs.value = 0;
        isPlaying.value = false;

        return;
    }

    currentTimeMs.value = Math.max(
        0,
        Math.round(audio.value.currentTime * 1000),
    );
    audioDurationMs.value = Math.max(
        audioDurationMs.value,
        readAudioDurationMs(),
        currentTimeMs.value + 1000,
    );
    isPlaying.value = !audio.value.paused;
}

function stopAudioSyncLoop(): void {
    if (audioSyncFrame !== null) {
        window.cancelAnimationFrame(audioSyncFrame);
        audioSyncFrame = null;
    }
}

function startAudioSyncLoop(): void {
    stopAudioSyncLoop();

    const step = () => {
        syncAudioState();

        if (audio.value !== null && !audio.value.paused && !audio.value.ended) {
            audioSyncFrame = window.requestAnimationFrame(step);

            return;
        }

        audioSyncFrame = null;
    };

    step();
}

function handleAudioReady(): void {
    syncAudioState();
    applyAudioVolume(volume.value);
}

function handleAudioPlay(): void {
    syncAudioState();
    startAudioSyncLoop();
}

function handleAudioPause(): void {
    syncAudioState();
    stopAudioSyncLoop();
}

function handleAudioEnded(): void {
    syncAudioState();
    stopAudioSyncLoop();
}

function volumePercentage(): number {
    return Math.round(volume.value * 100);
}

function timeToPixels(value: number): number {
    return (
        TIMELINE_SIDE_PADDING +
        (clamp(value, 0, durationMs.value) / durationMs.value) *
            trackWidth.value
    );
}

function pixelsToTime(value: number): number {
    const normalized = clamp(
        value - TIMELINE_SIDE_PADDING,
        0,
        trackWidth.value,
    );

    return Math.round((normalized / trackWidth.value) * durationMs.value);
}

function playedTrackStyle(): { width: string } {
    return {
        width: `${Math.max(0, timeToPixels(currentTimeMs.value) - TIMELINE_SIDE_PADDING)}px`,
    };
}

function playheadStyle(): { left: string } {
    return {
        left: `${timeToPixels(currentTimeMs.value)}px`,
    };
}

function tickStyle(value: number): { left: string } {
    return {
        left: `${timeToPixels(value)}px`,
    };
}

function markerStyle(segment: LyricsSegment): { left: string } {
    return {
        left: `${timeToPixels(segment.starts_at_ms ?? 0)}px`,
    };
}

function timelineTimeFromClientX(clientX: number): number {
    const viewport = timelineViewport.value;

    if (!viewport) {
        return 0;
    }

    const rect = viewport.getBoundingClientRect();
    const pointerX = clientX - rect.left + viewport.scrollLeft;

    return pixelsToTime(pointerX);
}

function queueTimelineFollow(
    time: number,
    force = false,
    behavior: ScrollBehavior = 'smooth',
): void {
    if (timelineFollowFrame !== null) {
        window.cancelAnimationFrame(timelineFollowFrame);
    }

    timelineFollowFrame = window.requestAnimationFrame(() => {
        timelineFollowFrame = null;
        ensureTimelineTimeVisible(time, force, behavior);
    });
}

function ensureTimelineTimeVisible(
    time: number,
    force = false,
    behavior: ScrollBehavior = 'smooth',
): void {
    const viewport = timelineViewport.value;

    if (!viewport) {
        return;
    }

    const markerPosition = timeToPixels(time);
    const currentLeft = viewport.scrollLeft;
    const currentRight = currentLeft + viewport.clientWidth;
    const safePadding = viewport.clientWidth * 0.22;
    const targetLeft = clamp(
        markerPosition - viewport.clientWidth * 0.35,
        0,
        Math.max(0, timelineWidth.value - viewport.clientWidth),
    );

    if (
        force ||
        markerPosition < currentLeft + safePadding ||
        markerPosition > currentRight - safePadding
    ) {
        viewport.scrollTo({
            left: targetLeft,
            behavior,
        });
    }
}

function scrollActiveSegmentIntoView(): void {
    const container = lyricsList.value;
    const element = segmentElements.get(activeIndex.value);

    if (!container || !element) {
        return;
    }

    const targetTop =
        element.offsetTop -
        container.clientHeight / 2 +
        element.clientHeight / 2;

    container.scrollTo({
        top: Math.max(0, targetTop),
        behavior: 'smooth',
    });
}

function setSegmentElement(
    index: number,
    element: Element | ComponentPublicInstance | null,
): void {
    if (element instanceof HTMLElement) {
        segmentElements.set(index, element);

        return;
    }

    if (element && '$el' in element && element.$el instanceof HTMLElement) {
        segmentElements.set(index, element.$el);

        return;
    }

    segmentElements.delete(index);
}

function updateCurrentTime(): void {
    syncAudioState();
}

function togglePlayback(): void {
    if (audio.value === null) {
        return;
    }

    if (audio.value.paused) {
        void audio.value.play().catch(() => {
            notice.type = 'error';
            notice.message = 'Playback could not start in the browser.';
        });

        startAudioSyncLoop();

        return;
    }

    audio.value.pause();
}

function seekToMs(value: number): void {
    const nextValue = clamp(value, 0, durationMs.value);

    currentTimeMs.value = nextValue;

    if (audio.value !== null) {
        audio.value.currentTime = nextValue / 1000;
    }

    queueTimelineFollow(nextValue, true);
}

function seekByMs(delta: number): void {
    seekToMs(currentTimeMs.value + delta);
}

function seekFromTimeline(event: MouseEvent): void {
    if (markerDrag.moved) {
        markerDrag.moved = false;

        return;
    }

    seekToMs(timelineTimeFromClientX(event.clientX));
}

function setSegmentStart(index: number, timestamp: number): void {
    const segment = segments.value[index];

    if (!segment) {
        return;
    }

    const boundedTimestamp = clamp(timestamp, 0, durationMs.value);
    const previousSegment = segments.value[index - 1];
    const previousStart = segment.starts_at_ms;

    segment.starts_at_ms = boundedTimestamp;

    if (segment.ends_at_ms !== null && segment.ends_at_ms < boundedTimestamp) {
        segment.ends_at_ms = boundedTimestamp;
    }

    if (
        previousSegment &&
        (previousSegment.ends_at_ms === null ||
            previousSegment.ends_at_ms === previousStart)
    ) {
        previousSegment.ends_at_ms = boundedTimestamp;
    }
}

function syncFromCurrentTime(): void {
    if (activeIndex.value < 0) {
        return;
    }

    const segment = segments.value[activeIndex.value];

    if (!segment) {
        return;
    }

    const timestamp = currentTimeMs.value;

    setSegmentStart(activeIndex.value, timestamp);

    const nextIndex = activeIndex.value + 1;
    activeIndex.value =
        nextIndex < segments.value.length
            ? nextIndex
            : segments.value.length - 1;
    notice.type = 'info';
    notice.message = `Stamped line ${segment.position} at ${msToClock(timestamp)}.`;
}

function stampSpecificSegment(index: number): void {
    activeIndex.value = index;
    syncFromCurrentTime();
}

function nudgeSegment(index: number, delta: number): void {
    const segment = segments.value[index];

    if (!segment) {
        return;
    }

    const referencePoint = segment.starts_at_ms ?? currentTimeMs.value;

    setSegmentStart(index, referencePoint + delta);
    activeIndex.value = index;

    if (segments.value[index]?.starts_at_ms !== null) {
        seekToMs(segments.value[index].starts_at_ms ?? 0);
    }
}

function syncDraggedMarker(index: number, clientX: number): void {
    const timestamp = timelineTimeFromClientX(clientX);

    setSegmentStart(index, timestamp);
    activeIndex.value = index;
    queueTimelineFollow(timestamp, false, 'auto');
}

function startMarkerDrag(index: number, event: PointerEvent): void {
    if (event.button !== 0) {
        return;
    }

    const target = event.currentTarget;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    markerDrag.index = index;
    markerDrag.pointerId = event.pointerId;
    markerDrag.moved = false;
    activeIndex.value = index;
    target.setPointerCapture(event.pointerId);
    event.preventDefault();
}

function moveMarkerDrag(index: number, event: PointerEvent): void {
    if (
        markerDrag.index !== index ||
        markerDrag.pointerId !== event.pointerId
    ) {
        return;
    }

    markerDrag.moved = true;
    syncDraggedMarker(index, event.clientX);
}

function endMarkerDrag(index: number, event: PointerEvent): void {
    if (
        markerDrag.index !== index ||
        markerDrag.pointerId !== event.pointerId
    ) {
        return;
    }

    const target = event.currentTarget;

    if (
        target instanceof HTMLElement &&
        target.hasPointerCapture(event.pointerId)
    ) {
        target.releasePointerCapture(event.pointerId);
    }

    if (markerDrag.moved) {
        const segment = segments.value[index];

        if (segment?.starts_at_ms !== null) {
            notice.type = 'info';
            notice.message = `Adjusted line ${segment.position} to ${msToClock(segment.starts_at_ms)}.`;
        }
    }

    markerDrag.index = null;
    markerDrag.pointerId = null;
    markerDrag.moved = false;
}

function handleKeydown(event: KeyboardEvent): void {
    const target = event.target as HTMLElement | null;

    if (
        target &&
        (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) ||
            target.isContentEditable)
    ) {
        return;
    }

    if (event.code === 'Space') {
        event.preventDefault();
        syncFromCurrentTime();
    }

    if (event.code === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = Math.min(
            activeIndex.value + 1,
            segments.value.length - 1,
        );
    }

    if (event.code === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
    }
}

function selectSegment(index: number): void {
    activeIndex.value = index;

    const segment = segments.value[index];

    if (segment?.starts_at_ms !== null) {
        seekToMs(segment.starts_at_ms);
    }
}

function resetSegment(index: number): void {
    const segment = segments.value[index];

    if (!segment) {
        return;
    }

    const previousSegment = segments.value[index - 1];
    const previousStart = segment.starts_at_ms;

    segment.starts_at_ms = null;
    segment.ends_at_ms = null;

    if (previousSegment && previousSegment.ends_at_ms === previousStart) {
        previousSegment.ends_at_ms = null;
    }

    activeIndex.value = index;
}

async function requestJson(
    url: string,
    method: 'POST' | 'PUT',
    payload: unknown,
) {
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
        const data = (await response.json().catch(() => null)) as {
            message?: string;
        } | null;

        throw new Error(data?.message ?? fallbackMessage);
    }

    return response.json();
}

async function saveSegments(): Promise<void> {
    isSaving.value = true;
    notice.type = 'info';
    notice.message = '';

    try {
        const data = (await requestJson(props.routes.save, 'PUT', {
            lyrics: lyrics.value,
            segments: segments.value,
        })) as {
            lyric: LyricsSyncPageProps['lyric'] & { updated_at: string | null };
            segments: LyricsSegment[];
        };

        lyrics.value = data.lyric.lyrics;
        savedLyrics.value = data.lyric.lyrics;
        segments.value = cloneSegments(data.segments);
        savedSegments.value = cloneSegments(data.segments);
        notice.type = 'success';
        notice.message = data.lyric.synced_at
            ? `Saved. Fully synced at ${new Date(data.lyric.synced_at).toLocaleString()}.`
            : 'Saved draft timestamps.';
    } catch (error) {
        notice.type = 'error';
        notice.message =
            error instanceof Error
                ? error.message
                : 'Could not save lyrics sync data.';
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
        notice.message =
            error instanceof Error
                ? error.message
                : 'Could not re-segment lyrics.';
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
        notice.message =
            error instanceof Error
                ? error.message
                : 'Could not queue manual crawl.';
    } finally {
        isCrawling.value = false;
    }
}
</script>

<template>
    <Head :title="`Sync versuri · ${props.song.title}`" />

    <div class="min-h-screen bg-zinc-950 text-zinc-100">
        <div
            class="mx-auto flex max-w-[1600px] flex-col gap-5 px-4 py-5 lg:px-8"
        >
            <header
                class="flex flex-col gap-4 rounded-[28px] border border-zinc-800 bg-zinc-900/80 p-5 shadow-[0_24px_80px_-48px_rgba(0,0,0,0.85)] lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="min-w-0">
                    <p
                        class="text-xs tracking-[0.35em] text-amber-400 uppercase"
                    >
                        MuzicaRap admin
                    </p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                        {{ props.song.title }}
                    </h1>
                    <p class="mt-1 text-sm text-zinc-400">
                        {{ props.song.artist }}
                        <span v-if="props.song.album"
                            >· {{ props.song.album }}</span
                        >
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button
                        class="rounded-2xl border border-amber-500/50 bg-amber-500/15 px-4 py-2.5 text-sm font-medium text-amber-100 transition hover:bg-amber-500/25 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isCrawling"
                        type="button"
                        @click="crawlLyrics"
                    >
                        {{ isCrawling ? 'Queueing crawl…' : 'Manual crawl' }}
                    </button>
                    <button
                        class="rounded-2xl border border-zinc-700 bg-zinc-800 px-4 py-2.5 text-sm font-medium transition hover:bg-zinc-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isResegmenting"
                        type="button"
                        @click="resegmentLyrics"
                    >
                        {{
                            isResegmenting
                                ? 'Re-segmenting…'
                                : 'Re-segment lyrics'
                        }}
                    </button>
                    <button
                        class="rounded-2xl bg-emerald-400 px-4 py-2.5 text-sm font-semibold text-zinc-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:opacity-50"
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
                    'rounded-2xl border px-4 py-3 text-sm',
                    notice.type === 'error'
                        ? 'border-rose-500/40 bg-rose-500/10 text-rose-100'
                        : notice.type === 'success'
                          ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100'
                          : 'border-amber-500/40 bg-amber-500/10 text-amber-100',
                ]"
            >
                {{ notice.message }}
            </div>

            <section class="sticky top-4 z-30">
                <div
                    class="rounded-[30px] border border-zinc-800/90 bg-zinc-900/85 p-4 shadow-[0_28px_90px_-44px_rgba(0,0,0,0.92)] backdrop-blur-xl lg:p-5"
                >
                    <audio
                        v-if="hasAudio"
                        ref="audio"
                        class="hidden"
                        preload="metadata"
                        :src="props.routes.audio ?? undefined"
                        @canplay="handleAudioReady"
                        @durationchange="handleAudioReady"
                        @ended="handleAudioEnded"
                        @loadeddata="handleAudioReady"
                        @loadedmetadata="handleAudioReady"
                        @pause="handleAudioPause"
                        @play="handleAudioPlay"
                        @playing="handleAudioPlay"
                        @seeked="handleAudioReady"
                        @timeupdate="updateCurrentTime"
                    />

                    <div
                        class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
                    >
                        <div class="flex min-w-0 flex-1 flex-col gap-3">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <button
                                    class="rounded-full bg-emerald-400 px-5 py-2.5 text-sm font-semibold text-zinc-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!hasAudio"
                                    type="button"
                                    @click="togglePlayback"
                                >
                                    {{ isPlaying ? 'Pause' : 'Play' }}
                                </button>
                                <button
                                    class="rounded-full border border-zinc-700 bg-zinc-950/70 px-3 py-2 text-xs font-medium text-zinc-300 transition hover:border-zinc-500 hover:text-zinc-100 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!hasAudio"
                                    type="button"
                                    @click="seekByMs(-2000)"
                                >
                                    -2s
                                </button>
                                <button
                                    class="rounded-full border border-zinc-700 bg-zinc-950/70 px-3 py-2 text-xs font-medium text-zinc-300 transition hover:border-zinc-500 hover:text-zinc-100 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!hasAudio"
                                    type="button"
                                    @click="seekByMs(2000)"
                                >
                                    +2s
                                </button>
                                <div
                                    class="rounded-full border border-zinc-800 bg-zinc-950/80 px-4 py-2 font-mono text-sm text-zinc-200"
                                >
                                    {{ msToClock(currentTimeMs) }} /
                                    {{ msToClock(durationMs) }}
                                </div>
                                <div
                                    class="rounded-full border border-zinc-800 bg-zinc-950/80 px-4 py-2 text-xs tracking-[0.22em] text-zinc-400 uppercase"
                                >
                                    Space = stamp
                                </div>
                                <div
                                    class="rounded-full border border-zinc-800 bg-zinc-950/80 px-4 py-2 text-xs tracking-[0.22em] text-zinc-400 uppercase"
                                >
                                    Arrows = navigate
                                </div>
                                <label
                                    class="flex items-center gap-3 rounded-full border border-zinc-800 bg-zinc-950/80 px-4 py-2"
                                >
                                    <span
                                        class="text-xs tracking-[0.22em] text-zinc-400 uppercase"
                                        >Vol</span
                                    >
                                    <input
                                        v-model.number="volume"
                                        class="w-24 accent-emerald-400"
                                        max="1"
                                        min="0"
                                        step="0.01"
                                        type="range"
                                    />
                                    <span
                                        class="w-9 text-right font-mono text-xs text-zinc-300"
                                        >{{ volumePercentage() }}%</span
                                    >
                                </label>
                            </div>

                            <div class="grid gap-3 md:grid-cols-3">
                                <div
                                    class="rounded-2xl border border-zinc-800 bg-zinc-950/70 px-4 py-3"
                                >
                                    <div
                                        class="text-[11px] tracking-[0.24em] text-zinc-500 uppercase"
                                    >
                                        Current line
                                    </div>
                                    <div
                                        class="mt-2 truncate text-sm font-medium text-zinc-100"
                                    >
                                        {{
                                            activeSegment?.text ??
                                            'No active line selected'
                                        }}
                                    </div>
                                </div>
                                <div
                                    class="rounded-2xl border border-zinc-800 bg-zinc-950/70 px-4 py-3"
                                >
                                    <div
                                        class="text-[11px] tracking-[0.24em] text-zinc-500 uppercase"
                                    >
                                        Selected stamp
                                    </div>
                                    <div
                                        class="mt-2 font-mono text-sm text-zinc-100"
                                    >
                                        {{
                                            msToClock(
                                                activeSegment?.starts_at_ms ??
                                                    null,
                                            )
                                        }}
                                    </div>
                                </div>
                                <div
                                    class="rounded-2xl border border-zinc-800 bg-zinc-950/70 px-4 py-3"
                                >
                                    <div
                                        class="text-[11px] tracking-[0.24em] text-zinc-500 uppercase"
                                    >
                                        Sync status
                                    </div>
                                    <div
                                        class="mt-2 text-sm font-medium text-zinc-100"
                                    >
                                        {{ timedSegmentsCount }}/{{
                                            segments.length
                                        }}
                                        stamped ·
                                        {{
                                            dirty ? 'Unsaved changes' : 'Saved'
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex w-full max-w-sm flex-col gap-3 rounded-3xl border border-zinc-800 bg-zinc-950/60 p-4"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <div
                                        class="text-[11px] tracking-[0.24em] text-zinc-500 uppercase"
                                    >
                                        Timeline zoom
                                    </div>
                                    <div class="mt-1 text-sm text-zinc-300">
                                        Spread markers for tighter timestamp
                                        edits.
                                    </div>
                                </div>
                                <div class="font-mono text-sm text-zinc-400">
                                    {{ zoom }}x
                                </div>
                            </div>
                            <input
                                v-model.number="zoom"
                                class="accent-amber-400"
                                :max="ZOOM_MAX"
                                :min="ZOOM_MIN"
                                step="1"
                                type="range"
                            />
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    class="rounded-2xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs font-medium text-zinc-300 transition hover:border-zinc-500 hover:text-zinc-100"
                                    type="button"
                                    @click="
                                        nudgeSegment(
                                            activeIndex,
                                            -NUDGE_STEP_MS,
                                        )
                                    "
                                >
                                    -250ms
                                </button>
                                <button
                                    class="rounded-2xl border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs font-medium text-amber-100 transition hover:bg-amber-500/20"
                                    type="button"
                                    @click="syncFromCurrentTime"
                                >
                                    Set now
                                </button>
                                <button
                                    class="rounded-2xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs font-medium text-zinc-300 transition hover:border-zinc-500 hover:text-zinc-100"
                                    type="button"
                                    @click="
                                        nudgeSegment(activeIndex, NUDGE_STEP_MS)
                                    "
                                >
                                    +250ms
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-4 rounded-[28px] border border-zinc-800 bg-zinc-950/75 p-4"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <div>
                                <h2
                                    class="text-lg font-semibold tracking-tight"
                                >
                                    Compact player timeline
                                </h2>
                                <p class="text-sm text-zinc-400">
                                    Click anywhere to seek. Each stamped line
                                    gets its own marker and timestamp. Drag a
                                    marker to retime it directly.
                                </p>
                            </div>
                            <div
                                class="flex flex-wrap gap-2 text-xs tracking-[0.2em] text-zinc-400 uppercase"
                            >
                                <span
                                    class="rounded-full border border-zinc-700 px-3 py-1"
                                    >{{ completionPercentage }}% complete</span
                                >
                                <span
                                    class="rounded-full border border-zinc-700 px-3 py-1"
                                    >{{ timelineSegments.length }} placed</span
                                >
                                <span
                                    class="rounded-full border border-zinc-700 px-3 py-1"
                                    >{{
                                        segments.length -
                                        timelineSegments.length
                                    }}
                                    pending</span
                                >
                            </div>
                        </div>

                        <div
                            ref="timelineViewport"
                            class="mt-4 overflow-x-auto pb-2"
                            @click="seekFromTimeline"
                        >
                            <div
                                :style="{ width: `${timelineWidth}px` }"
                                class="relative h-40 min-w-full cursor-pointer select-none"
                            >
                                <div
                                    v-for="tick in timelineTicks"
                                    :key="`tick-${tick}`"
                                    class="absolute top-0 flex -translate-x-1/2 flex-col items-center"
                                    :style="tickStyle(tick)"
                                >
                                    <span
                                        class="font-mono text-[10px] text-zinc-500"
                                        >{{ msToCompactClock(tick) }}</span
                                    >
                                    <span
                                        class="mt-2 h-4 w-px bg-zinc-700"
                                    ></span>
                                </div>

                                <div
                                    class="absolute inset-x-0 top-16 h-3 rounded-full bg-zinc-800"
                                ></div>
                                <div
                                    class="absolute top-16 left-[28px] h-3 rounded-full bg-emerald-400/85"
                                    :style="playedTrackStyle()"
                                ></div>

                                <div
                                    class="absolute top-3 z-20 -translate-x-1/2"
                                    :style="playheadStyle()"
                                >
                                    <div
                                        class="rounded-full bg-white px-2 py-1 font-mono text-[10px] font-semibold text-zinc-950 shadow-lg shadow-black/30"
                                    >
                                        {{ msToCompactClock(currentTimeMs) }}
                                    </div>
                                    <div
                                        class="mx-auto mt-2 h-24 w-px bg-white/80"
                                    ></div>
                                </div>

                                <button
                                    v-for="(
                                        { segment, index }, markerIndex
                                    ) in timelineMarkers"
                                    :key="
                                        segment.id ??
                                        `marker-${markerIndex}-${segment.position}`
                                    "
                                    class="absolute top-6 z-10 flex -translate-x-1/2 touch-none flex-col items-center gap-1.5"
                                    :class="
                                        markerDrag.index === index
                                            ? 'cursor-grabbing'
                                            : 'cursor-grab'
                                    "
                                    :style="markerStyle(segment)"
                                    :title="`${segment.text} · drag pentru ajustare fină`"
                                    type="button"
                                    @click.stop="selectSegment(index)"
                                    @pointercancel="
                                        endMarkerDrag(index, $event)
                                    "
                                    @pointerdown.stop="
                                        startMarkerDrag(index, $event)
                                    "
                                    @pointermove.stop="
                                        moveMarkerDrag(index, $event)
                                    "
                                    @pointerup.stop="
                                        endMarkerDrag(index, $event)
                                    "
                                >
                                    <span
                                        class="inline-flex min-w-9 items-center justify-center rounded-full border px-2.5 py-1 text-[11px] font-semibold transition"
                                        :class="
                                            markerDrag.index === index
                                                ? 'border-emerald-300 bg-emerald-300 text-zinc-950 shadow-lg shadow-emerald-500/20'
                                                : segment.position ===
                                                    activeSegment?.position
                                                  ? 'border-amber-300 bg-amber-400 text-zinc-950 shadow-lg shadow-amber-500/20'
                                                  : 'border-zinc-600 bg-zinc-900 text-zinc-200 hover:border-zinc-400'
                                        "
                                    >
                                        {{ segment.position }}
                                    </span>
                                    <span
                                        class="h-8 w-px"
                                        :class="
                                            markerDrag.index === index
                                                ? 'bg-emerald-300'
                                                : segment.position ===
                                                    activeSegment?.position
                                                  ? 'bg-amber-300'
                                                  : 'bg-zinc-600'
                                        "
                                    ></span>
                                    <span
                                        class="rounded-full border px-2 py-0.5 font-mono text-[10px]"
                                        :class="
                                            markerDrag.index === index
                                                ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100'
                                                : segment.position ===
                                                    activeSegment?.position
                                                  ? 'border-amber-500/40 bg-amber-500/10 text-amber-100'
                                                  : 'border-zinc-700 bg-zinc-950/90 text-zinc-400'
                                        "
                                    >
                                        {{
                                            msToCompactClock(
                                                segment.starts_at_ms ?? 0,
                                            )
                                        }}
                                    </span>
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="!hasAudio"
                            class="mt-3 rounded-2xl border border-zinc-800 bg-zinc-900/70 px-4 py-3 text-sm text-zinc-400"
                        >
                            No audio file is attached yet. You can still click
                            the timeline, place manual timestamps, and save the
                            sync.
                        </div>
                    </div>
                </div>
            </section>

            <div
                class="grid gap-5 xl:grid-cols-[minmax(340px,0.85fr)_minmax(0,1.15fr)]"
            >
                <section
                    class="rounded-[28px] border border-zinc-800 bg-zinc-900/80 p-5"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold tracking-tight">
                                Canonical lyrics
                            </h2>
                            <p class="text-sm text-zinc-400">
                                Edit the source text here, then re-segment it
                                into stampable lines.
                            </p>
                        </div>
                        <span
                            class="rounded-full border border-zinc-700 px-3 py-1 text-xs tracking-[0.2em] text-zinc-400 uppercase"
                        >
                            {{ props.lyric.source_status ?? 'manual' }}
                        </span>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div
                            class="rounded-2xl border border-zinc-800 bg-zinc-950/70 px-4 py-3"
                        >
                            <div
                                class="text-[11px] tracking-[0.24em] text-zinc-500 uppercase"
                            >
                                Lines
                            </div>
                            <div class="mt-2 text-sm font-medium text-zinc-100">
                                {{ segments.length }}
                            </div>
                        </div>
                        <div
                            class="rounded-2xl border border-zinc-800 bg-zinc-950/70 px-4 py-3"
                        >
                            <div
                                class="text-[11px] tracking-[0.24em] text-zinc-500 uppercase"
                            >
                                Stamped
                            </div>
                            <div class="mt-2 text-sm font-medium text-zinc-100">
                                {{ timedSegmentsCount }}
                            </div>
                        </div>
                        <div
                            class="rounded-2xl border border-zinc-800 bg-zinc-950/70 px-4 py-3"
                        >
                            <div
                                class="text-[11px] tracking-[0.24em] text-zinc-500 uppercase"
                            >
                                Pending
                            </div>
                            <div class="mt-2 text-sm font-medium text-zinc-100">
                                {{ segments.length - timedSegmentsCount }}
                            </div>
                        </div>
                    </div>

                    <textarea
                        v-model="lyrics"
                        class="mt-4 h-[26rem] w-full rounded-[26px] border border-zinc-700 bg-zinc-950 px-4 py-4 text-sm leading-7 text-zinc-100 transition outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/30"
                        placeholder="Paste or edit the canonical lyrics here…"
                    />
                </section>

                <section
                    class="rounded-[28px] border border-zinc-800 bg-zinc-900/80 p-5"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-4"
                    >
                        <div>
                            <h2 class="text-lg font-semibold tracking-tight">
                                Lyrics segments
                            </h2>
                            <p class="text-sm text-zinc-400">
                                The active line stays centered while you stamp
                                with
                                <span class="font-mono text-zinc-200"
                                    >Space</span
                                >.
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap gap-2 text-xs tracking-[0.2em] text-zinc-400 uppercase"
                        >
                            <span
                                class="rounded-full border border-zinc-700 px-3 py-1"
                                >Autoscroll on</span
                            >
                            <span
                                class="rounded-full border border-zinc-700 px-3 py-1"
                                >Fine nudge ready</span
                            >
                        </div>
                    </div>

                    <div
                        v-if="segments.length === 0"
                        class="mt-4 rounded-[24px] border border-dashed border-zinc-700 bg-zinc-950/60 px-5 py-10 text-center text-sm text-zinc-400"
                    >
                        No segments yet. Add lyrics on the left, then re-segment
                        to start syncing.
                    </div>

                    <div
                        v-else
                        ref="lyricsList"
                        class="mt-4 max-h-[calc(100vh-18rem)] space-y-3 overflow-y-auto pr-1"
                    >
                        <article
                            v-for="(segment, index) in segments"
                            :key="segment.id ?? `${index}-${segment.text}`"
                            :ref="
                                (element) => setSegmentElement(index, element)
                            "
                            :class="[
                                'rounded-[26px] border p-4 transition',
                                index === activeIndex
                                    ? 'border-amber-400 bg-amber-500/10 shadow-[0_20px_40px_-28px_rgba(251,191,36,0.65)]'
                                    : 'border-zinc-800 bg-zinc-950/60 hover:border-zinc-700',
                            ]"
                        >
                            <div
                                class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
                            >
                                <button
                                    class="min-w-0 flex-1 text-left"
                                    type="button"
                                    @click="selectSegment(index)"
                                >
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="inline-flex h-9 min-w-9 items-center justify-center rounded-full border text-sm font-semibold"
                                            :class="
                                                index === activeIndex
                                                    ? 'border-amber-300 bg-amber-400 text-zinc-950'
                                                    : 'border-zinc-700 bg-zinc-900 text-zinc-300'
                                            "
                                        >
                                            {{ segment.position }}
                                        </span>
                                        <div class="min-w-0">
                                            <div
                                                class="truncate text-base font-medium text-zinc-100"
                                            >
                                                {{ segment.text }}
                                            </div>
                                            <div
                                                class="mt-2 flex flex-wrap gap-2 text-xs text-zinc-400"
                                            >
                                                <span
                                                    class="rounded-full border border-zinc-700 px-2.5 py-1 font-mono"
                                                >
                                                    Start
                                                    {{
                                                        msToClock(
                                                            segment.starts_at_ms,
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    class="rounded-full border border-zinc-700 px-2.5 py-1 font-mono"
                                                >
                                                    End
                                                    {{
                                                        msToClock(
                                                            segment.ends_at_ms,
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    v-if="
                                                        segment.starts_at_ms ===
                                                        null
                                                    "
                                                    class="rounded-full border border-amber-500/40 bg-amber-500/10 px-2.5 py-1 text-amber-100"
                                                >
                                                    Needs stamp
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </button>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        class="rounded-2xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs font-medium text-zinc-300 transition hover:border-zinc-500 hover:text-zinc-100"
                                        type="button"
                                        @click="selectSegment(index)"
                                    >
                                        Focus
                                    </button>
                                    <button
                                        class="rounded-2xl border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs font-medium text-amber-100 transition hover:bg-amber-500/20"
                                        type="button"
                                        @click="stampSpecificSegment(index)"
                                    >
                                        Use now
                                    </button>
                                    <button
                                        class="rounded-2xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs font-medium text-zinc-300 transition hover:border-zinc-500 hover:text-zinc-100"
                                        type="button"
                                        @click="resetSegment(index)"
                                    >
                                        Reset
                                    </button>
                                </div>
                            </div>

                            <div
                                class="mt-4 grid gap-3 lg:grid-cols-[1fr_1fr_auto]"
                            >
                                <label
                                    class="text-xs tracking-[0.16em] text-zinc-500 uppercase"
                                >
                                    Starts at
                                    <input
                                        v-model.number="segment.starts_at_ms"
                                        class="mt-2 w-full rounded-2xl border border-zinc-700 bg-zinc-900 px-3 py-2.5 font-mono text-sm text-zinc-100 transition outline-none focus:border-amber-400"
                                        min="0"
                                        step="1"
                                        type="number"
                                    />
                                </label>
                                <label
                                    class="text-xs tracking-[0.16em] text-zinc-500 uppercase"
                                >
                                    Ends at
                                    <input
                                        v-model.number="segment.ends_at_ms"
                                        class="mt-2 w-full rounded-2xl border border-zinc-700 bg-zinc-900 px-3 py-2.5 font-mono text-sm text-zinc-100 transition outline-none focus:border-amber-400"
                                        min="0"
                                        step="1"
                                        type="number"
                                    />
                                </label>
                                <div
                                    class="flex flex-wrap items-end gap-2 lg:justify-end"
                                >
                                    <button
                                        class="rounded-2xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs font-medium text-zinc-300 transition hover:border-zinc-500 hover:text-zinc-100"
                                        type="button"
                                        @click="
                                            nudgeSegment(index, -NUDGE_STEP_MS)
                                        "
                                    >
                                        -250ms
                                    </button>
                                    <button
                                        class="rounded-2xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs font-medium text-zinc-300 transition hover:border-zinc-500 hover:text-zinc-100"
                                        type="button"
                                        @click="
                                            nudgeSegment(index, NUDGE_STEP_MS)
                                        "
                                    >
                                        +250ms
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
