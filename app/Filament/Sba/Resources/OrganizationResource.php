<?php

namespace App\Filament\Sba\Resources;

use App\Filament\Sba\Resources\OrganizationResource\Pages;
use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Profil Organisasi';

    protected static ?string $modelLabel = 'Profil Organisasi';

    /** Tenant-scoped: the SBA admin only ever sees their own organization row. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereKey(auth()->user()?->organization_id);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    // Route binding resolves through getEloquentQuery(), so another org's slug 404s.

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nama')->required()->maxLength(200),
            Forms\Components\TextInput::make('company')->label('Perusahaan')->maxLength(200),
            Forms\Components\FileUpload::make('logo_path')
                ->label('Logo')->image()->directory('organizations')->imageEditor()->columnSpanFull(),
            Forms\Components\RichEditor::make('description')->label('Deskripsi')->columnSpanFull(),
            Forms\Components\TextInput::make('website')->url()->maxLength(255),
            Forms\Components\TextInput::make('location')->label('Lokasi')->maxLength(200),
            Forms\Components\TextInput::make('founded_year')
                ->label('Tahun berdiri')->nullable()->numeric()->minValue(1900)->maxValue(2100),
            // slug, is_published, member_count are federation-controlled — intentionally absent.
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable(),
                Tables\Columns\TextColumn::make('company')->label('Perusahaan'),
                Tables\Columns\TextColumn::make('updated_at')->label('Diperbarui')->dateTime('d M Y'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizations::route('/'),
            'edit' => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}
