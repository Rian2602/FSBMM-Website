<?php

namespace App\Filament\Sba\Resources\EventResource\RelationManagers;

use App\Models\Attendance;
use App\Models\Member;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('member_id')
                ->label('Anggota')
                ->relationship('member', 'name', modifyQueryUsing: fn (Builder $q) => $q->where('organization_id', $this->getOwnerRecord()->organization_id))
                ->required()
                ->searchable()
                ->rules([
                    fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                        $event = $this->getOwnerRecord();

                        if (! Member::where('id', $value)->where('organization_id', $event->organization_id)->exists()) {
                            $fail('Anggota tidak terkait dengan organisasi ini.');

                            return;
                        }

                        // Composite unique (event_id, member_id) as a form error — excludes
                        // the currently edited row so editing an existing attendance works.
                        $query = Attendance::where('event_id', $event->id)->where('member_id', $value);
                        if (filled($this->mountedTableActionRecord)) {
                            $query->where('id', '!=', $this->mountedTableActionRecord);
                        }
                        if ($query->exists()) {
                            $fail('Anggota ini sudah dicatat kehadirannya untuk kegiatan ini.');
                        }
                    },
                ]),
            Forms\Components\Select::make('status')
                ->options(['hadir' => 'Hadir', 'izin' => 'Izin', 'tidak_hadir' => 'Tidak Hadir'])
                ->required()
                ->rules(['in:hadir,izin,tidak_hadir']),
            Forms\Components\Textarea::make('note')
                ->label('Catatan')
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('member.name')->label('Anggota')->searchable(),
            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->badge(fn (string $state) => match ($state) {
                    'hadir' => 'success',
                    'izin' => 'warning',
                    'tidak_hadir' => 'danger',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('note')->label('Catatan')->limit(50),
        ])->headerActions([
            CreateAction::make(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    protected function configureCreateAction(CreateAction $action): void
    {
        parent::configureCreateAction($action);

        // The framework's default process fills the record from form data only and
        // lets the HasMany relationship set event_id — but organization_id would be
        // left null. Inject it here (never client-supplied), plus event_id explicitly.
        $action->using(function (array $data): Attendance {
            $event = $this->getOwnerRecord();

            $record = new Attendance($data);
            $record->organization_id = $event->organization_id;
            $record->event_id = $event->id;
            $record->save();

            return $record;
        });
    }
}
