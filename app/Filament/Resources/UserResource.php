<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    /** User management is federation super-admin only (spec §5). */
    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if (! ($user?->isSuperAdmin() ?? false)) {
            return false;
        }

        return $record instanceof User && static::roleChangeAllowed($record, $user);
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();

        if (! ($user?->isSuperAdmin() ?? false)) {
            return false;
        }

        return $record instanceof User && static::roleChangeAllowed($record, $user);
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();

        if (! ($user?->isSuperAdmin() ?? false)) {
            return false;
        }

        // Anti-lockout: never allow bulk-deleting the only super admin.
        return User::where('role', User::ROLE_SUPER_ADMIN)->count() > 1;
    }

    /**
     * Defensive guard: a super admin must not remove or demote their own
     * account, nor the last remaining super admin — both would lock the panel.
     */
    protected static function roleChangeAllowed(User $record, User $actor): bool
    {
        // Never act on your own account.
        if ($record->is($actor)) {
            return false;
        }

        // Never remove the last remaining super admin.
        if ($record->role === User::ROLE_SUPER_ADMIN) {
            return User::where('role', User::ROLE_SUPER_ADMIN)->count() > 1;
        }

        return true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(200),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\Select::make('role')
                    ->options([
                        User::ROLE_SUPER_ADMIN => 'Super Admin (Federasi)',
                        User::ROLE_EDITOR => 'Editor Konten (Federasi)',
                        User::ROLE_SBA_ADMIN => 'Pengurus SBA (Akun Organisasi)',
                    ])
                    ->default(User::ROLE_EDITOR)
                    ->live()
                    ->required(),
                Forms\Components\Select::make('organization_id')
                    ->label('Organisasi SBA')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload()
                    ->required(fn (Forms\Get $get): bool => $get('role') === User::ROLE_SBA_ADMIN)
                    ->visible(fn (Forms\Get $get): bool => $get('role') === User::ROLE_SBA_ADMIN)
                    ->dehydrated(fn (Forms\Get $get): bool => $get('role') === User::ROLE_SBA_ADMIN)
                    ->helperText('Akun pengurus SBA wajib ditautkan ke satu organisasi.'),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->helperText('Kosongkan jika tidak ingin mengubah kata sandi'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisasi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(static function (string $state): string {
                        return match ($state) {
                            User::ROLE_SUPER_ADMIN => 'Super Admin',
                            User::ROLE_SBA_ADMIN => 'Pengurus SBA',
                            default => 'Editor',
                        };
                    }),
                // ->color() left default until brand palette lands
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->label('Dibuat'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        User::ROLE_SUPER_ADMIN => 'Super Admin',
                        User::ROLE_EDITOR => 'Editor',
                        User::ROLE_SBA_ADMIN => 'Pengurus SBA',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->using(static function (Collection $records): void {
                            $actor = auth()->user();

                            // Never delete the actor's own account via bulk delete.
                            $candidates = $records->reject(
                                static fn (User $record): bool => $actor && $record->is($actor)
                            );

                            // Keep at least one super admin in the system (anti-lockout),
                            // matching the single-row canDelete() guard.
                            $superAdmins = $candidates->where('role', User::ROLE_SUPER_ADMIN);
                            $totalSuperAdmins = User::where('role', User::ROLE_SUPER_ADMIN)->count();
                            $removable = max(0, $totalSuperAdmins - 1);

                            $candidates->where('role', '!=', User::ROLE_SUPER_ADMIN)
                                ->merge($superAdmins->take($removable))
                                ->each(static fn (User $record): bool => (bool) $record->delete());
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
