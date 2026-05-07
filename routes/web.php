<?php

use App\Http\Controllers\Admin\DispatchSongLyricsCrawlController;
use App\Http\Controllers\Admin\LyricsSyncController;
use App\Http\Controllers\Admin\ResegmentSongLyricsController;
use App\Http\Controllers\Admin\StreamSongAudioController;
use App\Http\Controllers\Public\AlbumShowController;
use App\Http\Controllers\Public\ArtistShowController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\SongShowController;
use App\Http\Controllers\Public\StreamPublishedSongAudioController;
use App\Http\Middleware\EnsureAdminAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::scopeBindings()->group(function (): void {
    Route::get('/artisti/{artist}', ArtistShowController::class)->name('artists.show');
    Route::get('/artisti/{artist}/albume/{album}', AlbumShowController::class)->name('artists.albums.show');
    Route::get('/artisti/{artist}/piese/{song}', SongShowController::class)->name('artists.songs.show');
    Route::get('/artisti/{artist}/piese/{song}/audio', StreamPublishedSongAudioController::class)->name('artists.songs.audio.stream');
});

Route::middleware([EnsureAdminAccess::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('songs/{song}/lyrics-sync', [LyricsSyncController::class, 'edit'])->name('songs.lyrics-sync.edit');
        Route::put('songs/{song}/lyrics-sync', [LyricsSyncController::class, 'update'])->name('songs.lyrics-sync.update');
        Route::post('songs/{song}/lyrics-sync/resegment', ResegmentSongLyricsController::class)->name('songs.lyrics-sync.resegment');
        Route::post('songs/{song}/lyrics-crawl', DispatchSongLyricsCrawlController::class)->name('songs.lyrics-crawl.dispatch');
        Route::get('songs/{song}/audio', StreamSongAudioController::class)->name('songs.audio.stream');
    });
