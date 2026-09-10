<?php

namespace App\Filament\Sba\Pages;

use App\Models\Due;
use App\Models\Member;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DuesReportPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Iuran';

    protected static ?string $title = 'Laporan Iuran';

    protected static ?string $slug = 'dues-report';

    protected static string $view = 'filament.sba.pages.dues-report-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('period')
                        ->label('Periode (Satu Bulan)')
                        ->rule('regex:/^\d{4}-(0[1-9]|1[0-2])$/')
                        ->placeholder('YYYY-MM (contoh: 2026-09)'),
                    Forms\Components\TextInput::make('period_start')
                        ->label('Rentang Periode (Mulai)')
                        ->rule('regex:/^\d{4}-(0[1-9]|1[0-2])$/')
                        ->placeholder('YYYY-MM'),
                    Forms\Components\TextInput::make('period_end')
                        ->label('Rentang Periode (Sampai)')
                        ->rule('regex:/^\d{4}-(0[1-9]|1[0-2])$/')
                        ->placeholder('YYYY-MM'),
                ]),
            ])
            ->statePath('data')
            ->live();
    }

    public function getFilteredQuery(): Builder
    {
        $query = Due::query()->where('organization_id', auth()->user()->organization_id);

        $data = $this->data;

        if (! empty($data['period'])) {
            $query->where('period', $data['period']);
        }
        if (! empty($data['period_start'])) {
            $query->where('period', '>=', $data['period_start']);
        }
        if (! empty($data['period_end'])) {
            $query->where('period', '<=', $data['period_end']);
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getFilteredQuery())
            ->columns([
                Tables\Columns\TextColumn::make('member.name')->label('Anggota')->searchable(),
                Tables\Columns\TextColumn::make('period')->label('Periode')->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Tgl Bayar')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('recordedBy.name')->label('Dicatat Oleh'),
            ])
            ->paginated([10, 25, 50]);
    }

    public function getStats(): array
    {
        $query = $this->getFilteredQuery();

        $paymentCount = (clone $query)->count();
        $totalAmount = (clone $query)->sum('amount');
        $averageAmount = $paymentCount > 0 ? $totalAmount / $paymentCount : 0;

        $activeMembers = Member::query()
            ->where('organization_id', auth()->user()->organization_id)
            ->where('status', Member::STATUS_ACTIVE)
            ->count();

        $membersWithDuesCount = (clone $query)->distinct('member_id')->count('member_id');
        $membersWithoutDues = max(0, $activeMembers - $membersWithDuesCount);

        return [
            'payment_count' => $paymentCount,
            'total_amount' => $totalAmount,
            'average_amount' => $averageAmount,
            'active_members' => $activeMembers,
            'members_without_dues' => $membersWithoutDues,
        ];
    }

    public function getPeriodContext(): string
    {
        $data = $this->data;

        if (! empty($data['period'])) {
            return 'Periode '.$data['period'];
        }

        if (! empty($data['period_start']) && ! empty($data['period_end'])) {
            return 'Periode '.$data['period_start'].' s.d. '.$data['period_end'];
        }

        if (! empty($data['period_start'])) {
            return 'Mulai '.$data['period_start'];
        }

        if (! empty($data['period_end'])) {
            return 'Sampai '.$data['period_end'];
        }

        return 'Semua periode';
    }
}
