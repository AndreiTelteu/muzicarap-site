<?php

use App\Jobs\CrawlLyricsForSongJob;
use App\Models\Lyric;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

it('shows the admin lyrics sync editor', function (): void {
    $admin = User::factory()->admin()->create();
    $song = Song::factory()->create();
    $lyric = Lyric::factory()->for($song)->create([
        'lyrics' => "Primul vers\nAl doilea vers",
    ]);

    $lyric->segments()->createMany([
        [
            'position' => 1,
            'text' => 'Primul vers',
            'starts_at_ms' => 1000,
            'ends_at_ms' => 2000,
            'is_instrumental_gap' => false,
        ],
        [
            'position' => 2,
            'text' => 'Al doilea vers',
            'starts_at_ms' => null,
            'ends_at_ms' => null,
            'is_instrumental_gap' => false,
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.songs.lyrics-sync.edit', $song))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Admin/LyricsSync/Edit')
            ->where('song.id', $song->id)
            ->where('lyric.lyrics', "Primul vers\nAl doilea vers")
            ->has('segments', 2)
            ->where('segments.0.text', 'Primul vers')
            ->where('routes.save', route('admin.songs.lyrics-sync.update', $song))
            ->where('routes.resegment', route('admin.songs.lyrics-sync.resegment', $song))
            ->where('routes.crawl', route('admin.songs.lyrics-crawl.dispatch', $song))
        );
});

it('saves synced lyric timestamps for an admin song', function (): void {
    $admin = User::factory()->admin()->create();
    $song = Song::factory()->create();

    $response = $this->actingAs($admin)
        ->putJson(route('admin.songs.lyrics-sync.update', $song), [
            'lyrics' => "Linia unu\nLinia doi",
            'segments' => [
                [
                    'text' => 'Linia unu',
                    'starts_at_ms' => 1200,
                    'ends_at_ms' => 3100,
                    'is_instrumental_gap' => false,
                ],
                [
                    'text' => 'Linia doi',
                    'starts_at_ms' => 3200,
                    'ends_at_ms' => null,
                    'is_instrumental_gap' => false,
                ],
            ],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('lyric.source_status', 'manual')
        ->assertJsonPath('segments.0.starts_at_ms', 1200)
        ->assertJsonCount(2, 'segments');

    $song->refresh()->load('lyric.segments');

    expect($song->lyric)->not->toBeNull()
        ->and($song->lyric->lyrics)->toBe("Linia unu\nLinia doi")
        ->and($song->lyric->synced_at)->not->toBeNull()
        ->and($song->lyric->segments)->toHaveCount(2)
        ->and($song->lyric->segments[1]->starts_at_ms)->toBe(3200);
});

it('resegments lyrics lines for manual timing', function (): void {
    $admin = User::factory()->admin()->create();
    $song = Song::factory()->create();

    $this->actingAs($admin)
        ->postJson(route('admin.songs.lyrics-sync.resegment', $song), [
            'lyrics' => " Refren \n\n Strofa doi ",
        ])
        ->assertOk()
        ->assertJsonPath('segments.0.position', 1)
        ->assertJsonPath('segments.0.text', 'Refren')
        ->assertJsonPath('segments.1.text', 'Strofa doi')
        ->assertJsonCount(2, 'segments');
});

it('queues a crawl run for a song', function (): void {
    Queue::fake();

    $admin = User::factory()->admin()->create();
    $song = Song::factory()->create();

    $this->actingAs($admin)
        ->postJson(route('admin.songs.lyrics-crawl.dispatch', $song))
        ->assertStatus(202)
        ->assertJsonPath('run.status', 'queued');

    $song->refresh()->load('crawlRuns', 'lyric');

    expect($song->crawlRuns)->toHaveCount(1)
        ->and($song->lyric)->not->toBeNull()
        ->and($song->lyric->source_status->value)->toBe('queued');

    Queue::assertPushed(CrawlLyricsForSongJob::class, function (CrawlLyricsForSongJob $job) use ($song): bool {
        return $job->songId === $song->id;
    });
});
