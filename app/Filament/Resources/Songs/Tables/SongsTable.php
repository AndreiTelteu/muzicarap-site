<?php

namespace App\Filament\Resources\Songs\Tables;

use App\Actions\Lyrics\StartLyricsCrawlAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SongsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('artist.name')->label('Artist')->searchable()->sortable(),
                TextColumn::make('album.title')->label('Album')->toggleable(),
                TextColumn::make('parent_type')->badge(),
                TextColumn::make('lyric.source_status')->label('Lyrics')->badge(),
                TextColumn::make('latestCrawlRun.status')->label('Latest crawl')->badge(),
                IconColumn::make('is_published')->boolean()->label('Published'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('syncLyrics')
                    ->label('Sync lyrics')
                    ->icon('heroicon-o-musical-note')
                    ->url(fn ($record): string => route('admin.songs.lyrics-sync.edit', $record)),
                Action::make('crawlLyrics')
                    ->label('Manual crawl')
                    ->icon('heroicon-o-magnifying-glass')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        app(StartLyricsCrawlAction::class)->handle($record->loadMissing('artist'));

                        Notification::make()
                            ->title('Lyrics crawl queued')
                            ->success()
                            ->send();
                    }),
                Action::make('clearLyrics')
                    ->label('Clear lyrics')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->lyric?->delete();

                        Notification::make()
                            ->title('Lyrics cleared')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
