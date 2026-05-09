import type { LyricsSegment } from './lyrics';

export type CatalogLink = {
    url: string;
};

export type PublicArtistSummary = {
    name: string;
    slug: string;
    url?: string;
};

export type PublicSongSummary = {
    title: string;
    slug: string;
    parent_type: string;
    album: string | null;
    duration_seconds: number | null;
    url: string;
    created_at?: string | null;
    artist?: PublicArtistSummary;
};

export type PublicAlbumSummary = {
    title: string;
    slug: string;
    type: string;
    release_date: string | null;
    songs_count: number;
    url: string;
};

export type PublicHomePageProps = {
    latestSongs: Array<
        PublicSongSummary & {
            artist: PublicArtistSummary;
        }
    >;
};

export type PublicArtistPageProps = {
    artist: {
        name: string;
        slug: string;
        bio: string | null;
        image_path: string | null;
    };
    albums: PublicAlbumSummary[];
    songs: PublicSongSummary[];
};

export type PublicAlbumPageProps = {
    artist: {
        name: string;
        slug: string;
        url: string;
    };
    album: {
        title: string;
        slug: string;
        type: string;
        description: string | null;
        release_date: string | null;
        cover_path: string | null;
    };
    tracks: Array<
        Pick<
            PublicSongSummary,
            'title' | 'slug' | 'duration_seconds' | 'url'
        > & {
            track_number: number | null;
        }
    >;
};

export type PublicSongPageProps = {
    artist: {
        name: string;
        slug: string;
        url: string;
    };
    song: {
        title: string;
        slug: string;
        album: string | null;
        album_url: string | null;
        duration_seconds: number | null;
        parent_type: string;
        youtube_id: string | null;
    };
    lyrics: {
        text: string;
        is_synced: boolean;
        segments: LyricsSegment[];
    };
};
