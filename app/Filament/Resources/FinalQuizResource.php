<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinalQuizResource\Pages;
use App\Filament\Resources\FinalQuizResource\RelationManagers\QuestionsRelationManager;
use App\Models\CourseQuiz;
use Closure;
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
                ->disabledOn('edit')
                // (** executed: Task 4 evaluation (F4-1) — the authoring rule
                // "at most one final quiz per course" (the RM create is
                // guarded in FinalQuizRelationManager). Course::finalQuiz()
                // takes the first lesson_id-null row, so a second is dead
                // weight. The rule only fires on create — the field is
                // disabled on edit. **)
                ->rules(
                    fn (): array => [
                        function (string $attribute, mixed $value, Closure $fail): void {
                            if (CourseQuiz::query()->where('course_id', $value)->whereNull('lesson_id')->exists()) {
                                $fail('Kursus ini sudah punya kuis akhir.');
                            }
                        },
                    ],
                    // (** executed: disabled-on-edit fields still validate with
                    // their existing value, so the rule must not fire while
                    // editing the course's own final quiz. **)
                    fn (Forms\Components\Field $component): bool => ! $component->isDisabled(),
                ),
            Forms\Components\TextInput::make('title')->label('Judul')->required()->maxLength(255),
            Forms\Components\Select::make('pass_threshold')
                ->label('Ambang Lulus')
                ->options([50 => 50, 60 => 60, 70 => 70, 80 => 80, 90 => 90])
                // (** executed: Task 4 evaluation (F4-2) — spec §6a keeps the
                // course default as the fallback; the field must be nullable so
                // an author can defer to course->pass_threshold like
                // CourseQuizResource (default(70) alone would pin every final
                // at 70). **)
                ->nullable()
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
