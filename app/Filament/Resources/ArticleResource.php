<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'Artikel';

    protected static ?string $modelLabel = 'Artikel';

    protected static ?string $navigationGroup = 'Konten';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Judul')
                    ->required()
                    ->maxLength(200)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(200)
                    ->unique(ignoreRecord: true)
                    ->helperText('Otomatis diisi dari judul. Dipakai pada alamat /berita/{slug}.'),
                Forms\Components\Textarea::make('excerpt')
                    ->label('Ringkasan')
                    ->required()
                    ->maxLength(400)
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\RichEditor::make('body')
                    ->label('Isi')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('cover_image_path')
                    ->label('Gambar sampul')
                    ->image()
                    ->directory('articles')
                    ->columnSpanFull(),
                Forms\Components\Select::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(),
                    ]),
                Forms\Components\Select::make('author_id')
                    ->label('Penulis')
                    ->relationship('author', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->default(fn () => auth()->id()),
                Forms\Components\DateTimePicker::make('published_at')
                    ->label('Jadwal terbit')
                    ->seconds(false)
                    ->helperText('Kosongkan untuk menyimpan sebagai draf. Tanggal di masa depan = terjadwal.'),
                Forms\Components\Toggle::make('is_featured')
                    ->label('Artikel unggulan'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Penulis')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Terbit')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (Article $record): string {
                        if ($record->published_at === null) {
                            return 'Draf';
                        }

                        return $record->published_at->isPast() ? 'Tayang' : 'Terjadwal';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Tayang' => 'success',
                        'Terjadwal' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Unggulan')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'live' => 'Tayang',
                        'scheduled' => 'Terjadwal',
                        'draft' => 'Draf',
                    ])
                    ->query(function ($query, array $data): void {
                        $value = $data['value'] ?? null;

                        if ($value === 'draft') {
                            $query->whereNull('published_at');
                        } elseif ($value === 'scheduled') {
                            $query->where('published_at', '>', now());
                        } elseif ($value === 'live') {
                            $query->where('published_at', '<=', now());
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
