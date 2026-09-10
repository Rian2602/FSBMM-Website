<?php

namespace App\Filament\Sba\Pages;

use App\Models\Attendance;
use App\Models\Event;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceReportPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Kegiatan & Absensi';

    protected static ?string $title = 'Laporan Kegiatan & Absensi';

    protected static ?string $slug = 'attendance-report';

    protected static string $view = 'filament.sba.pages.attendance-report-page';

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
                    Forms\Components\Select::make('event_id')
                        ->label('Kegiatan')
                        ->placeholder('Semua Kegiatan')
                        ->options(fn (): array => Event::query()
                            ->where('organization_id', auth()->user()->organization_id)
                            ->orderByDesc('event_date')
                            ->pluck('title', 'id')
                            ->all())
                        ->searchable(),
                    Forms\Components\DatePicker::make('event_date_start')
                        ->label('Rentang Tanggal (Mulai)'),
                    Forms\Components\DatePicker::make('event_date_end')
                        ->label('Rentang Tanggal (Sampai)'),
                ]),
            ])
            ->statePath('data')
            ->live();
    }

    public function getFilteredEventQuery(): Builder
    {
        $query = Event::query()->where('events.organization_id', auth()->user()->organization_id);

        $data = $this->data;

        if (! empty($data['event_id'])) {
            $query->whereKey($data['event_id']);
        }
        if (! empty($data['event_date_start'])) {
            $query->whereDate('events.event_date', '>=', $data['event_date_start']);
        }
        if (! empty($data['event_date_end'])) {
            $query->whereDate('events.event_date', '<=', $data['event_date_end']);
        }

        return $query;
    }

    public function getFilteredQuery(): Builder
    {
        return Attendance::query()
            ->where('attendances.organization_id', auth()->user()->organization_id)
            ->whereIn('attendances.event_id', $this->getFilteredEventQuery()->select('id'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getFilteredQuery())
            ->columns([
                Tables\Columns\TextColumn::make('event.title')->label('Kegiatan')->searchable(),
                Tables\Columns\TextColumn::make('member.name')->label('Anggota')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'hadir' => 'success',
                        'izin' => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'hadir' => 'Hadir',
                        'izin' => 'Izin',
                        default => 'Tidak Hadir',
                    }),
                Tables\Columns\TextColumn::make('note')->label('Catatan')->limit(50),
            ])
            ->paginated([10, 25, 50]);
    }

    public function getStats(): array
    {
        $query = $this->getFilteredQuery();

        $participantCount = (clone $query)->count();
        $hadir = (clone $query)->where('status', 'hadir')->count();
        $izin = (clone $query)->where('status', 'izin')->count();
        $tidakHadir = (clone $query)->where('status', 'tidak_hadir')->count();
        $attendanceRate = $participantCount > 0 ? round($hadir / $participantCount * 100, 1) : 0;

        return [
            'event_count' => $this->getFilteredEventQuery()->count(),
            'participant_count' => $participantCount,
            'hadir' => $hadir,
            'izin' => $izin,
            'tidak_hadir' => $tidakHadir,
            'attendance_rate' => $attendanceRate,
        ];
    }

    public function getBreakdownByEvent(): array
    {
        return $this->getFilteredEventQuery()
            ->select('events.id', 'events.title', 'events.event_date', 'attendances.status', DB::raw('count(attendances.id) as c'))
            ->leftJoin('attendances', function ($join) {
                $join->on('attendances.event_id', '=', 'events.id')
                    ->where('attendances.organization_id', auth()->user()->organization_id);
            })
            ->groupBy('events.id', 'events.title', 'events.event_date', 'attendances.status')
            ->orderBy('events.event_date')
            ->get()
            ->groupBy('id')
            ->map(fn ($rows) => [
                'title' => $rows->first()->title,
                'event_date' => Carbon::parse($rows->first()->event_date)->format('d M Y'),
                'hadir' => $rows->firstWhere('status', 'hadir')->c ?? 0,
                'izin' => $rows->firstWhere('status', 'izin')->c ?? 0,
                'tidak_hadir' => $rows->firstWhere('status', 'tidak_hadir')->c ?? 0,
            ])
            ->values()
            ->all();
    }

    public function getDateContext(): string
    {
        $data = $this->data;

        if (! empty($data['event_date_start']) && ! empty($data['event_date_end'])) {
            return $data['event_date_start'].' s.d. '.$data['event_date_end'];
        }

        if (! empty($data['event_date_start'])) {
            return 'Mulai '.$data['event_date_start'];
        }

        if (! empty($data['event_date_end'])) {
            return 'Sampai '.$data['event_date_end'];
        }

        return 'Semua tanggal';
    }
}
