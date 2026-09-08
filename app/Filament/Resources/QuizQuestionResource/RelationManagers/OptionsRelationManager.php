<?php

namespace App\Filament\Resources\QuizQuestionResource\RelationManagers;

use App\Models\QuizOption;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    protected static ?string $title = 'Opsi Jawaban';

    protected static ?string $modelLabel = 'Opsi';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('option')->label('Teks Opsi')->required()->columnSpanFull(),
            Forms\Components\Toggle::make('is_correct')->label('Kunci jawaban benar'),
            Forms\Components\TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        // (** executed: run the `orderColumn('sort_order')` plan snippet into
        // `reorderable('sort_order')` (doesn't exist in Filament v3.3.55), and
        // add the Task 3 evaluation guards (C-T3-1, spec §7): the exact-one
        // correct-option invariant is enforced in the actions — score() takes
        // the FIRST correct option and turns a correct-less question into a
        // permanent wrong, so the UI must not allow a second key or the
        // deletion of the only key. halt() throws the Halt exception, so the
        // create/update/delete line below it never runs when guarded. **)
        return $table
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('option')->label('Opsi'),
                Tables\Columns\IconColumn::make('is_correct')->label('Benar')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->action(function (Tables\Actions\CreateAction $action, array $data) {
                        $this->guardExclusiveCorrect($action, $data['is_correct'] ?? false);

                        $this->getOwnerRecord()->options()->create($data);
                        $action->success();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->action(function (Tables\Actions\EditAction $action, QuizOption $record, array $data) {
                        $this->guardExclusiveCorrect($action, $data['is_correct'] ?? false, $record->getKey());

                        $record->update($data);
                        $action->success();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->action(function (Tables\Actions\DeleteAction $action, QuizOption $record) {
                        $isLastCorrect = $record->is_correct
                            && $this->getOwnerRecord()->options()->where('is_correct', true)->count() === 1;

                        if ($isLastCorrect) {
                            Notification::make()
                                ->danger()
                                ->title('Jangan hapus satu-satunya kunci jawaban.')
                                ->body('Tandai opsi lain sebagai benar terlebih dahulu.')
                                ->send();
                            $action->halt();
                        }

                        $record->delete();
                        $action->success();
                    }),
            ]);
    }

    protected function guardExclusiveCorrect(Tables\Actions\Action $action, bool $isCorrect, mixed $ignoreOptionId = null): void
    {
        if (! $isCorrect) {
            return;
        }

        $hasOtherCorrect = $this->getOwnerRecord()->options()
            ->where('is_correct', true)
            ->when($ignoreOptionId !== null, fn ($query) => $query->whereKeyNot($ignoreOptionId))
            ->exists();

        if ($hasOtherCorrect) {
            Notification::make()
                ->danger()
                ->title('Soal ini sudah punya kunci jawaban.')
                ->body('Matikan kunci pada opsi yang lama terlebih dahulu.')
                ->send();
            $action->halt();
        }
    }
}
