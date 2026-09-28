<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class HorizonPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Очереди задач';

    protected static ?string $title = 'Очереди задач';

    protected static string|\UnitEnum|null $navigationGroup = 'Система';

    protected static ?int $navigationSort = 91;

    protected string $view = 'filament.pages.horizon';

    public static function canAccess(): bool
    {
        return Gate::allows('access-filament-admin');
    }
}
