<?php

use App\Jobs\DownloadSongAudioJob;
use App\Jobs\SyncSongYoutubeIdJob;
use App\Models\Song;
use App\Services\Songs\SearxNgYouTubeSearchService;
use App\Services\Songs\YouTubeMp3Downloader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('finds and stores the youtube id for a song', function (): void {
    config()->set('muzicarap.song_audio.searxng_url', 'https://searx.test/search');

    $song = Song::factory()->create([
        'slug' => 'balada',
        'title' => 'Balada',
        'audio_path' => 'songs/existing-file.mp3',
        'youtube_id' => null,
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

    $job = new SyncSongYoutubeIdJob($song->getKey());
    $job->handle(app(SearxNgYouTubeSearchService::class));

    $song->refresh();

    expect($song->audio_path)->toBe('songs/existing-file.mp3')
        ->and($song->youtube_id)->toBe('abc123');

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://searx.test/search')
        && $request['q'] === $song->artist->name.' Balada site:youtube.com/watch'
        && $request['categories'] === 'videos');
});

it('downloads mp3 audio for a song with a youtube id and stores the file path', function (): void {
    Storage::fake('local');

    config()->set('filesystems.default', 'local');
    config()->set('muzicarap.song_audio.temporary_directory', storage_path('framework/testing/song-audio-downloads'));

    $song = Song::factory()->create([
        'slug' => 'balada',
        'title' => 'Balada',
        'youtube_id' => 'abc123',
        'audio_path' => null,
    ]);

    $temporaryDirectory = rtrim((string) config('muzicarap.song_audio.temporary_directory'), '/').'/'.$song->getKey();

    Process::fake(function ($process) use ($temporaryDirectory) {
        File::ensureDirectoryExists($temporaryDirectory);
        file_put_contents($temporaryDirectory.'/abc123.mp3', 'fake-mp3');

        return Process::result(output: 'downloaded');
    });

    $job = new DownloadSongAudioJob($song->getKey());
    $job->handle(app(YouTubeMp3Downloader::class));

    $song->refresh();

    expect($song->audio_path)->toBe('songs/'.$song->artist->slug.'-balada-'.$song->getKey().'.mp3')
        ->and($song->youtube_id)->toBe('abc123');

    Storage::disk('local')->assertExists($song->audio_path);

    Process::assertRan(fn ($process): bool => is_array($process->command)
        && in_array('yt-dlp', $process->command, true)
        && in_array('https://www.youtube.com/watch?v=abc123', $process->command, true));
});

it('dispatches sync jobs only for songs missing youtube ids', function (): void {
    Queue::fake();

    $missingYouTubeSong = Song::factory()->create([
        'youtube_id' => null,
    ]);
    $existingYouTubeSong = Song::factory()->create([
        'youtube_id' => 'already-set',
        'audio_path' => 'songs/already-downloaded.mp3',
    ]);
    $secondMissingYouTubeSong = Song::factory()->create([
        'youtube_id' => null,
    ]);

    $this->artisan('missing:songs')
        ->expectsOutput('Dispatched 2 song YouTube ID sync job(s).')
        ->assertSuccessful();

    Queue::assertPushed(SyncSongYoutubeIdJob::class, 2);
    Queue::assertPushed(SyncSongYoutubeIdJob::class, fn (SyncSongYoutubeIdJob $job): bool => $job->songId === $missingYouTubeSong->getKey());
    Queue::assertPushed(SyncSongYoutubeIdJob::class, fn (SyncSongYoutubeIdJob $job): bool => $job->songId === $secondMissingYouTubeSong->getKey());
    Queue::assertNotPushed(SyncSongYoutubeIdJob::class, fn (SyncSongYoutubeIdJob $job): bool => $job->songId === $existingYouTubeSong->getKey());
});

it('dispatches download jobs only for songs with youtube ids and missing audio', function (): void {
    Queue::fake();

    $readyToDownloadSong = Song::factory()->create([
        'youtube_id' => 'abc123',
        'audio_path' => null,
    ]);
    $missingYouTubeSong = Song::factory()->create([
        'youtube_id' => null,
        'audio_path' => null,
    ]);
    $alreadyDownloadedSong = Song::factory()->create([
        'youtube_id' => 'def456',
        'audio_path' => 'songs/already-downloaded.mp3',
    ]);

    $this->artisan('missing:download')
        ->expectsOutput('Dispatched 1 song audio download job(s).')
        ->assertSuccessful();

    Queue::assertPushed(DownloadSongAudioJob::class, 1);
    Queue::assertPushed(DownloadSongAudioJob::class, fn (DownloadSongAudioJob $job): bool => $job->songId === $readyToDownloadSong->getKey());
    Queue::assertNotPushed(DownloadSongAudioJob::class, fn (DownloadSongAudioJob $job): bool => $job->songId === $missingYouTubeSong->getKey());
    Queue::assertNotPushed(DownloadSongAudioJob::class, fn (DownloadSongAudioJob $job): bool => $job->songId === $alreadyDownloadedSong->getKey());
});
