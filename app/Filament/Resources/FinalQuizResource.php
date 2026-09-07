<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinalQuizResource\Pages;
use App\Filament\Resources\FinalQuizResource\RelationManagers\QuestionsRelationManager;
use App\Models\CourseQuiz;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinalQuizResource extends Resource
{
    protected static ?string $model = CourseQuiz::class;

    protected static ?string $navigationGroup = 'E-Learning';

    protected static ?string $navigationLabel = 'Kuis Akhir';

    protected static ?string $modelLabel = 'Kuis Akhir';

    protected static ?string $pluralModelLabel = 'Kuis Akhir';

    /** Only course-level (final) quizzes show here. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('lesson_id');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('course_id')
                ->label('Kursus')
                ->relationship('course', 'title')
                ->required()
                ->searchable()
                ->disabledOn('edit'),
            Forms\Components\TextInput::make('title')->label('Judul')->required()->maxLength(255),
            Forms\Components\Select::make('pass_threshold')
                ->label('Ambang Lulus')
                ->options([50 => 50, 60 => 60, 70 => 70, 80 => 80, 90 => 90])
                ->default(70),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('course.title')->label('Kursus')->searchable(),
                Tables\Columns\TextColumn::make('title')->label('Judul'),
                Tables\Columns\TextColumn::make('questions_count')->label('Jml Soal')->counts('questions'),
                Tables\Columns\TextColumn::make('pass_threshold')->label('Ambang'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [QuestionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinalQuizzes::route('/'),
            'create' => Pages\CreateFinalQuiz::route('/create'),
            'edit' => Pages\EditFinalQuiz::route('/{record}/edit'),
        ];
    }
}
