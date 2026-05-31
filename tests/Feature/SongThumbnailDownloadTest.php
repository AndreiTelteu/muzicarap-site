<?php

use App\Models\Song;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('downloads youtube thumbnails for songs missing image paths', function (): void {
    Storage::fake('local');

    config()->set('filesystems.default', 'local');

    $song = Song::factory()->create([
        'title' => 'Balada',
        'slug' => 'balada',
        'youtube_id' => 'abc123xyz12',
        'image_path' => null,
    ]);

    Http::fake([
        'https://img.youtube.com/vi/abc123xyz12/maxresdefault.jpg' => Http::response('fake-thumbnail-bytes', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
        '*' => Http::response('', 404),
    ]);

    $this->artisan('missing:thumbnails')
        ->expectsOutput('Downloaded 1 song thumbnail(s).')
        ->assertSuccessful();

    $song->refresh();

    expect($song->image_path)->toBe('songs/thumbnails/'.$song->artist->slug.'/'.$song->slug.'.jpg');

    Storage::disk('local')->assertExists($song->image_path);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://img.youtube.com/vi/abc123xyz12/maxresdefault.jpg');
});
