<?php

namespace App\Support;

use App\Models\DeviceToken;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitReminderDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VisitReminders
{
    /**
     * Run reminders sender. Intended to be called every minute.
     */
    public static function run(): void
    {
        $serverKey = trim((string) env('FCM_SERVER_KEY', ''));
        if ($serverKey === '') {
            // Not configured; don't crash.
            return;
        }

        $now = now()->utc();

        // Grace window to avoid missing reminders due to scheduler drift.
        $windowStart = (clone $now)->subSeconds(30);
        $windowEnd = (clone $now)->addSeconds(30);

        // Upper bound: UI offsets are short; keep small but safe.
        $maxHorizon = (clone $now)->addDays(2);

        $visits = Visit::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('starts_at', [$now, $maxHorizon])
            ->with(['service:id,name'])
            ->get(['id', 'user_id', 'staff_id', 'service_id', 'client_name', 'starts_at']);

        if ($visits->isEmpty()) return;

        $ownerIds = $visits->pluck('user_id')->unique()->values()->all();
        $staffIds = $visits->pluck('staff_id')->filter()->unique()->values()->all();

        /** @var array<int, User> $ownersById */
        $ownersById = User::query()
            ->whereIn('id', $ownerIds)
            ->get(['id', 'company_name', 'reminder_offsets_min'])
            ->keyBy('id')
            ->all();

        /** @var array<int, User> $staffUsersByStaffId */
        $staffUsersByStaffId = [];
        if (!empty($staffIds)) {
            $staffUsersByStaffId = User::query()
                ->whereIn('staff_id', $staffIds)
                ->get(['id', 'staff_id', 'name', 'reminder_offsets_min', 'organization_id'])
                ->keyBy('staff_id')
                ->all();
        }

        foreach ($visits as $v) {
            $startsAt = $v->starts_at ? $v->starts_at->copy()->utc() : null;
            if (!$startsAt) continue;

            // Owner: remind for all org visits (owner is admin).
            $owner = $ownersById[(int) $v->user_id] ?? null;
            if ($owner) {
                self::sendForRecipient(
                    visit: $v,
                    recipient: $owner,
                    windowStart: $windowStart,
                    windowEnd: $windowEnd,
                    serverKey: $serverKey,
                );
            }

            // Staff: remind only for own visits.
            if (!empty($v->staff_id)) {
                $staffUser = $staffUsersByStaffId[(int) $v->staff_id] ?? null;
                if ($staffUser) {
                    self::sendForRecipient(
                        visit: $v,
                        recipient: $staffUser,
                        windowStart: $windowStart,
                        windowEnd: $windowEnd,
                        serverKey: $serverKey,
                    );
                }
            }
        }
    }

    private static function offsets(User $u): array
    {
        $raw = $u->reminder_offsets_min;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $x) {
            $v = is_numeric($x) ? (int) $x : null;
            if ($v === null || $v <= 0) continue;
            if ($v > 60 * 24 * 7) continue; // cap: 7 days
            $out[] = $v;
        }
        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    private static function sendForRecipient(Visit $visit, User $recipient, $windowStart, $windowEnd, string $serverKey): void
    {
        $offsets = self::offsets($recipient);
        if (empty($offsets)) return;

        $startsAt = $visit->starts_at->copy()->utc();
        $serviceName = $visit->service?->name ?? 'Visit';
        $clientName = trim((string) ($visit->client_name ?? ''));

        foreach ($offsets as $m) {
            $dueAt = (clone $startsAt)->subMinutes($m);
            if ($dueAt->lt($windowStart) || $dueAt->gt($windowEnd)) continue;

            // Deduplicate
            try {
                VisitReminderDelivery::query()->create([
                    'visit_id' => $visit->id,
                    'user_id' => $recipient->id,
                    'offset_min' => $m,
                    'due_at' => $dueAt,
                    'sent_at' => now()->utc(),
                    'status' => 'sent',
                ]);
            } catch (\Throwable $e) {
                // likely unique constraint => already sent
                continue;
            }

            $tokens = DeviceToken::query()
                ->where('user_id', $recipient->id)
                ->orderByDesc('last_seen_at')
                ->limit(20)
                ->pluck('token')
                ->filter()
                ->values()
                ->all();

            if (empty($tokens)) continue;

            $title = 'Reminder';
            $body = $m >= 60
                ? ('In ' . floor($m / 60) . 'h ' . ($m % 60 > 0 ? ($m % 60) . 'm ' : '') . ': ' . $serviceName)
                : ('In ' . $m . ' min: ' . $serviceName);
            if ($clientName !== '') {
                $body .= ' • ' . $clientName;
            }

            // Legacy FCM API (simple). Requires env(FCM_SERVER_KEY).
            try {
                Http::withHeaders([
                    'Authorization' => 'key=' . $serverKey,
                    'Content-Type' => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'registration_ids' => $tokens,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => [
                        'type' => 'visit_reminder',
                        'visit_id' => (string) $visit->id,
                        'offset_min' => (string) $m,
                    ],
                ]);
            } catch (\Throwable $e) {
                // Update status to failed (best-effort)
                VisitReminderDelivery::query()
                    ->where('visit_id', $visit->id)
                    ->where('user_id', $recipient->id)
                    ->where('offset_min', $m)
                    ->update([
                        'status' => 'failed',
                        'error' => Str::limit($e->getMessage(), 255),
                    ]);
            }
        }
    }
}

