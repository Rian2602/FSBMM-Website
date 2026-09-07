<?php

namespace App\Filament\Resources\FinalQuizResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

// (** executed: kept as an explicit separate copy of the CourseQuiz
// QuestionsRelationManager per the repo convention (no shared classes). **)
class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Soal';

    protected static ?string $modelLabel = 'Soal';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Textarea::make('question')->label('Pertanyaan')->required()->rows(3)->columnSpanFull(),
            Forms\Components\TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('Urutan')->sortable(),
                Tables\Columns\TextColumn::make('question')->label('Pertanyaan')->limit(60),
                Tables\Columns\TextColumn::make('options_count')->label('Jml Opsi')->counts('options'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
