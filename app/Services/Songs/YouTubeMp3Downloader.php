<?php

namespace App\Services\Songs;

use App\Models\Song;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class YouTubeMp3Downloader
{
    public function download(Song $song, string $youtubeUrl): string
    {
        $song->loadMissing('artist');

        $temporaryDirectory = $this->temporaryDirectoryFor($song);
        File::deleteDirectory($temporaryDirectory);
        File::ensureDirectoryExists($temporaryDirectory);

        try {
            Process::path(base_path())
                ->timeout((int) config('muzicarap.song_audio.process_timeout', 75))
                ->run($this->buildCommand($temporaryDirectory, $youtubeUrl))
                ->throw();

            $downloadedMp3Path = collect(File::glob($temporaryDirectory.'/*.mp3'))
                ->first();

            if (! is_string($downloadedMp3Path)) {
                throw new RuntimeException('yt-dlp did not produce an MP3 file.');
            }

            return $this->storeDownloadedFile($song, $downloadedMp3Path);
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    /**
     * @return list<string>
     */
    private function buildCommand(string $temporaryDirectory, string $youtubeUrl): array
    {
        $ffmpegLocation = config('muzicarap.song_audio.ffmpeg_location');

        return collect([
            (string) config('muzicarap.song_audio.yt_dlp_binary', 'yt-dlp'),
            '--no-playlist',
            '--no-warnings',
            '--extract-audio',
            '--audio-format',
            'mp3',
            '--audio-quality',
            '0',
            '--output',
            $temporaryDirectory.'/%(id)s.%(ext)s',
            is_string($ffmpegLocation) && $ffmpegLocation !== '' ? '--ffmpeg-location' : null,
            is_string($ffmpegLocation) && $ffmpegLocation !== '' ? $ffmpegLocation : null,
            $youtubeUrl,
        ])->filter(fn (?string $value): bool => $value !== null && $value !== '')
            ->values()
            ->all();
    }

    private function temporaryDirectoryFor(Song $song): string
    {
        return rtrim((string) config('muzicarap.song_audio.temporary_directory', storage_path('app/tmp/song-audio-downloads')), '/').'/'.$song->getKey();
    }

    private function storeDownloadedFile(Song $song, string $downloadedMp3Path): string
    {
        $disk = Storage::disk(config('filesystems.default'));
        $storedPath = trim((string) config('muzicarap.song_audio.directory', 'songs'), '/').'/'.$this->filenameFor($song);
        $stream = fopen($downloadedMp3Path, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to read the downloaded MP3 file.');
        }

        try {
            $written = $disk->writeStream($storedPath, $stream);
        } finally {
            fclose($stream);
        }

        if ($written === false) {
            throw new RuntimeException('Unable to store the downloaded MP3 file.');
        }

        return $storedPath;
    }

    private function filenameFor(Song $song): string
    {
        $artistSlug = $song->artist->slug !== '' ? $song->artist->slug : Str::slug($song->artist->name);
        $songSlug = $song->slug !== '' ? $song->slug : Str::slug($song->title);

        return sprintf('%s-%s-%d.mp3', $artistSlug, $songSlug, $song->getKey());
    }
}
