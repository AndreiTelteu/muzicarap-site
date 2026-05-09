import type {
    FavoriteSongEntry,
    PublicArtistSummary,
    PublicPlayerSong,
    PublicSongPageProps,
    PublicSongSummary,
} from '@/types';

export const formatDuration = (seconds: number | null): string => {
    if (seconds === null) {
        return '0:00';
    }

    const minutes = Math.floor(seconds / 60);
    const remainder = seconds % 60;

    return `${minutes}:${remainder.toString().padStart(2, '0')}`;
};

export const formatCount = (value: number, noun: string): string => {
    return `${value} ${noun}`;
};

export const withAutoplay = (url: string): string => {
    return `${url}${url.includes('?') ? '&' : '?'}autoplay=1`;
};

export const toFavoriteSong = (
    song: PublicSongSummary,
    artist?: PublicArtistSummary,
): FavoriteSongEntry => {
    return {
        key: song.url,
        title: song.title,
        slug: song.slug,
        url: song.url,
        parent_type: song.parent_type,
        album: song.album,
        album_url: song.album_url ?? null,
        cover_url: song.cover_url ?? artist?.image_url ?? null,
        duration_seconds: song.duration_seconds,
        youtube_id: song.youtube_id ?? null,
        player_url: song.player_url ?? song.url,
        artist: artist ??
            song.artist ?? { name: 'Artist necunoscut', slug: 'unknown' },
    };
};

export const toPlayerSong = (
    song: PublicSongSummary,
    artist: PublicArtistSummary,
    lyrics: PublicSongPageProps['lyrics'],
): PublicPlayerSong => {
    return {
        ...toFavoriteSong(song, artist),
        lyrics,
    };
};
