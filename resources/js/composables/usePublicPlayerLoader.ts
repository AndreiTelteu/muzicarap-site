import { usePublicPlayer } from '@/composables/usePublicPlayer';
import { toPlayerSong } from '@/lib/public-catalog';
import type { PublicSongPageProps, PublicSongSummary } from '@/types';

export const usePublicPlayerLoader = () => {
    const player = usePublicPlayer();

    const openFromSummary = async (song: PublicSongSummary): Promise<void> => {
        if (!song.youtube_id || !song.player_url) {
            return;
        }

        player.setLoading(true);

        const response = await fetch(song.player_url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            player.setLoading(false);

            return;
        }

        const payload = (await response.json()) as PublicSongPageProps;

        player.openSong(
            toPlayerSong(payload.song, payload.artist, payload.lyrics),
            {
                autoplay: true,
                expanded: true,
            },
        );
    };

    return {
        openFromSummary,
    };
};
