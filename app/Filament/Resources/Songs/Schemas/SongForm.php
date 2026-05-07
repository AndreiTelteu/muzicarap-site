<?php

namespace App\Filament\Resources\Songs\Schemas;

use App\Enums\SongParentType;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SongForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('artist_id')
                    ->relationship('artist', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('album_id')
                    ->relationship('album', 'title')
                    ->searchable()
                    ->preload(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                TextInput::make('track_number')
                    ->numeric(),
                Select::make('parent_type')
                    ->options(SongParentType::options())
                    ->required(),
                TextInput::make('duration_seconds')
                    ->numeric(),
                FileUpload::make('audio_path')
                    ->directory('songs')
                    ->disk(config('filesystems.default')),
                Toggle::make('is_published')
                    ->default(false),
            ]);
    }
}
