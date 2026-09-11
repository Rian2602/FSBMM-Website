<?php

namespace App\Filament\Widgets;

use App\Models\Complaint;
use App\Models\Due;
use App\Models\Event;
use App\Models\Member;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Widgets\Widget;

class GlobalSearchWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.widgets.global-search';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public ?string $searchQuery = '';

    public array $results = [];

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('searchQuery')
                    ->label('Cari lintas modul')
                    ->placeholder('Cari nama anggota, NIK, judul pengaduan, nama kegiatan...')
                    ->live(onBlur: true)
                    ->debounce(500)
                    ->suffixIcon('heroicon-m-magnifying-glass'),
            ])
            ->statePath('data');
    }

    public function updatedData(): void
    {
        $this->performSearch();
    }

    public function performSearch(): void
    {
        $query = $this->data['searchQuery'] ?? '';

        if (strlen($query) < 2) {
            $this->results = [];

            return;
        }

        $search = '%' . $query . '%';
        $results = [];

        // Search members
        $members = Member::query()
            ->where('name', 'like', $search)
            ->orWhere('nik', 'like', $search)
            ->limit(5)
            ->get()
            ->map(fn ($m) => [
                'type' => 'Anggota',
                'title' => $m->name,
                'subtitle' => "NIK: {$m->nik} — {$m->department}",
                'url' => "/panel-sba/{$m->organization_id}/members/{$m->id}/edit",
                'color' => 'primary',
            ]);
        $results = $results->merge($members);

        // Search complaints
        $complaints = Complaint::query()
            ->where('title', 'like', $search)
            ->orWhere('reporter_name', 'like', $search)
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'type' => 'Pengaduan',
                'title' => $c->title,
                'subtitle' => "Pelapor: {$c->reporter_name} — Status: {$c->status}",
                'url' => "/panel-sba/{$c->organization_id}/complaints/{$c->id}/edit",
                'color' => match ($c->status) {
                    'baru' => 'info',
                    'diproses' => 'warning',
                    default => 'success',
                },
            ]);
        $results = $results->merge($complaints);

        // Search events
        $events = Event::query()
            ->where('title', 'like', $search)
            ->limit(5)
            ->get()
            ->map(fn ($e) => [
                'type' => 'Kegiatan',
                'title' => $e->title,
                'subtitle' => 'Tanggal: ' . ($e->event_date?->format('d M Y') ?? '-'),
                'url' => "/panel-sba/{$e->organization_id}/events/{$e->id}/edit",
                'color' => 'success',
            ]);
        $results = $results->merge($events);

        // Search dues
        $dues = Due::query()
            ->with('member')
            ->where('period', 'like', $search)
            ->orWhereHas('member', fn ($q) => $q->where('name', 'like', $search))
            ->limit(5)
            ->get()
            ->map(fn ($d) => [
                'type' => 'Iuran',
                'title' => ($d->member?->name ?? 'N/A') . " — {$d->period}",
                'subtitle' => 'Rp ' . number_format($d->amount, 0, ',', '.'),
                'url' => "/panel-sba/{$d->organization_id}/dues/{$d->id}/edit",
                'color' => 'warning',
            ]);
        $results = $results->merge($dues);

        $this->results = $results->toArray();
    }
}
