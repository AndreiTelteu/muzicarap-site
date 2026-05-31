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
            ->has('featuredArtists', 1)
            ->where('featuredArtists.0.name', $artist->name)
            ->where('latestSongs.1.title', $olderSong->title)
            ->missing('latestSongs.2')
        );
});

it('shows the published artists index page', function (): void {
    $artist = Artist::factory()->create([
        'name' => 'Parazitii',
        'slug' => 'parazitii',
        'is_published' => true,
    ]);

    $album = Album::factory()->for($artist)->create();

    Song::factory()->for($artist)->for($album)->count(2)->create([
        'is_published' => true,
    ]);

    Artist::factory()->create([
        'name' => 'Ascuns',
        'slug' => 'ascuns',
        'is_published' => false,
    ]);

    $this->get(route('artists.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Public/Artists/Index')
            ->has('artists', 1)
            ->where('artists.0.name', 'Parazitii')
            ->where('artists.0.albums_count', 1)
            ->where('artists.0.songs_count', 2)
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

    Song::factory()->for($artist)->for($album)->create([
        'title' => 'Piesă de pe album',
        'slug' => 'piesa-de-pe-album',
        'parent_type' => SongParentType::Album,
        'track_number' => 3,
        'is_published' => true,
    ]);

    $soloSong = Song::factory()->for($artist)->create([
        'title' => 'Piesă solo publică',
        'slug' => 'piesa-solo-publica',
        'parent_type' => SongParentType::Single,
        'album_id' => null,
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
            ->where('artist.bio', $artist->bio)
            ->has('albums', 1)
            ->where('albums.0.title', $album->title)
            ->has('songs', 1)
            ->where('songs.0.title', $soloSong->title)
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
            ->where('tracks.0.artist.name', $artist->name)
            ->where('tracks.1.title', $secondTrack->title)
        );
});

it('shows a published song page with synced lyric segments and a public youtube embed id', function (): void {
    $artist = Artist::factory()->create(['slug' => 'subcarpati', 'is_published' => true]);
    $song = Song::factory()->for($artist)->create([
        'title' => 'Baladă',
        'slug' => 'balada',
        'youtube_id' => 'abc123xyz',
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
            ->where('song.youtube_id', 'abc123xyz')
            ->where('artist.name', $artist->name)
            ->where('lyrics.is_synced', true)
            ->has('lyrics.segments', 2)
        );
});

it('uses the latest song thumbnail for artist artwork and a song thumbnail for album artwork', function (): void {
    Storage::fake('local');

    config()->set('filesystems.default', 'local');

    $artist = Artist::factory()->create([
        'name' => 'Artwork Artist',
        'slug' => 'artwork-artist',
        'is_published' => true,
    ]);

    $album = Album::factory()->for($artist)->create([
        'title' => 'Artwork Album',
        'slug' => 'artwork-album',
        'type' => AlbumType::Album,
        'cover_path' => null,
    ]);

    Song::factory()->for($artist)->for($album)->create([
        'title' => 'Album older',
        'slug' => 'album-older',
        'track_number' => 1,
        'parent_type' => SongParentType::Album,
        'image_path' => 'songs/thumbnails/'.$artist->slug.'/album-older.jpg',
        'created_at' => now()->subDays(2),
        'is_published' => true,
    ]);

    $albumThumbnailSong = Song::factory()->for($artist)->for($album)->create([
        'title' => 'Album newer',
        'slug' => 'album-newer',
        'track_number' => 2,
        'parent_type' => SongParentType::Album,
        'image_path' => 'songs/thumbnails/'.$artist->slug.'/album-newer.jpg',
        'created_at' => now()->subDay(),
        'is_published' => true,
    ]);

    $artistThumbnailSong = Song::factory()->for($artist)->create([
        'title' => 'Artist latest',
        'slug' => 'artist-latest',
        'parent_type' => SongParentType::Single,
        'album_id' => null,
        'image_path' => 'songs/thumbnails/'.$artist->slug.'/artist-latest.jpg',
        'created_at' => now(),
        'is_published' => true,
    ]);

    $artistSummary = \App\Support\PublicCatalogData::artistSummary($artist);
    $albumSummary = \App\Support\PublicCatalogData::albumSummary($artist, $album);

    expect($artistSummary['image_url'])->toContain($artistThumbnailSong->image_path)
        ->and($albumSummary['cover_url'])->toContain($albumThumbnailSong->image_path);
});

it('returns grouped public search results for songs artists and albums', function (): void {
    $artist = Artist::factory()->create([
        'name' => 'Usi Specii',
        'slug' => 'specii',
        'is_published' => true,
    ]);

    $album = Album::factory()->for($artist)->create([
        'title' => 'Usile',
        'slug' => 'usile',
    ]);

    Song::factory()->for($artist)->for($album)->create([
        'title' => 'Usile Vol 1',
        'slug' => 'usile-vol-1',
        'is_published' => true,
    ]);

    $this->get(route('search', ['query' => 'usi']))
        ->assertOk()
        ->assertJsonPath('query', 'usi')
        ->assertJsonCount(1, 'artists')
        ->assertJsonCount(1, 'albums')
        ->assertJsonCount(1, 'songs')
        ->assertJsonPath('artists.0.name', 'Usi Specii')
        ->assertJsonPath('albums.0.title', 'Usile')
        ->assertJsonPath('songs.0.title', 'Usile Vol 1');
});
