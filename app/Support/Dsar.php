<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientNote;
use App\Models\DsarOperation;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitAgreement;
use App\Models\VisitPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class Dsar
{
    public static function orgId(User $user): int
    {
        return (int) ($user->organization_id ?? $user->id);
    }

    public static function isOwner(User $user): bool
    {
        return empty($user->staff_id);
    }

    public static function normalizeLanguage(?string $lang): string
    {
        $v = strtolower(trim((string) $lang));
        if ($v === '') return 'pl';
        if (str_starts_with($v, 'uk')) return 'uk';
        if (str_starts_with($v, 'pl')) return 'pl';
        if (str_starts_with($v, 'en')) return 'en';
        return 'pl';
    }

    public static function createOperation(
        int $orgId,
        ?int $actorUserId,
        ?int $actorStaffId,
        ?int $clientId,
        string $type,
        ?string $languageCode = null,
        ?string $targetEmail = null,
        ?array $meta = null,
    ): DsarOperation {
        return DsarOperation::query()->create([
            'org_id' => $orgId,
            'actor_user_id' => $actorUserId,
            'actor_staff_id' => $actorStaffId,
            'client_id' => $clientId,
            'type' => $type,
            'status' => 'pending',
            'language_code' => $languageCode,
            'target_email' => $targetEmail,
            'meta' => $meta,
        ]);
    }

    public static function runClientExport(DsarOperation $op, User $org, Client $client, string $languageCode, string $targetEmail): DsarOperation
    {
        $op->update([
            'status' => 'running',
            'started_at' => now(),
            'language_code' => $languageCode,
            'target_email' => $targetEmail,
        ]);

        try {
            $payload = self::buildClientPayload($org, $client, $languageCode);
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($json === false) {
                throw new \RuntimeException('json_encode_failed');
            }

            $stamp = now()->format('Ymd_His');
            $fileName = "client_{$client->id}_{$stamp}.json";
            $relativePath = "dsar_exports/org_{$org->id}/{$fileName}";
            Storage::disk('local')->put($relativePath, $json);

            Mail::raw(self::mailBody('export_client', $languageCode, $org, $client), function ($m) use ($targetEmail, $languageCode, $json, $fileName) {
                $m->to($targetEmail)
                    ->subject(self::mailSubject('export_client', $languageCode))
                    ->attachData($json, $fileName, ['mime' => 'application/json; charset=UTF-8']);
            });

            $op->update([
                'status' => 'done',
                'finished_at' => now(),
                'payload_path' => $relativePath,
                'payload_sha256' => hash('sha256', $json),
                'payload_bytes' => strlen($json),
                'error_message' => null,
            ]);

            Log::channel('dsar')->info('dsar_export_client_done', [
                'dsar_operation_id' => (int) $op->id,
                'org_id' => (int) $org->id,
                'client_id' => (int) $client->id,
                'target_email' => $targetEmail,
                'language_code' => $languageCode,
                'payload_path' => $relativePath,
                'payload_bytes' => strlen($json),
            ]);

            Audit::custom(
                $org->id,
                'dsar_export_client',
                old: null,
                new: [
                    'dsar_operation_id' => $op->id,
                    'client_id' => $client->id,
                    'target_email' => $targetEmail,
                ],
                source: 'api_mobile'
            );
        } catch (\Throwable $e) {
            $op->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            Log::channel('dsar')->error('dsar_export_client_failed', [
                'dsar_operation_id' => (int) $op->id,
                'org_id' => (int) $org->id,
                'client_id' => (int) $client->id,
                'target_email' => $targetEmail,
                'language_code' => $languageCode,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $op->fresh();
    }

    public static function runAnonymizeClient(DsarOperation $op, User $org, User $actor, Client $client, string $languageCode): DsarOperation
    {
        $op->update([
            'status' => 'running',
            'started_at' => now(),
            'language_code' => $languageCode,
        ]);

        try {
            DB::transaction(function () use ($org, $actor, $client, $op) {
                $visitIds = Visit::query()
                    ->where('user_id', $org->id)
                    ->where('client_id', $client->id)
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->values()
                    ->all();

                if (!empty($visitIds)) {
                    $photos = VisitPhoto::query()->whereIn('visit_id', $visitIds)->get(['id', 'path']);
                    foreach ($photos as $photo) {
                        if (!empty($photo->path)) {
                            Storage::disk('public')->delete($photo->path);
                        }
                    }
                    VisitPhoto::query()->whereIn('visit_id', $visitIds)->delete();

                    $agreements = VisitAgreement::query()->whereIn('visit_id', $visitIds)->get(['id', 'signature_path']);
                    foreach ($agreements as $agreement) {
                        if (!empty($agreement->signature_path)) {
                            Storage::disk('public')->delete($agreement->signature_path);
                        }
                    }
                    VisitAgreement::query()
                        ->whereIn('visit_id', $visitIds)
                        ->update([
                            'agreement_text' => null,
                            'signature_path' => null,
                            'signed_at' => null,
                            'updated_at' => now(),
                        ]);

                    Visit::query()
                        ->whereIn('id', $visitIds)
                        ->update([
                            'client_id' => null,
                            'client_name' => 'Anonymized client',
                            'client_phone' => null,
                            'updated_at' => now(),
                        ]);
                    if (Schema::hasColumn('visits', 'client_phone_hash')) {
                        Visit::query()
                            ->whereIn('id', $visitIds)
                            ->update([
                                'client_phone_hash' => null,
                                'updated_at' => now(),
                            ]);
                    }
                }

                ClientNote::query()
                    ->where('user_id', $org->id)
                    ->where('client_id', $client->id)
                    ->delete();

                $client->name = 'Anonymized client';
                $client->phone = null;
                if (isset($client->instagram)) {
                    $client->instagram = null;
                }
                $client->blocked_at = null;
                $client->anonymized_at = now();
                $client->anonymized_by_user_id = $actor->id;
                $client->save();
                $client->delete();

                $op->meta = array_merge((array) $op->meta, [
                    'anonymized_visit_count' => count($visitIds),
                ]);
                $op->save();
            });

            $op->update([
                'status' => 'done',
                'finished_at' => now(),
                'error_message' => null,
            ]);

            Log::channel('dsar')->info('dsar_anonymize_client_done', [
                'dsar_operation_id' => (int) $op->id,
                'org_id' => (int) $org->id,
                'actor_user_id' => (int) $actor->id,
                'client_id' => (int) $client->id,
                'language_code' => $languageCode,
                'meta' => (array) $op->meta,
            ]);

            Audit::custom(
                $org->id,
                'dsar_anonymize_client',
                old: null,
                new: [
                    'dsar_operation_id' => $op->id,
                    'client_id' => $client->id,
                ],
                source: 'api_mobile'
            );
        } catch (\Throwable $e) {
            $op->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            Log::channel('dsar')->error('dsar_anonymize_client_failed', [
                'dsar_operation_id' => (int) $op->id,
                'org_id' => (int) $org->id,
                'actor_user_id' => (int) $actor->id,
                'client_id' => (int) $client->id,
                'language_code' => $languageCode,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $op->fresh();
    }

    public static function buildClientPayload(User $org, Client $client, string $languageCode): array
    {
        $visits = Visit::query()
            ->where('user_id', $org->id)
            ->where(function ($q) use ($client) {
                $q->where('client_id', $client->id)
                    ->orWhere(function ($s) use ($client) {
                        if (Schema::hasColumn('visits', 'client_phone_hash')) {
                            $phoneHash = PhoneIndex::hash($client->phone);
                            if ($phoneHash) {
                                $s->whereNull('client_id')->where('client_phone_hash', $phoneHash);
                            } else {
                                $s->whereRaw('1=0');
                            }
                        } else {
                            if ($client->phone) {
                                $s->whereNull('client_id')->where('client_phone', $client->phone);
                            } else {
                                $s->whereRaw('1=0');
                            }
                        }
                    });
            })
            ->orderBy('starts_at')
            ->get();

        $visitIds = $visits->pluck('id')->map(fn ($x) => (int) $x)->all();

        $agreements = empty($visitIds)
            ? collect()
            : VisitAgreement::query()
                ->whereIn('visit_id', $visitIds)
                ->orderBy('visit_id')
                ->get();

        $notes = ClientNote::query()
            ->where('user_id', $org->id)
            ->where('client_id', $client->id)
            ->orderBy('created_at')
            ->get();

        return [
            'meta' => [
                'generated_at' => now()->toISOString(),
                'operation' => 'export_client',
                'language' => $languageCode,
            ],
            'labels' => self::labels($languageCode),
            'organization' => [
                'id' => $org->id,
                'company_name' => $org->company_name,
                'email' => $org->email,
                'phone' => $org->phone,
                'address' => $org->address,
                'timezone' => $org->timezone,
            ],
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
                'instagram' => $client->instagram ?? null,
                'blocked_at' => $client->blocked_at?->toISOString(),
                'created_at' => $client->created_at?->toISOString(),
                'updated_at' => $client->updated_at?->toISOString(),
            ],
            'notes' => $notes->map(fn (ClientNote $n) => [
                'id' => $n->id,
                'text' => $n->text,
                'created_at' => $n->created_at?->toISOString(),
                'updated_at' => $n->updated_at?->toISOString(),
            ])->values()->all(),
            'visits' => $visits->map(fn (Visit $v) => [
                'id' => $v->id,
                'service_id' => $v->service_id,
                'staff_id' => $v->staff_id,
                'starts_at' => $v->starts_at?->toISOString(),
                'ends_at' => $v->ends_at?->toISOString(),
                'duration_min' => $v->duration_min,
                'price' => $v->price,
                'status' => $v->status,
                'comment' => $v->comment,
                'created_at' => $v->created_at?->toISOString(),
                'updated_at' => $v->updated_at?->toISOString(),
            ])->values()->all(),
            'agreements' => $agreements->map(fn (VisitAgreement $a) => [
                'id' => $a->id,
                'visit_id' => $a->visit_id,
                'service_id' => $a->service_id,
                'agreement_text' => $a->agreement_text,
                'signed_at' => $a->signed_at?->toISOString(),
                'created_at' => $a->created_at?->toISOString(),
                'updated_at' => $a->updated_at?->toISOString(),
            ])->values()->all(),
        ];
    }

    public static function buildAllClientsPayload(User $org, string $languageCode): array
    {
        $clients = Client::query()
            ->where('user_id', $org->id)
            ->orderBy('id')
            ->get(['id', 'name', 'phone', 'instagram', 'blocked_at', 'created_at', 'updated_at']);

        return [
            'meta' => [
                'generated_at' => now()->toISOString(),
                'operation' => 'export_all_clients',
                'language' => $languageCode,
            ],
            'labels' => self::labels($languageCode),
            'organization' => [
                'id' => $org->id,
                'company_name' => $org->company_name,
                'email' => $org->email,
            ],
            'clients' => $clients->map(fn (Client $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'instagram' => $c->instagram ?? null,
                'blocked_at' => $c->blocked_at?->toISOString(),
                'created_at' => $c->created_at?->toISOString(),
                'updated_at' => $c->updated_at?->toISOString(),
            ])->values()->all(),
        ];
    }

    public static function buildOrganizationPayload(User $org, string $languageCode): array
    {
        $staff = Staff::query()
            ->where('user_id', $org->id)
            ->orderBy('id')
            ->get();

        $services = Service::query()
            ->where('user_id', $org->id)
            ->orderBy('id')
            ->get();

        return [
            'meta' => [
                'generated_at' => now()->toISOString(),
                'operation' => 'export_org',
                'language' => $languageCode,
            ],
            'labels' => self::labels($languageCode),
            'organization' => [
                'id' => $org->id,
                'company_name' => $org->company_name,
                'email' => $org->email,
                'phone' => $org->phone,
                'address' => $org->address,
                'description' => $org->description,
                'timezone' => $org->timezone,
                'currency_code' => $org->currency_code,
                'schedule' => $org->schedule,
                'online_booking_enabled' => (bool) $org->online_booking_enabled,
                'online_booking_whitelist_only' => (bool) $org->online_booking_whitelist_only,
                'online_booking_auto_confirm' => (bool) $org->online_booking_auto_confirm,
                'online_booking_period_days' => (int) $org->online_booking_period_days,
            ],
            'staff' => $staff->map(fn (Staff $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'phone' => $s->phone,
                'is_active' => (bool) $s->is_active,
                'permissions' => $s->permissions,
                'schedule' => $s->schedule,
                'created_at' => $s->created_at?->toISOString(),
                'updated_at' => $s->updated_at?->toISOString(),
            ])->values()->all(),
            'services' => $services->map(fn (Service $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'category' => $s->category ?? null,
                'duration_min' => $s->duration_min ?? null,
                'price' => $s->price ?? null,
                'buffer_before_min' => $s->buffer_before_min ?? null,
                'buffer_after_min' => $s->buffer_after_min ?? null,
                'is_active' => isset($s->is_active) ? (bool) $s->is_active : null,
                'created_at' => $s->created_at?->toISOString(),
                'updated_at' => $s->updated_at?->toISOString(),
            ])->values()->all(),
            'counters' => [
                'clients' => Client::query()->where('user_id', $org->id)->count(),
                'visits' => Visit::query()->where('user_id', $org->id)->count(),
            ],
        ];
    }

    public static function pruneExports(int $retentionDays): int
    {
        if ($retentionDays <= 0) return 0;
        $cutoff = now()->subDays($retentionDays);

        $ops = DsarOperation::query()
            ->whereNotNull('payload_path')
            ->where('finished_at', '<', $cutoff)
            ->get(['id', 'payload_path']);

        $count = 0;
        foreach ($ops as $op) {
            $path = (string) $op->payload_path;
            if ($path !== '') {
                Storage::disk('local')->delete($path);
            }
            $op->payload_path = null;
            $op->save();
            $count++;
        }
        return $count;
    }

    private static function labels(string $languageCode): array
    {
        return match ($languageCode) {
            'uk' => [
                'organization' => 'Організація',
                'client' => 'Клієнт',
                'notes' => 'Нотатки',
                'visits' => 'Візити',
                'agreements' => 'Згоди',
                'staff' => 'Персонал',
                'services' => 'Послуги',
            ],
            'en' => [
                'organization' => 'Organization',
                'client' => 'Client',
                'notes' => 'Notes',
                'visits' => 'Visits',
                'agreements' => 'Agreements',
                'staff' => 'Staff',
                'services' => 'Services',
            ],
            default => [
                'organization' => 'Salon',
                'client' => 'Klient',
                'notes' => 'Notatki',
                'visits' => 'Wizyty',
                'agreements' => 'Zgody',
                'staff' => 'Personel',
                'services' => 'Usługi',
            ],
        };
    }

    private static function mailSubject(string $type, string $languageCode): string
    {
        if ($type !== 'export_client') return 'DSAR export';
        return match ($languageCode) {
            'uk' => 'Експорт даних клієнта (JSON)',
            'en' => 'Client data export (JSON)',
            default => 'Eksport danych klienta (JSON)',
        };
    }

    private static function mailBody(string $type, string $languageCode, User $org, ?Client $client = null): string
    {
        if ($type !== 'export_client') return 'DSAR export attached.';
        return match ($languageCode) {
            'uk' => "Вітаємо!\n\nВкладення містить JSON-експорт даних клієнта.\nСалон: " . ($org->company_name ?: '—') . "\nКлієнт: " . ($client?->name ?: '—') . "\n\nПовідомлення сформовано автоматично.",
            'en' => "Hello,\n\nThe attachment contains a JSON export of client data.\nSalon: " . ($org->company_name ?: '—') . "\nClient: " . ($client?->name ?: '—') . "\n\nThis message was generated automatically.",
            default => "Dzień dobry,\n\nW załączniku znajduje się eksport danych klienta w formacie JSON.\nSalon: " . ($org->company_name ?: '—') . "\nKlient: " . ($client?->name ?: '—') . "\n\nWiadomość wygenerowana automatycznie.",
        };
    }
}
