<?php
namespace App\Filament\Resources;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
class AdminAccessEventResource extends Resource
{
    protected static ?string $model = \App\Models\AdminAccessEvent::class;
    protected static ?string $navigationGroup = 'Administration';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $pluralModelLabel = 'Journal des accès administratifs';
    public static function canViewAny(): bool { return auth()->user()?->hasRole('super_admin') ?? false; }
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }
    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            Tables\Columns\TextColumn::make('created_at')->label('Date')->dateTime(),
            Tables\Columns\TextColumn::make('actor_id')->label('Auteur (ID)'),
            Tables\Columns\TextColumn::make('user_id')->label('Compte (ID)'),
            Tables\Columns\TextColumn::make('department_id')->label('Département (ID)'),
            Tables\Columns\TextColumn::make('event')->label('Action'),
            Tables\Columns\TextColumn::make('changes')->label('Modifications')->getStateUsing(fn ($record) => json_encode($record->changes, JSON_UNESCAPED_UNICODE))->wrap(),
        ]);
    }
    public static function getPages(): array { return ['index' => AdminAccessEventResource\Pages\ListAdminAccessEvents::route('/')]; }
}
