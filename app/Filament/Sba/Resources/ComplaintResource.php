<?php

namespace App\Filament\Sba\Resources;

use App\Filament\Sba\Resources\ComplaintResource\Pages;
use App\Models\Complaint;
use App\Models\Member;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
        ])->bulkActions([
            // (** executed: Phase 4 bulk operations — status transitions go
            // through ComplaintObserver, so each record is audited. **)
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\BulkAction::make('process')
                    ->label('Tandai Diproses')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $records->each(fn (Complaint $complaint) => $complaint->update(['status' => 'diproses']));

                        Notification::make()
                            ->title($records->count().' pengaduan ditandai diproses')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\BulkAction::make('resolve')
                    ->label('Tandai Selesai')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $records->each(fn (Complaint $complaint) => $complaint->update([
                            'status' => 'selesai',
                            'resolved_at' => now(),
                        ]));

                        Notification::make()
                            ->title($records->count().' pengaduan diselesaikan')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteBulkAction::make(),
            ]),
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
