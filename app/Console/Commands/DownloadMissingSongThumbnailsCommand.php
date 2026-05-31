<?php

namespace App\Console\Commands;

use App\Models\Song;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('missing:thumbnails {--chunk=100 : Number of songs to inspect per batch}')]
#[Description('Download YouTube thumbnails for songs that do not have an image_path set')]
class DownloadMissingSongThumbnailsCommand extends Command
{
    public function handle(): int
    {
        $chunkSize = max((int) $this->option('chunk'), 1);
        $disk = Storage::disk(config('filesystems.default'));
        $downloadedCount = 0;
        $skippedCount = 0;

        Song::query()
            ->with(['artist:id,slug'])
            ->whereNotNull('youtube_id')
            ->whereNull('image_path')
            ->select(['id', 'artist_id', 'slug', 'title', 'youtube_id'])
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $songs) use (&$downloadedCount, &$skippedCount, $disk): void {
                foreach ($songs as $song) {
                    try {
                        $thumbnailContents = $this->downloadThumbnail((string) $song->youtube_id);

                        if ($thumbnailContents === null) {
                            $skippedCount++;

                            $this->warn(sprintf(
                                'Skipped "%s" because no usable thumbnail was returned for YouTube ID %s.',
                                $song->title,
                                $song->youtube_id,
                            ));

                            continue;
                        }

                        $imagePath = $this->imagePathFor($song);
                        $disk->put($imagePath, $thumbnailContents);

                        $song->forceFill([
                            'image_path' => $imagePath,
                        ])->saveQuietly();

                        $downloadedCount++;
                    } catch (Throwable $exception) {
                        $skippedCount++;

                        $this->warn(sprintf(
                            'Skipped "%s" because thumbnail download failed: %s',
                            $song->title,
                            $exception->getMessage(),
                        ));
                    }
                }
            });

        if ($downloadedCount === 0 && $skippedCount === 0) {
            $this->info('No songs are missing thumbnails.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Downloaded %d song thumbnail(s).', $downloadedCount));

        if ($skippedCount > 0) {
            $this->warn(sprintf('Skipped %d song(s).', $skippedCount));
        }

        return self::SUCCESS;
    }

    private function downloadThumbnail(string $youtubeId): ?string
    {
        foreach (['maxresdefault', 'hqdefault', 'mqdefault'] as $size) {
            $response = Http::retry(2, 250)
                ->timeout(20)
                ->accept('image/jpeg')
                ->get(sprintf('https://img.youtube.com/vi/%s/%s.jpg', $youtubeId, $size));

            if ($response->successful() && trim($response->body()) !== '') {
                return $response->body();
            }
        }

        return null;
    }

    private function imagePathFor(Song $song): string
    {
        $artistSlug = $song->artist?->slug ?? 'artist';

        return sprintf('songs/thumbnails/%s/%s.jpg', $artistSlug, $song->slug);
    }
}
