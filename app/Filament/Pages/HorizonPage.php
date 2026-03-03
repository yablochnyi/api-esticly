<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class HorizonPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationLabel = 'Queues';
    protected static ?string $title = 'Queues';
    protected static ?int $navigationSort = 91;

    protected string $view = 'filament.pages.horizon';

    public static function canAccess(): bool
    {
        return Gate::allows('access-filament-admin');
    }
}
