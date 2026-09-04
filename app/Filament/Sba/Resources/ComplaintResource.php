<?php

namespace App\Filament\Sba\Resources;

use App\Filament\Sba\Resources\ComplaintResource\Pages;
use App\Models\Complaint;
use App\Models\Member;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ComplaintResource extends Resource
{
    protected static ?string $model = Complaint::class;

    protected static ?string $navigationGroup = 'Data Anggota';

    protected static ?string $navigationLabel = 'Pengaduan';

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organization_id', auth()->user()->organization_id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('reporter_name')
                ->label('Nama Pelapor')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('member_id')
                ->label('Terkait Anggota')
                ->relationship('member', 'name', modifyQueryUsing: fn (Builder $q) => $q->where('organization_id', auth()->user()->organization_id))
                ->searchable()
                ->nullable()
                ->rules(fn () => function ($attribute, $value, $fail) {
                    if ($value && ! Member::where('id', $value)->where('organization_id', auth()->user()->organization_id)->exists()) {
                        $fail('Anggota tidak terkait dengan organisasi ini.');
                    }
                }),
            Forms\Components\TextInput::make('title')
                ->label('Judul')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->label('Keterangan')
                ->required()
                ->columnSpanFull(),
            Forms\Components\Select::make('status')
                ->options([
                    'baru' => 'Baru',
                    'diproses' => 'Diproses',
                    'selesai' => 'Selesai',
                ])
                ->required()
                ->default('baru')
                ->reactive(),
            Forms\Components\DatePicker::make('submitted_at')
                ->label('Tanggal Masuk')
                ->required()
                ->default(now()),
            Forms\Components\DatePicker::make('resolved_at')
                ->label('Tanggal Selesai')
                ->nullable()
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('reporter_name')->label('Pelapor')->searchable(),
            Tables\Columns\TextColumn::make('title')->label('Judul')->searchable(),
            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->badge(fn (string $state) => match ($state) {
                    'baru' => 'info',
                    'diproses' => 'warning',
                    'selesai' => 'success',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('submitted_at')->label('Masuk')->date('d M Y'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComplaints::route('/'),
            'create' => Pages\CreateComplaint::route('/create'),
            'edit' => Pages\EditComplaint::route('/{record}/edit'),
        ];
    }
}
