<?php

namespace App\Filament\Sba\Pages;

use App\Models\Member;
use App\Models\MemberCard;
use App\Support\MemberCardService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class MemberCardPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Data Anggota';

    protected static ?string $navigationLabel = 'Kartu Anggota';

    protected static ?string $title = 'Kartu Anggota';

    protected static ?string $slug = 'member-cards';

    protected static string $view = 'filament.sba.pages.member-card-page';

    protected static ?int $navigationSort = 60;

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
                    Forms\Components\Select::make('member_id')
                        ->label('Pilih Anggota')
                        ->options(fn () => Member::where('organization_id', auth()->user()->organization_id)
                            ->where('status', Member::STATUS_ACTIVE)
                            ->pluck('name', 'id'))
                        ->placeholder('Semua Anggota'),
                    Forms\Components\Select::make('card_status')
                        ->label('Status Kartu')
                        ->options([
                            MemberCard::STATUS_ACTIVE => 'Aktif',
                            MemberCard::STATUS_REVOKED => 'Dicabut',
                        ])
                        ->placeholder('Semua Status'),
                ]),
            ])
            ->statePath('data')
            ->live();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getFilteredQuery())
            ->columns([
                Tables\Columns\TextColumn::make('member.name')
                    ->label('Nama Anggota')
                    ->searchable(),
                Tables\Columns\TextColumn::make('card_number')
                    ->label('Nomor Kartu')
                    ->fontFamily('mono')
                    ->copyable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        MemberCard::STATUS_ACTIVE => 'success',
                        MemberCard::STATUS_REVOKED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Diterbitkan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('revoked_at')
                    ->label('Dicabut')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('revocation_reason')
                    ->label('Alasan Pencabutan')
                    ->limit(30)
                    ->placeholder('-'),
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn (MemberCard $record): string => route('filament.sba.card.print', ['record' => $record->id]))
                    ->openUrlInNewTab()
                    ->visible(fn (MemberCard $record): bool => $record->status === MemberCard::STATUS_ACTIVE),
                Tables\Actions\Action::make('revoke')
                    ->label('Cabut')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cabut Kartu Anggota')
                    ->modalDescription('Kartu ini akan dicabut dan tidak dapat digunakan lagi.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Alasan Pencabutan')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (MemberCard $record, array $data): void {
                        $service = new MemberCardService;
                        $service->revoke($record, $data['reason'], auth()->user());

                        Notification::make()
                            ->title('Kartu berhasil dicabut')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (MemberCard $record): bool => $record->status === MemberCard::STATUS_ACTIVE),
                Tables\Actions\Action::make('reissue')
                    ->label('Ganti Kartu')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Ganti Kartu Anggota')
                    ->modalDescription('Kartu lama akan dicabut dan kartu baru akan diterbitkan.')
                    ->action(function (MemberCard $record): void {
                        $service = new MemberCardService;
                        $service->reissue($record, auth()->user());

                        Notification::make()
                            ->title('Kartu baru berhasil diterbitkan')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (MemberCard $record): bool => $record->status === MemberCard::STATUS_REVOKED),
                Tables\Actions\Action::make('issue')
                    ->label('Terbitkan Kartu')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Terbitkan Kartu Anggota')
                    ->modalDescription('Kartu baru akan diterbitkan untuk anggota ini.')
                    ->action(function (MemberCard $record): void {
                        $service = new MemberCardService;
                        $service->issue($record->member, auth()->user());

                        Notification::make()
                            ->title('Kartu berhasil diterbitkan')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (MemberCard $record): bool => $record->status === MemberCard::STATUS_REVOKED),
            ])
            ->headerActions([
                Tables\Actions\Action::make('issueNew')
                    ->label('Terbitkan Kartu Baru')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('member_id')
                            ->label('Pilih Anggota')
                            ->options(fn () => Member::where('organization_id', auth()->user()->organization_id)
                                ->where('status', Member::STATUS_ACTIVE)
                                ->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $member = Member::findOrFail($data['member_id']);
                        $service = new MemberCardService;

                        try {
                            $service->issue($member, auth()->user());

                            Notification::make()
                                ->title('Kartu berhasil diterbitkan untuk ' . $member->name)
                                ->success()
                                ->send();
                        } catch (\DomainException $e) {
                            Notification::make()
                                ->title('Gagal menerbitkan kartu')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        } catch (AuthorizationException $e) {
                            Notification::make()
                                ->title('Gagal menerbitkan kartu')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->paginated([10, 25, 50]);
    }

    public function getFilteredQuery(): Builder
    {
        $query = MemberCard::query()
            ->where('organization_id', auth()->user()->organization_id)
            ->with('member');

        $data = $this->data;

        if (! empty($data['member_id'])) {
            $query->where('member_id', $data['member_id']);
        }
        if (! empty($data['card_status'])) {
            $query->where('status', $data['card_status']);
        }

        return $query;
    }

    public function getStats(): array
    {
        $orgId = auth()->user()->organization_id;

        $total = MemberCard::where('organization_id', $orgId)->count();
        $active = MemberCard::where('organization_id', $orgId)
            ->where('status', MemberCard::STATUS_ACTIVE)->count();
        $revoked = MemberCard::where('organization_id', $orgId)
            ->where('status', MemberCard::STATUS_REVOKED)->count();

        $totalMembers = Member::where('organization_id', $orgId)
            ->where('status', Member::STATUS_ACTIVE)->count();
        $withoutCard = $totalMembers - $active;

        return [
            'total' => $total,
            'active' => $active,
            'revoked' => $revoked,
            'without_card' => max(0, $withoutCard),
        ];
    }
}
