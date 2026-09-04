<?php

namespace App\Filament\Sba\Resources;

use App\Filament\Sba\Resources\MemberResource\Pages;
use App\Models\Member;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static ?string $navigationGroup = 'Data Anggota';

    protected static ?string $navigationLabel = 'Anggota';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organization_id', auth()->user()->organization_id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nik')->required()->maxLength(32),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\Select::make('gender')->options(['L' => 'Laki-laki', 'P' => 'Perempuan']),
            Forms\Components\TextInput::make('birthplace')->maxLength(255),
            Forms\Components\DatePicker::make('birthdate')->maxDate(now()),
            Forms\Components\Textarea::make('address'),
            Forms\Components\TextInput::make('department')->maxLength(255),
            Forms\Components\TextInput::make('position')->maxLength(255),
            Forms\Components\TextInput::make('basic_salary')->numeric()->prefix('Rp'),
            Forms\Components\DatePicker::make('join_date'),
            Forms\Components\TextInput::make('education')->maxLength(255),
            Forms\Components\Select::make('status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])->required()->default('aktif'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('nik')->searchable(),
            Tables\Columns\TextColumn::make('department'),
            Tables\Columns\TextColumn::make('position')->label('Jabatan'),
            Tables\Columns\TextColumn::make('status')
                ->badge()
                ->formatStateUsing(static fn (string $state): string => $state === 'aktif' ? 'Aktif' : 'Nonaktif'),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}
