export type LyricsSegment = {
    id?: number;
    position: number;
    text: string;
    starts_at_ms: number | null;
    ends_at_ms: number | null;
    is_instrumental_gap: boolean;
};

export type LyricsSyncPageProps = {
    song: {
        id: number;
        title: string;
        artist: string;
        album: string | null;
        duration_seconds: number | null;
        audio_path: string | null;
    };
    lyric: {
        id: number | null;
        lyrics: string;
        source_status: string | null;
        synced_at: string | null;
    };
    segments: LyricsSegment[];
    routes: {
        save: string;
        resegment: string;
        crawl: string;
        audio: string | null;
    };
};
