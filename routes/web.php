<?php

use App\Http\Controllers\Admin\DispatchSongLyricsCrawlController;
use App\Http\Controllers\Admin\LyricsSyncController;
use App\Http\Controllers\Admin\ResegmentSongLyricsController;
use App\Http\Controllers\Admin\StreamSongAudioController;
use App\Http\Middleware\EnsureAdminAccess;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

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
