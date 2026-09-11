<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuizQuestionResource\Pages;
use App\Filament\Resources\QuizQuestionResource\RelationManagers;
use App\Models\QuizQuestion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuizQuestionResource extends Resource
{
    protected static ?string $model = QuizQuestion::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'E-Learning';

    protected static ?string $navigationLabel = 'Soal';

    protected static ?string $modelLabel = 'Soal';

    protected static ?string $pluralModelLabel = 'Soal';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // (** executed: the FK column is course_quiz_id — a Select
                // named `quiz_id` would write to a non-existent column and
                // trip NOT NULL on insert (same naming trap as Task 1/2). **)
                Forms\Components\Select::make('course_quiz_id')
                    ->label('Kuis')
                    ->relationship('quiz', 'title')
                    ->required()
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->course?->title . ' — ' . $record->title),
                Forms\Components\Textarea::make('question')->label('Pertanyaan')->required()->rows(3)->columnSpanFull(),
                Forms\Components\TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('quiz.title')->label('Kuis'),
                Tables\Columns\TextColumn::make('question')->label('Pertanyaan')->limit(60),
                Tables\Columns\TextColumn::make('options_count')->label('Jml Opsi')->counts('options'),
                Tables\Columns\TextColumn::make('sort_order')->label('Urutan')->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuizQuestions::route('/'),
            'create' => Pages\CreateQuizQuestion::route('/create'),
            'edit' => Pages\EditQuizQuestion::route('/{record}/edit'),
        ];
    }
}
