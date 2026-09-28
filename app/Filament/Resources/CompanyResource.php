<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\User;
use App\Support\OrgSubscription;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class CompanyResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Салоны';

    protected static ?string $modelLabel = 'салон';

    protected static ?string $pluralModelLabel = 'Салоны';

    protected static string|\UnitEnum|null $navigationGroup = 'Клиенты и коммуникации';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return Gate::allows('access-filament-admin');
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView($record): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return static::canAccess();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $query) {
                $query
                    ->whereNull('staff_id')
                    ->orWhere(function (Builder $query) {
                        $query
                            ->whereNotNull('staff_id')
                            ->whereColumn('organization_id', 'id');
                    });
            })
            ->where(function (Builder $query) {
                $query
                    ->whereNotNull('company_name')
                    ->orWhereNotNull('email')
                    ->orWhereNotNull('phone');
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Салон')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Эл. почта')
                    ->searchable()
                    ->copyable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('language_code')
                    ->label('Язык')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency_code')
                    ->label('Валюта')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_plan')
                    ->label('Тариф')
                    ->formatStateUsing(fn ($state, User $record) => match (OrgSubscription::normalizePlan($state)) {
                        OrgSubscription::PLAN_PRO => 'PRO',
                        OrgSubscription::PLAN_BASIC => 'BASIC',
                        default => 'Нет тарифа',
                    })
                    ->badge()
                    ->color(fn ($state) => match (OrgSubscription::normalizePlan($state)) {
                        OrgSubscription::PLAN_PRO => 'success',
                        OrgSubscription::PLAN_BASIC => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('subscription_active')
                    ->label('Активен')
                    ->state(fn (User $record) => OrgSubscription::hasActivePaidPlan($record))
                    ->boolean(),
                Tables\Columns\TextColumn::make('subscription_ends_at')
                    ->label('Оплачено до')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('registered_at')
                    ->label('Регистрация')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('timezone')
                    ->label('Часовой пояс')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('staff_count')
                    ->label('Команда')
                    ->counts('staff')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('language_code')
                    ->label('Язык')
                    ->options([
                        'pl' => 'PL',
                        'uk' => 'UK',
                        'en' => 'EN',
                        'it' => 'IT',
                        'fr' => 'FR',
                        'pt' => 'PT',
                        'de' => 'DE',
                        'es' => 'ES',
                        'cs' => 'CS',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('company_name')->label('Салон')->disabled(),
            TextInput::make('phone')->label('Телефон')->disabled(),
            TextInput::make('email')->label('Эл. почта')->disabled(),
            TextInput::make('language_code')->label('Язык')->disabled(),
            TextInput::make('currency_code')->label('Валюта')->disabled(),
            TextInput::make('timezone')->label('Часовой пояс')->disabled(),
            TextInput::make('address')->label('Адрес')->disabled(),
            DateTimePicker::make('registered_at')->label('Дата регистрации'),
            Select::make('subscription_plan')
                ->label('Тариф подписки')
                ->options([
                    OrgSubscription::PLAN_BASIC => 'Basic',
                    OrgSubscription::PLAN_PRO => 'Pro',
                ])
                ->native(false),
            DateTimePicker::make('subscription_ends_at')->label('Подписка действует до'),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            Pages\ViewCompany::class,
            Pages\ManageCompanyStaff::class,
            Pages\ManageCompanyClients::class,
            Pages\ManageCompanyServices::class,
            Pages\ManageCompanyVisits::class,
            Pages\ManageCompanyPortfolio::class,
            Pages\ManageCompanySms::class,
            Pages\ManageCompanySettings::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'view' => Pages\ViewCompany::route('/{record}'),
            'staff' => Pages\ManageCompanyStaff::route('/{record}/staff'),
            'clients' => Pages\ManageCompanyClients::route('/{record}/clients'),
            'services' => Pages\ManageCompanyServices::route('/{record}/services'),
            'visits' => Pages\ManageCompanyVisits::route('/{record}/visits'),
            'portfolio' => Pages\ManageCompanyPortfolio::route('/{record}/portfolio'),
            'sms' => Pages\ManageCompanySms::route('/{record}/sms'),
            'settings' => Pages\ManageCompanySettings::route('/{record}/settings'),
        ];
    }
}
