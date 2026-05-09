<?php

namespace App\Console\Commands;

use App\Jobs\DownloadSongAudioJob;
use App\Models\Song;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('missing:download {--chunk=100 : Number of songs to dispatch per batch}')]
#[Description('Queue MP3 downloads for songs that have a YouTube ID and no audio_path set')]
class DownloadMissingAudioCommand extends Command
{
    public function handle(): int
    {
        $chunkSize = max((int) $this->option('chunk'), 1);
        $dispatched = 0;

        Song::query()
            ->whereNotNull('youtube_id')
            ->missingAudio()
            ->select('id')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($songs) use (&$dispatched): void {
                foreach ($songs as $song) {
                    DownloadSongAudioJob::dispatch($song->getKey());
                    $dispatched++;
                }
            });

        if ($dispatched === 0) {
            $this->info('No songs are ready for audio download.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Dispatched %d song audio download job(s).', $dispatched));

        return self::SUCCESS;
    }
}
