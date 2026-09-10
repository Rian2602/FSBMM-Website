<?php

namespace App\Filament\Sba\Pages;

use App\Models\Complaint;
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

class ComplaintReportPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Pengaduan';

    protected static ?string $title = 'Laporan Pengaduan';

    protected static ?string $slug = 'complaint-report';

    protected static string $view = 'filament.sba.pages.complaint-report-page';

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
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->placeholder('Semua Status')
                        ->options([
                            'baru' => 'Baru',
                            'diproses' => 'Diproses',
                            'selesai' => 'Selesai',
                        ]),
                    Forms\Components\DatePicker::make('submitted_start')
                        ->label('Rentang Tanggal Masuk (Mulai)'),
                    Forms\Components\DatePicker::make('submitted_end')
                        ->label('Rentang Tanggal Masuk (Sampai)'),
                ]),
            ])
            ->statePath('data')
            ->live();
    }

    public function getFilteredQuery(): Builder
    {
        $query = Complaint::query()->where('organization_id', auth()->user()->organization_id);

        $data = $this->data;

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['submitted_start'])) {
            $query->whereDate('submitted_at', '>=', $data['submitted_start']);
        }
        if (! empty($data['submitted_end'])) {
            $query->whereDate('submitted_at', '<=', $data['submitted_end']);
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getFilteredQuery())
            ->columns([
                Tables\Columns\TextColumn::make('reporter_name')->label('Pelapor')->searchable(),
                Tables\Columns\TextColumn::make('title')->label('Judul')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'baru' => 'info',
                        'diproses' => 'warning',
                        default => 'success',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'baru' => 'Baru',
                        'diproses' => 'Diproses',
                        default => 'Selesai',
                    }),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Masuk')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('resolved_at')
                    ->label('Selesai')
                    ->date('d M Y')
                    ->placeholder('—'),
            ])
            ->paginated([10, 25, 50]);
    }

    public function getStats(): array
    {
        $query = $this->getFilteredQuery();

        return [
            'total' => (clone $query)->count(),
            'baru' => (clone $query)->where('status', 'baru')->count(),
            'diproses' => (clone $query)->where('status', 'diproses')->count(),
            'selesai' => (clone $query)->where('status', 'selesai')->count(),
            'open' => (clone $query)->where('status', '!=', 'selesai')->count(),
        ];
    }

    public function getDateContext(): string
    {
        $data = $this->data;

        if (! empty($data['submitted_start']) && ! empty($data['submitted_end'])) {
            return $data['submitted_start'].' s.d. '.$data['submitted_end'];
        }

        if (! empty($data['submitted_start'])) {
            return 'Mulai '.$data['submitted_start'];
        }

        if (! empty($data['submitted_end'])) {
            return 'Sampai '.$data['submitted_end'];
        }

        return 'Semua tanggal';
    }
}
