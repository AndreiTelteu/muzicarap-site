<?php

use App\Jobs\DownloadSongAudioJob;
use App\Models\Song;
use App\Services\Songs\SearxNgYouTubeSearchService;
use App\Services\Songs\YouTubeMp3Downloader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('downloads mp3 audio for a song and stores the file path', function (): void {
    Storage::fake('local');

    config()->set('filesystems.default', 'local');
    config()->set('muzicarap.song_audio.searxng_url', 'https://searx.test/search');
    config()->set('muzicarap.song_audio.temporary_directory', storage_path('framework/testing/song-audio-downloads'));

    $song = Song::factory()->create([
        'slug' => 'balada',
        'title' => 'Balada',
        'audio_path' => null,
    ]);

    Http::fake([
        'https://searx.test/search*' => Http::response([
            'results' => [
                [
                    'url' => 'https://www.youtube.com/watch?v=abc123',
                    'title' => $song->artist->name.' - Balada (Official Audio)',
                    'content' => 'Official audio upload',
                ],
                [
                    'url' => 'https://www.youtube.com/watch?v=def456',
                    'title' => $song->artist->name.' - Balada (Live)',
                    'content' => 'Concert live version',
                ],
            ],
        ]),
    ]);

    $temporaryDirectory = rtrim((string) config('muzicarap.song_audio.temporary_directory'), '/').'/'.$song->getKey();

    Process::fake(function ($process) use ($temporaryDirectory) {
        File::ensureDirectoryExists($temporaryDirectory);
        file_put_contents($temporaryDirectory.'/abc123.mp3', 'fake-mp3');

        return Process::result(output: 'downloaded');
    });

    $job = new DownloadSongAudioJob($song->getKey());
    $job->handle(app(SearxNgYouTubeSearchService::class), app(YouTubeMp3Downloader::class));

    $song->refresh();

    expect($song->audio_path)->toBe('songs/'.$song->artist->slug.'-balada-'.$song->getKey().'.mp3');

    Storage::disk('local')->assertExists($song->audio_path);

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://searx.test/search')
        && $request['q'] === $song->artist->name.' Balada site:youtube.com/watch'
        && $request['categories'] === 'videos');

    Process::assertRan(fn ($process): bool => is_array($process->command)
        && in_array('yt-dlp', $process->command, true)
        && in_array('https://www.youtube.com/watch?v=abc123', $process->command, true));
});

it('dispatches download jobs only for songs missing audio', function (): void {
    Queue::fake();

    $missingAudioSong = Song::factory()->create([
        'audio_path' => null,
    ]);
    $existingAudioSong = Song::factory()->create([
        'audio_path' => 'songs/already-downloaded.mp3',
    ]);
    $secondMissingAudioSong = Song::factory()->create([
        'audio_path' => null,
    ]);

    $this->artisan('download:missing:songs')
        ->expectsOutput('Dispatched 2 song audio download job(s).')
        ->assertSuccessful();

    Queue::assertPushed(DownloadSongAudioJob::class, 2);
    Queue::assertPushed(DownloadSongAudioJob::class, fn (DownloadSongAudioJob $job): bool => $job->songId === $missingAudioSong->getKey());
    Queue::assertPushed(DownloadSongAudioJob::class, fn (DownloadSongAudioJob $job): bool => $job->songId === $secondMissingAudioSong->getKey());
    Queue::assertNotPushed(DownloadSongAudioJob::class, fn (DownloadSongAudioJob $job): bool => $job->songId === $existingAudioSong->getKey());
});
