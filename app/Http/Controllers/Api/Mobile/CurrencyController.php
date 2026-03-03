<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function index()
    {
        return Currency::query()
            ->select(['id', 'code', 'name', 'symbol'])
            ->orderBy('name')
            ->get();
    }
}
