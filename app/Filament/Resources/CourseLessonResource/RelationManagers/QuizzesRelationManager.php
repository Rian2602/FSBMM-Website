<?php

namespace App\Filament\Resources\CourseLessonResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class QuizzesRelationManager extends RelationManager
{
    protected static string $relationship = 'quizzes';

    protected static ?string $title = 'Kuis';

    protected static ?string $modelLabel = 'Kuis';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('Judul')->required()->maxLength(255),
            Forms\Components\Select::make('pass_threshold')
                ->label('Ambang Lulus')
                ->options([50 => 50, 60 => 60, 70 => 70, 80 => 80, 90 => 90])
                ->nullable()
                ->default(70)
                ->helperText('Kosong = pakai ambang default kursus.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Judul')->searchable(),
                Tables\Columns\TextColumn::make('questions_count')->label('Jml Soal')->counts('questions'),
                Tables\Columns\TextColumn::make('pass_threshold')->label('Ambang'),
            ])
            // (** executed: the relationship is lesson's quizzes, so the RM
            // only fills lesson_id — course_id must be copied from the owning
            // lesson or the NOT NULL constraint fails. Plan Task 2 snippet
            // omitted this. **)
            ->headerActions([
                // (** executed: plan snippet used `mutateDataUsing`, which
                // doesn't exist in Filament v3.3.55 — the real API is
                // `mutateFormDataUsing`. **)
                Tables\Actions\CreateAction::make()->mutateFormDataUsing(function (array $data): array {
                    $data['course_id'] = $this->getOwnerRecord()->course_id;

                    return $data;
                }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
