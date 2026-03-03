<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function store(Request $request)
    {
        $u = $request->user();
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'string', 'in:ios,android'],
        ]);

        $token = trim($data['token']);
        if ($token === '') {
            return response()->json(['message' => 'Invalid token'], 422);
        }

        DeviceToken::query()->updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $u->id,
                'platform' => $data['platform'],
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['ok' => true]);
    }
}

