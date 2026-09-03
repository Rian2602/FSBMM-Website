<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms;
use Filament\Forms\Components\Builder;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Halaman';

    protected static ?string $modelLabel = 'Halaman';

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
                    ->disabled(fn (?Page $record): bool => $record !== null && $record->isStructural())
                    ->helperText('Alamat halaman. home/tentang/kontak dipakai sebagai halaman tetap dan tidak dapat diubah/dihapus.'),
                Forms\Components\TextInput::make('meta_title')
                    ->label('Judul SEO (opsional)')
                    ->maxLength(200)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('meta_description')
                    ->label('Deskripsi SEO (opsional)')
                    ->maxLength(300)
                    ->rows(2)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_published')
                    ->label('Terbit di situs publik'),
                Builder::make('blocks')
                    ->label('Blok halaman')
                    ->addActionLabel('Tambah blok')
                    ->reorderableWithButtons()
                    ->collapsible()
                    ->blockNumbers(false)
                    ->blocks([
                        Builder\Block::make('hero')
                            ->label('Hero (Judul Besar)')
                            ->schema([
                                Forms\Components\TextInput::make('eyebrow')->label('Label kecil (opsional)')->maxLength(60),
                                Forms\Components\TextInput::make('title')->label('Judul utama'),
                                Forms\Components\TextInput::make('subtitle')->label('Subjudul (opsional)'),
                                Forms\Components\FileUpload::make('image_path')->label('Gambar (opsional)')->image()->directory('pages'),
                                Forms\Components\TextInput::make('cta_label')->label('Teks tombol (opsional)'),
                                Forms\Components\TextInput::make('cta_url')->label('Alamat tombol (opsional)'),
                            ]),
                        Builder\Block::make('rich_text')
                            ->label('Teks Kaya')
                            ->schema([
                                Forms\Components\RichEditor::make('content')->label('Isi teks'),
                            ]),
                        Builder\Block::make('image')
                            ->label('Gambar')
                            ->schema([
                                Forms\Components\FileUpload::make('image_path')->label('Gambar')->image()->directory('pages')->required(),
                                Forms\Components\TextInput::make('caption')->label('Keterangan (opsional)'),
                            ]),
                        Builder\Block::make('stats')
                            ->label('Statistik')
                            ->schema([
                                Forms\Components\Repeater::make('items')
                                    ->label('Angka statistik')
                                    ->schema([
                                        Forms\Components\TextInput::make('label')->label('Label')->required(),
                                        Forms\Components\TextInput::make('value')->label('Nilai')->required(),
                                    ])
                                    ->defaultItems(2)
                                    ->columns(2),
                            ]),
                        Builder\Block::make('cta')
                            ->label('Ajakan (CTA)')
                            ->schema([
                                Forms\Components\TextInput::make('title')->label('Judul'),
                                Forms\Components\TextInput::make('body')->label('Teks'),
                                Forms\Components\TextInput::make('label')->label('Teks tombol'),
                                Forms\Components\TextInput::make('url')->label('Alamat tombol'),
                            ]),
                        Builder\Block::make('quote')
                            ->label('Kutipan')
                            ->schema([
                                Forms\Components\Textarea::make('quote')->label('Isi kutipan')->rows(3),
                                Forms\Components\TextInput::make('author')->label('Nama (opsional)'),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->afterStateHydrated(function (Builder $component): void {
                        $record = $component->getRecord();

                        if ($record === null || ! method_exists($record, 'blocks')) {
                            return;
                        }

                        $component->state(
                            $record->blocks()
                                ->orderBy('sort_order')
                                ->get()
                                ->map(fn ($block): array => [
                                    'type' => $block->type,
                                    'data' => $block->payload ?? [],
                                ])
                                ->all()
                        );
                    }),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Alamat')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('blocks_count')
                    ->label('Blok')
                    ->counts('blocks'),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Terbit')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Status publikasi'),
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

    /** Structural pages (home/tentang/kontak) back named routes — never deletable. */
    public static function canDelete($record): bool
    {
        return ! ($record instanceof Page && $record->isStructural());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
