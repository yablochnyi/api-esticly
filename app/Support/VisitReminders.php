<?php

namespace App\Support;

use App\Jobs\SendVisitReminderSms;
use App\Jobs\SendVisitReminderPush;
use App\Models\MarketingAutomation;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitReminderDelivery;

class VisitReminders
{
    /**
     * Run reminders sender. Intended to be called every minute.
     */
    public static function run(): void
    {
        $pushConfigured = FcmV1::isConfigured();

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
        $orgIds = $visits->pluck('user_id')->unique()->values()->all();

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

        /** @var array<int, MarketingAutomation> $smsReminderByOrg */
        $smsReminderByOrg = [];
        if (!empty($orgIds)) {
            $smsReminderByOrg = MarketingAutomation::query()
                ->whereIn('user_id', $orgIds)
                ->where('key', 'visit_reminder_sms')
                ->where('enabled', true)
                ->get(['user_id', 'delay_min'])
                ->keyBy('user_id')
                ->all();
        }

        foreach ($visits as $v) {
            $startsAt = $v->starts_at ? $v->starts_at->copy()->utc() : null;
            if (!$startsAt) continue;

            // Owner: remind for all org visits (owner is admin).
            $owner = $ownersById[(int) $v->user_id] ?? null;
            if ($owner && $pushConfigured) {
                self::sendForRecipient(
                    visit: $v,
                    recipient: $owner,
                    windowStart: $windowStart,
                    windowEnd: $windowEnd,
                );
            }

            // Staff: remind only for own visits.
            if ($pushConfigured && !empty($v->staff_id)) {
                $staffUser = $staffUsersByStaffId[(int) $v->staff_id] ?? null;
                if ($staffUser) {
                    self::sendForRecipient(
                        visit: $v,
                        recipient: $staffUser,
                        windowStart: $windowStart,
                        windowEnd: $windowEnd,
                    );
                }
            }

            $smsAutomation = $smsReminderByOrg[(int) $v->user_id] ?? null;
            if ($smsAutomation) {
                self::sendSmsForVisit(
                    visit: $v,
                    delayMin: (int) ($smsAutomation->delay_min ?? 0),
                    windowStart: $windowStart,
                    windowEnd: $windowEnd,
                );
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

    private static function sendForRecipient(Visit $visit, User $recipient, $windowStart, $windowEnd): void
    {
        $offsets = self::offsets($recipient);
        if (empty($offsets)) return;

        $startsAt = $visit->starts_at->copy()->utc();

        foreach ($offsets as $m) {
            $dueAt = (clone $startsAt)->subMinutes($m);
            if ($dueAt->lt($windowStart) || $dueAt->gt($windowEnd)) continue;

            try {
                $delivery = VisitReminderDelivery::query()->create([
                    'visit_id' => $visit->id,
                    'user_id' => $recipient->id,
                    'offset_min' => $m,
                    'due_at' => $dueAt,
                    'sent_at' => null,
                    'status' => 'pending',
                    'error' => null,
                ]);
                SendVisitReminderPush::dispatch((int) $delivery->id);
            } catch (\Throwable $e) {
                // likely unique constraint => already sent
                continue;
            }
        }
    }

    private static function sendSmsForVisit(Visit $visit, int $delayMin, $windowStart, $windowEnd): void
    {
        if ($delayMin <= 0) return;

        $startsAt = $visit->starts_at->copy()->utc();
        $dueAt = (clone $startsAt)->subMinutes($delayMin);
        if ($dueAt->lt($windowStart) || $dueAt->gt($windowEnd)) return;

        SendVisitReminderSms::dispatch((int) $visit->id, $delayMin);
    }
}
