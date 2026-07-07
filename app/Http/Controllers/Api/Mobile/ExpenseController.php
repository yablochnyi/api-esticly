<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;
        $org = User::query()->findOrFail($orgId);
        $tz = $org->timezone ?: (config('app.timezone') ?: 'UTC');

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card'])],
        ]);

        $from = Carbon::createFromFormat('Y-m-d', $data['from'], $tz)->toDateString();
        $to = Carbon::createFromFormat('Y-m-d', $data['to'], $tz)->toDateString();
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $q = Expense::query()
            ->where('user_id', $orgId)
            ->whereBetween('expense_date', [$from, $to])
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        if (!empty($data['payment_method'])) {
            $q->where('payment_method', $data['payment_method']);
        }

        return response()->json([
            'expenses' => $q->get()->map(fn (Expense $expense) => $this->payload($expense))->values(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;
        $org = User::query()->findOrFail($orgId);
        $tz = $org->timezone ?: (config('app.timezone') ?: 'UTC');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card'])],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $expense = Expense::query()->create([
            'user_id' => $orgId,
            'title' => trim((string) $data['title']),
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'] ?? 'cash',
            'expense_date' => Carbon::createFromFormat('Y-m-d', $data['expense_date'], $tz)->toDateString(),
            'note' => $data['note'] ?? null,
        ]);

        return response()->json($this->payload($expense), 201);
    }

    public function destroy(Request $request, Expense $expense)
    {
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        abort_unless((int) $expense->user_id === (int) $orgId, 404);

        $expense->delete();

        return response()->json(['ok' => true]);
    }

    private function payload(Expense $expense): array
    {
        return [
            'id' => (int) $expense->id,
            'user_id' => (int) $expense->user_id,
            'title' => (string) $expense->title,
            'amount' => (float) $expense->amount,
            'payment_method' => (string) ($expense->payment_method ?: 'cash'),
            'expense_date' => optional($expense->expense_date)?->format('Y-m-d'),
            'note' => $expense->note,
            'created_at' => optional($expense->created_at)?->toIso8601String(),
            'updated_at' => optional($expense->updated_at)?->toIso8601String(),
        ];
    }
}
