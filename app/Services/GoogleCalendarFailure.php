<?php

namespace App\Services;

class GoogleCalendarFailure extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
