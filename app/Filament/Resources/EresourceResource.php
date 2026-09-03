<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EresourceResource\Pages;
use App\Models\Eresource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class EresourceResource extends Resource
{
    protected static ?string $model = Eresource::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $navigationLabel = 'E-Resource';

    protected static ?string $modelLabel = 'Dokumen';

    protected static ?string $navigationGroup = 'Konten';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Judul')
                    ->required()
                    ->maxLength(200)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(200)
                    ->unique(ignoreRecord: true),
                Forms\Components\FileUpload::make('file_path')
                    ->label('File PDF')
                    ->acceptedFileTypes(['application/pdf'])
                    ->directory('eresources')
                    ->required()
                    ->helperText('Hanya PDF. File tersimpan di storage publik.'),
                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi')
                    ->maxLength(1000)
                    ->rows(4)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('downloads_count')
                    ->label('Jumlah unduhan')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpan(1),
                Forms\Components\Toggle::make('is_published')
                    ->label('Terbit di situs publik'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('downloads_count')
                    ->label('Diunduh')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Terbit')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Status publikasi'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEresources::route('/'),
            'create' => Pages\CreateEresource::route('/create'),
            'edit' => Pages\EditEresource::route('/{record}/edit'),
        ];
    }
}
