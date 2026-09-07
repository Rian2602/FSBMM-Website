<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseQuizResource\Pages;
use App\Filament\Resources\CourseQuizResource\RelationManagers;
use App\Models\CourseLesson;
use App\Models\CourseQuiz;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CourseQuizResource extends Resource
{
    protected static ?string $model = CourseQuiz::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'E-Learning';

    protected static ?string $navigationLabel = 'Kuis';

    protected static ?string $modelLabel = 'Kuis';

    protected static ?string $pluralModelLabel = 'Kuis';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')->label('Judul')->required()->maxLength(255),
                Forms\Components\Select::make('course_id')
                    ->label('Kursus')
                    ->relationship('course', 'name')
                    ->required()
                    ->searchable()
                    ->disabledOn('edit'),
                Forms\Components\Select::make('lesson_id')
                    ->label('Pelajaran')
                    ->relationship('lesson', 'title')
                    ->searchable()
                    ->nullable()
                    ->helperText('Kosong hanya untuk kuis akhir kursus.'),
                Forms\Components\Select::make('pass_threshold')
                    ->label('Ambang Lulus')
                    ->options([50 => 50, 60 => 60, 70 => 70, 80 => 80, 90 => 90])
                    ->nullable()
                    ->default(70)
                    ->helperText('Kosong = pakai ambang default kursus.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Judul')->searchable(),
                Tables\Columns\TextColumn::make('course.name')->label('Kursus'),
                Tables\Columns\TextColumn::make('lesson.title')->label('Pelajaran')->placeholder('Kuis akhir'),
                Tables\Columns\TextColumn::make('questions_count')->label('Jml Soal')->counts('questions'),
                Tables\Columns\TextColumn::make('pass_threshold')->label('Ambang'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('course')->relationship('course', 'name'),
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
            RelationManagers\QuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourseQuizzes::route('/'),
            'create' => Pages\CreateCourseQuiz::route('/create'),
            'edit' => Pages\EditCourseQuiz::route('/{record}/edit'),
        ];
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        // (** executed: course_quizzes.course_id is NOT NULL; when the author
        // picks a lesson, the course must come from that lesson so the quiz
        // never lands in a different course than its lesson (same invariant
        // enforced in QuizzesRelationManager). **)
        if (($data['lesson_id'] ?? null) !== null) {
            $data['course_id'] = CourseLesson::find($data['lesson_id'])->course_id;
        }

        return $data;
    }
}
