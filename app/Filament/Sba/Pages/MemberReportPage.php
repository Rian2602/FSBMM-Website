<?php

namespace App\Filament\Sba\Pages;

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
use Illuminate\Support\Facades\DB;

class MemberReportPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Anggota';

    protected static ?string $title = 'Laporan Anggota';

    protected static ?string $slug = 'member-report';

    protected static string $view = 'filament.sba.pages.member-report-page';

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
                        ->options([
                            'aktif' => 'Aktif',
                            'nonaktif' => 'Nonaktif',
                        ])
                        ->placeholder('Semua Status')
                        ->label('Status'),
                    Forms\Components\TextInput::make('department')
                        ->placeholder('Semua Departemen')
                        ->label('Departemen'),
                    Forms\Components\TextInput::make('position')
                        ->placeholder('Semua Jabatan')
                        ->label('Jabatan'),
                    Forms\Components\TextInput::make('education')
                        ->placeholder('Semua Pendidikan')
                        ->label('Pendidikan'),
                    Forms\Components\Select::make('gender')
                        ->options([
                            'L' => 'Laki-laki',
                            'P' => 'Perempuan',
                        ])
                        ->placeholder('Semua Jenis Kelamin')
                        ->label('Jenis Kelamin'),
                    Forms\Components\DatePicker::make('join_date_start')
                        ->label('Tanggal Bergabung (Dari)'),
                    Forms\Components\DatePicker::make('join_date_end')
                        ->label('Tanggal Bergabung (Sampai)'),
                ]),
            ])
            ->statePath('data')
            ->live();
    }

    public function getFilteredQuery(): Builder
    {
        $query = Member::query()->where('organization_id', auth()->user()->organization_id);

        $data = $this->data;

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['department'])) {
            $query->where('department', 'like', '%'.$data['department'].'%');
        }
        if (! empty($data['position'])) {
            $query->where('position', 'like', '%'.$data['position'].'%');
        }
        if (! empty($data['education'])) {
            $query->where('education', 'like', '%'.$data['education'].'%');
        }
        if (! empty($data['gender'])) {
            $query->where('gender', $data['gender']);
        }
        if (! empty($data['join_date_start'])) {
            $query->whereDate('join_date', '>=', $data['join_date_start']);
        }
        if (! empty($data['join_date_end'])) {
            $query->whereDate('join_date', '<=', $data['join_date_end']);
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getFilteredQuery())
            ->columns([
                Tables\Columns\TextColumn::make('nik')->label('NIK')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable(),
                Tables\Columns\TextColumn::make('department')->label('Departemen')->sortable(),
                Tables\Columns\TextColumn::make('position')->label('Jabatan')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'aktif' => 'success',
                        'nonaktif' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('join_date')->label('Tgl Bergabung')->date()->sortable(),
            ])
            ->paginated([10, 25, 50]);
    }

    public function getStats(): array
    {
        $query = $this->getFilteredQuery();

        $total = (clone $query)->count();
        $active = (clone $query)->where('status', 'aktif')->count();
        $inactive = (clone $query)->where('status', 'nonaktif')->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
        ];
    }

    public function getBreakdownByDepartment(): array
    {
        return (clone $this->getFilteredQuery())
            ->select('department', DB::raw('count(*) as aggregate'))
            ->groupBy('department')
            ->orderByDesc('aggregate')
            ->pluck('aggregate', 'department')
            ->toArray();
    }

    public function getBreakdownByPosition(): array
    {
        return (clone $this->getFilteredQuery())
            ->select('position', DB::raw('count(*) as aggregate'))
            ->groupBy('position')
            ->orderByDesc('aggregate')
            ->pluck('aggregate', 'position')
            ->toArray();
    }
}
