<?php

namespace App\Filament\Sba\Resources;

use App\Filament\Sba\Resources\DuesResource\Pages;
use App\Models\Due;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DuesResource extends Resource
{
    protected static ?string $model = Due::class;

    protected static ?string $navigationGroup = 'Data Anggota';

    protected static ?string $navigationLabel = 'Iuran';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organization_id', auth()->user()->organization_id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('member_id')
                ->relationship('member', 'name', modifyQueryUsing: fn (Builder $q) => $q->where('organization_id', auth()->user()->organization_id))
                ->required()
                ->searchable(),
            Forms\Components\TextInput::make('period')
                ->required()
                ->rule('regex:/^\d{4}-(0[1-9]|1[0-2])$/')
                ->helperText('Format: YYYY-MM (contoh: 2026-09)'),
            Forms\Components\TextInput::make('amount')
                ->numeric()
                ->required()
                ->minValue(0)
                ->prefix('Rp'),
            Forms\Components\DatePicker::make('paid_at')
                ->required()
                ->default(now()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('member.name')->label('Anggota')->searchable(),
            Tables\Columns\TextColumn::make('period')->label('Periode')->sortable(),
            Tables\Columns\TextColumn::make('amount')
                ->label('Jumlah')
                ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.')),
            Tables\Columns\TextColumn::make('paid_at')
                ->label('Tanggal Bayar')
                ->date('d M Y'),
            Tables\Columns\TextColumn::make('recordedBy.name')
                ->label('Dicatat Oleh')
                ->placeholder('-'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDues::route('/'),
            'create' => Pages\CreateDues::route('/create'),
            'edit' => Pages\EditDues::route('/{record}/edit'),
        ];
    }
}
