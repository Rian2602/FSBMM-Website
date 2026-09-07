<?php

namespace App\Filament\Resources\QuizQuestionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
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
        // (** executed: plan snippet used `orderColumn('sort_order')`, which
        // doesn't exist in Filament v3.3.55 — the real API is `reorderable()`. **)
        return $table
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('option')->label('Opsi'),
                Tables\Columns\IconColumn::make('is_correct')->label('Benar')->boolean(),
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
