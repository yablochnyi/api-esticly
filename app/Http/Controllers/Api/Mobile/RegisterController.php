<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Support\MediaUrl;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    public function store(Request $request)
    {
        // schedule приходит строкой JSON из multipart -> делаем массив
        if (is_string($request->input('schedule'))) {
            $decoded = json_decode($request->input('schedule'), true);
            if (is_array($decoded)) {
                $request->merge(['schedule' => $decoded]);
            }
        }

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'currency_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'schedule' => ['required', 'array'],

            // ВАЖНО: файл логотипа
            'logo' => ['nullable', 'image', 'max:4096'],
        ]);

        $user = $request->user();

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public'); // storage/app/public/logos
            $user->logo_path = $path;
        }

        $user->company_name = $data['company_name'];
        $user->address = $data['address'] ?? null;
        $user->description = $data['description'] ?? null;
        $user->currency_code = strtoupper($data['currency_code']);
        $user->schedule = $data['schedule'];
        if (empty($user->registered_at)) {
            $user->registered_at = Carbon::now();
        }

        $user->save();

        return response()->json([
            'ok' => true,
            'user' => $user->only(['id','phone','company_name','currency_code','logo_path']),
            'logo_url' => MediaUrl::publicFile($user->logo_path),
        ]);
    }
}
