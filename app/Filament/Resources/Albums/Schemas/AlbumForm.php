<?php

namespace App\Filament\Resources\Albums\Schemas;

use App\Enums\AlbumType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AlbumForm
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
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->options(AlbumType::options())
                    ->required(),
                DatePicker::make('release_date'),
                FileUpload::make('cover_path')
                    ->directory('albums')
                    ->disk(config('filesystems.default')),
                Textarea::make('description')
                    ->rows(5)
                    ->columnSpanFull(),
            ]);
    }
}
