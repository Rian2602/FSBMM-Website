<?php

namespace App\Filament\Resources\CourseResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinalQuizRelationManager extends RelationManager
{
    protected static string $relationship = 'quizzes';

    protected static ?string $title = 'Kuis Akhir';

    protected static ?string $modelLabel = 'Kuis Akhir';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('Judul')->required()->maxLength(255),
            Forms\Components\Select::make('pass_threshold')
                ->label('Ambang Lulus')
                ->options([50 => 50, 60 => 60, 70 => 70, 80 => 80, 90 => 90])
                ->default(70),
        ]);
    }

    public function table(Table $table): Table
    {
        // (** executed: plan snippet overrode getTableQuery(), which is
        // deprecated in Filament v3.3.55 and returns null here (the RM never
        // calls it) — the working hook is Table::modifyQueryUsing(). **)
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('lesson_id'))
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Judul'),
                Tables\Columns\TextColumn::make('questions_count')->label('Jml Soal')->counts('questions'),
                Tables\Columns\TextColumn::make('pass_threshold')->label('Ambang'),
            ])
            ->headerActions([
                // (** executed: plan snippet used `mutateDataUsing`, which does
                // not exist in Filament v3.3.55 — the real API is
                // `mutateFormDataUsing` (Task 2 evaluation finding). The action
                // was then moved to a full closure for the Task 4 evaluation
                // guard (F4-1): at most one final quiz per course, mirroring the
                // Task 3 options guard — Course::finalQuiz()->first() resolves
                // completion, so a second final would be dead weight. The owner
                // course FK comes from the relation create. **)
                Tables\Actions\CreateAction::make()
                    ->action(function (Tables\Actions\CreateAction $action, array $data) {
                        $data['lesson_id'] = null;

                        if ($this->getOwnerRecord()->quizzes()->whereNull('lesson_id')->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Kursus ini sudah punya kuis akhir.')
                                ->body('Hapus kuis akhir yang lama terlebih dahulu.')
                                ->send();
                            $action->halt();
                        }

                        $this->getOwnerRecord()->quizzes()->create($data);
                        $action->success();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
