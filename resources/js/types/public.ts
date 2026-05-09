import type { LyricsSegment } from './lyrics';

export type CatalogLink = {
    url: string;
};

export type PublicArtistSummary = {
    name: string;
    slug: string;
    bio?: string | null;
    image_url?: string | null;
    url?: string;
};

export type PublicSongSummary = {
    title: string;
    slug: string;
    parent_type: string;
    album: string | null;
    album_slug?: string | null;
    album_url?: string | null;
    cover_url?: string | null;
    duration_seconds: number | null;
    youtube_id?: string | null;
    player_url?: string;
    url: string;
    created_at?: string | null;
    artist?: PublicArtistSummary;
};

export type PublicAlbumSummary = {
    title: string;
    slug: string;
    type: string;
    release_date: string | null;
    songs_count: number | null;
    cover_url?: string | null;
    description?: string | null;
    artist?: PublicArtistSummary;
    url: string;
};

export type PublicHomePageProps = {
    latestSongs: Array<
        PublicSongSummary & {
            artist: PublicArtistSummary;
        }
    >;
    featuredArtists: Array<
        PublicArtistSummary & {
            songs_count: number;
            albums_count: number;
        }
    >;
};

export type PublicArtistsIndexPageProps = {
    artists: Array<
        PublicArtistSummary & {
            songs_count: number;
            albums_count: number;
        }
    >;
};

export type PublicArtistPageProps = {
    artist: PublicArtistSummary;
    albums: PublicAlbumSummary[];
    songs: PublicSongSummary[];
};

export type PublicAlbumPageProps = {
    artist: PublicArtistSummary;
    album: PublicAlbumSummary;
    tracks: Array<
        Pick<
            PublicSongSummary,
            | 'title'
            | 'slug'
            | 'duration_seconds'
            | 'url'
            | 'cover_url'
            | 'youtube_id'
            | 'artist'
            | 'album'
            | 'album_url'
            | 'parent_type'
        > & {
            track_number: number | null;
        }
    >;
};

export type PublicSongPageProps = {
    artist: PublicArtistSummary;
    song: PublicSongSummary;
    lyrics: {
        text: string;
        is_synced: boolean;
        segments: LyricsSegment[];
    };
};

export type PublicSearchResults = {
    query: string;
    artists: PublicArtistSummary[];
    albums: PublicAlbumSummary[];
    songs: PublicSongSummary[];
};

export type FavoriteSongEntry = {
    key: string;
    title: string;
    slug: string;
    url: string;
    parent_type: string;
    album: string | null;
    album_url: string | null;
    cover_url: string | null;
    duration_seconds: number | null;
    youtube_id: string | null;
    player_url: string;
    artist: PublicArtistSummary;
};

export type PublicPlayerSong = FavoriteSongEntry & {
    lyrics: PublicSongPageProps['lyrics'];
};
