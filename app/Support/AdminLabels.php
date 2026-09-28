<?php

namespace App\Support;

class AdminLabels
{
    public static function state(?string $state): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        $key = 'admin.states.'.$state;

        return trans()->has($key) ? __($key) : $state;
    }
}
