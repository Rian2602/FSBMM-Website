<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganizationResource\Pages;
use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Organisasi SBA';

    protected static ?string $modelLabel = 'Organisasi SBA';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(200)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(200)
                    ->unique(ignoreRecord: true)
                    ->helperText('Otomatis diisi dari nama. Dipakai pada alamat /sba/{slug}.'),
                Forms\Components\TextInput::make('company')
                    ->label('Perusahaan')
                    ->maxLength(200),
                Forms\Components\FileUpload::make('logo_path')
                    ->label('Logo')
                    ->image()
                    ->directory('organizations')
                    ->imageEditor()
                    ->columnSpanFull(),
                Forms\Components\RichEditor::make('description')
                    ->label('Deskripsi')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('website')
                    ->url()
                    ->maxLength(255),
                Forms\Components\TextInput::make('location')
                    ->label('Lokasi')
                    ->maxLength(200),
                Forms\Components\TextInput::make('founded_year')
                    ->label('Tahun berdiri')
                    ->nullable()
                    ->numeric()
                    ->minValue(1900)
                    ->maxValue(2100),
                Forms\Components\TextInput::make('member_count')
                    ->label('Jumlah anggota')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                Forms\Components\Toggle::make('is_published')
                    ->label('Terbit di situs publik'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('company')
                    ->label('Perusahaan')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('member_count')
                    ->label('Anggota')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.')),
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
            'index' => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'edit' => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}
