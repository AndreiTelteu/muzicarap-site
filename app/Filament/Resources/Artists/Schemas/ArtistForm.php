<?php

namespace App\Filament\Resources\Artists\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ArtistForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                Textarea::make('bio')
                    ->rows(5)
                    ->columnSpanFull(),
                FileUpload::make('image_path')
                    ->directory('artists')
                    ->disk(config('filesystems.default')),
                Toggle::make('is_published')
                    ->default(false),
            ]);
    }
}
