<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class LogsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Logs';
    protected static ?string $title = 'Logs';
    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.logs';

    public static function canAccess(): bool
    {
        return Gate::allows('access-filament-admin');
    }
}
