<?php

use App\Enums\AlbumType;
use App\Enums\LyricSourceStatus;
use App\Enums\SongParentType;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Lyric;
use App\Models\Song;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

it('shows the latest published songs on the public home page', function (): void {
    $artist = Artist::factory()->create(['name' => 'Cedry2k', 'slug' => 'cedry2k', 'is_published' => true]);

    $latestSong = Song::factory()->for($artist)->create([
        'title' => 'Ultima piesă',
        'slug' => 'ultima-piesa',
        'is_published' => true,
        'created_at' => now(),
    ]);

    $olderSong = Song::factory()->for($artist)->create([
        'title' => 'Prima piesă',
        'slug' => 'prima-piesa',
        'is_published' => true,
        'created_at' => now()->subDay(),
    ]);

    Song::factory()->for($artist)->create([
        'title' => 'Ascunsă',
        'slug' => 'ascunsa',
        'is_published' => false,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Home')
            ->has('latestSongs', 2)
            ->where('latestSongs.0.title', $latestSong->title)
            ->where('latestSongs.1.title', $olderSong->title)
            ->missing('latestSongs.2')
        );
});

it('shows a published artist page with public albums and songs', function (): void {
    $artist = Artist::factory()->create([
        'name' => 'DOC',
        'slug' => 'doc',
        'bio' => 'Bio artist',
        'is_published' => true,
    ]);

    $album = Album::factory()->for($artist)->create([
        'title' => 'Album public',
        'slug' => 'album-public',
        'type' => AlbumType::Album,
    ]);

    $song = Song::factory()->for($artist)->for($album)->create([
        'title' => 'Piesă publică',
        'slug' => 'piesa-publica',
        'parent_type' => SongParentType::Album,
        'track_number' => 3,
        'is_published' => true,
    ]);

    Song::factory()->for($artist)->create([
        'title' => 'Draft',
        'slug' => 'draft',
        'is_published' => false,
    ]);

    $this->get(route('artists.show', $artist))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Public/Artists/Show')
            ->where('artist.name', $artist->name)
            ->has('albums', 1)
            ->where('albums.0.title', $album->title)
            ->has('songs', 1)
            ->where('songs.0.title', $song->title)
        );
});

it('shows a published album page with ordered tracks', function (): void {
    $artist = Artist::factory()->create(['slug' => 'deliric', 'is_published' => true]);
    $album = Album::factory()->for($artist)->create([
        'title' => 'Album live',
        'slug' => 'album-live',
        'type' => AlbumType::Album,
    ]);

    $firstTrack = Song::factory()->for($artist)->for($album)->create([
        'title' => 'Track 1',
        'slug' => 'track-1',
        'track_number' => 1,
        'parent_type' => SongParentType::Album,
        'is_published' => true,
    ]);

    $secondTrack = Song::factory()->for($artist)->for($album)->create([
        'title' => 'Track 2',
        'slug' => 'track-2',
        'track_number' => 2,
        'parent_type' => SongParentType::Album,
        'is_published' => true,
    ]);

    $this->get(route('artists.albums.show', [$artist, $album]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Public/Albums/Show')
            ->where('album.title', $album->title)
            ->has('tracks', 2)
            ->where('tracks.0.title', $firstTrack->title)
            ->where('tracks.1.title', $secondTrack->title)
        );
});

it('shows a published song page with synced lyric segments and a public audio route', function (): void {
    $artist = Artist::factory()->create(['slug' => 'subcarpati', 'is_published' => true]);
    $song = Song::factory()->for($artist)->create([
        'title' => 'Baladă',
        'slug' => 'balada',
        'audio_path' => 'songs/balada.mp3',
        'is_published' => true,
    ]);

    $lyric = Lyric::factory()->for($song)->create([
        'lyrics' => "Primul vers\nAl doilea vers",
        'source_status' => LyricSourceStatus::Manual,
        'synced_at' => now(),
    ]);

    $lyric->segments()->createMany([
        [
            'position' => 1,
            'text' => 'Primul vers',
            'starts_at_ms' => 0,
            'ends_at_ms' => 2000,
            'is_instrumental_gap' => false,
        ],
        [
            'position' => 2,
            'text' => 'Al doilea vers',
            'starts_at_ms' => 2000,
            'ends_at_ms' => 3500,
            'is_instrumental_gap' => false,
        ],
    ]);

    $this->get(route('artists.songs.show', [$artist, $song]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Public/Songs/Show')
            ->where('song.title', $song->title)
            ->where('lyrics.is_synced', true)
            ->has('lyrics.segments', 2)
            ->where('routes.audio', route('artists.songs.audio.stream', [$artist, $song]))
        );
});

it('streams local audio for a published song', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('songs/public-song.mp3', 'fake-mp3');

    $artist = Artist::factory()->create(['slug' => 'ombladon', 'is_published' => true]);
    $song = Song::factory()->for($artist)->create([
        'slug' => 'public-song',
        'audio_path' => 'songs/public-song.mp3',
        'is_published' => true,
    ]);

    $this->get(route('artists.songs.audio.stream', [$artist, $song]))
        ->assertOk()
        ->assertHeader('content-type', 'audio/mpeg');
});
