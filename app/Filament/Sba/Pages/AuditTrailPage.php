<?php

namespace App\Filament\Sba\Pages;

use App\Models\AuditLog;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * SBA-side audit trail — tenant-scoped to the signed-in admin's organization
 * at the query level (same panel-layer scoping convention as the tenant
 * resources), so one SBA can never read another's activity.
 */
class AuditTrailPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Jejak Audit';

    protected static ?string $title = 'Jejak Audit';

    protected static ?string $slug = 'audit-trail';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.sba.pages.audit-trail';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AuditLog::query()
                    ->where('organization_id', auth()->user()->organization_id)
                    ->latest('id'),
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_name')
                    ->label('Pengguna')
                    ->placeholder('sistem'),
                Tables\Columns\TextColumn::make('action')
                    ->label('Aksi')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_starts_with($state, 'card.') => 'info',
                        str_starts_with($state, 'member.') => 'primary',
                        str_starts_with($state, 'complaint.') => 'warning',
                        str_starts_with($state, 'export.') => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('description')
                    ->label('Keterangan')
                    ->wrap(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Aksi')
                    ->options(fn (): array => AuditLog::query()
                        ->where('organization_id', auth()->user()->organization_id)
                        ->distinct()
                        ->orderBy('action')
                        ->pluck('action', 'action')
                        ->toArray()),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
