<?php

namespace App\Filament\Sba\Resources;

use App\Filament\Sba\Resources\EventResource\Pages;
use App\Filament\Sba\Resources\EventResource\RelationManagers\AttendancesRelationManager;
use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationGroup = 'Data Anggota';

    protected static ?string $navigationLabel = 'Kegiatan';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organization_id', auth()->user()->organization_id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->label('Judul')
                ->required()
                ->maxLength(255),
            Forms\Components\DatePicker::make('event_date')
                ->label('Tanggal')
                ->required(),
            Forms\Components\Textarea::make('description')
                ->label('Deskripsi')
                ->nullable()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->label('Judul')->searchable(),
            Tables\Columns\TextColumn::make('event_date')->label('Tanggal')->date('d M Y')->sortable(),
            Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->date('d M Y'),
        ]);
    }

    public static function getRelations(): array
    {
        return [AttendancesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
