<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Audit
{
    /**
     * Record audit entry for an Eloquent model change.
     *
     * @param array<string,mixed>|null $old
     * @param array<string,mixed>|null $new
     * @param list<string>|null $changedKeys
     */
    public static function model(
        Model $model,
        string $action,
        ?array $old = null,
        ?array $new = null,
        ?array $changedKeys = null,
        ?string $event = null,
        ?string $source = null,
    ): void {
        try {
            $ctx = self::context($source);
            $orgId = self::resolveOrgId($model, $ctx['org_id'] ?? null);

            AuditLog::query()->create([
                'org_id' => $orgId,

                'actor_user_id' => $ctx['actor_user_id'] ?? null,
                'actor_staff_id' => $ctx['actor_staff_id'] ?? null,
                'actor_type' => $ctx['actor_type'] ?? null,
                'actor_email' => $ctx['actor_email'] ?? null,
                'actor_name' => $ctx['actor_name'] ?? null,

                'action' => $action,
                'auditable_type' => get_class($model),
                'auditable_id' => (int) $model->getKey(),
                'event' => $event,

                'old_values' => $old,
                'new_values' => $new,
                'changed_keys' => $changedKeys,

                'source' => $ctx['source'] ?? $source,
                'ip' => $ctx['ip'] ?? null,
                'method' => $ctx['method'] ?? null,
                'url' => $ctx['url'] ?? null,
                'user_agent' => $ctx['user_agent'] ?? null,

                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit must never break main flow.
            try {
                \Log::channel('daily')->warning('audit_log_failed', [
                    'action' => $action,
                    'model' => get_class($model),
                    'id' => $model->getKey(),
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable $ignore) {
            }
        }
    }

    /**
     * Record a custom audit entry not tied to a specific model.
     *
     * @param array<string,mixed>|null $old
     * @param array<string,mixed>|null $new
     * @param list<string>|null $changedKeys
     */
    public static function custom(
        ?int $orgId,
        string $event,
        ?array $old = null,
        ?array $new = null,
        ?array $changedKeys = null,
        ?string $source = null,
    ): void {
        try {
            $ctx = self::context($source);
            $finalOrgId = $orgId ?? ($ctx['org_id'] ?? null);

            AuditLog::query()->create([
                'org_id' => $finalOrgId,
                'actor_user_id' => $ctx['actor_user_id'] ?? null,
                'actor_staff_id' => $ctx['actor_staff_id'] ?? null,
                'actor_type' => $ctx['actor_type'] ?? null,
                'actor_email' => $ctx['actor_email'] ?? null,
                'actor_name' => $ctx['actor_name'] ?? null,

                'action' => 'custom',
                'event' => $event,
                'old_values' => $old,
                'new_values' => $new,
                'changed_keys' => $changedKeys,

                'source' => $ctx['source'] ?? $source,
                'ip' => $ctx['ip'] ?? null,
                'method' => $ctx['method'] ?? null,
                'url' => $ctx['url'] ?? null,
                'user_agent' => $ctx['user_agent'] ?? null,

                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // swallow
        }
    }

    /**
     * Delete audit records older than N days.
     */
    public static function prune(int $retentionDays): int
    {
        if ($retentionDays <= 0) return 0;
        $cutoff = now()->subDays($retentionDays);
        return (int) AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    /**
     * Resolve organization id for audit.
     * Priority: model.org_id / model.user_id (tenant owner) / request user organization_id / request user id.
     */
    private static function resolveOrgId(Model $model, ?int $fallbackOrgId): ?int
    {
        foreach (['org_id', 'user_id', 'organization_id'] as $k) {
            if (isset($model->{$k}) && is_numeric($model->{$k})) {
                $v = (int) $model->{$k};
                if ($v > 0) return $v;
            }
        }
        return $fallbackOrgId;
    }

    /**
     * Build actor + request context if available.
     *
     * @return array<string,mixed>
     */
    private static function context(?string $source): array
    {
        $out = [];

        $runningInConsole = app()->runningInConsole();
        $out['source'] = $source ?? ($runningInConsole ? 'console' : null);

        /** @var Request|null $req */
        $req = null;
        try {
            $req = request();
        } catch (\Throwable $e) {
            $req = null;
        }

        if ($req) {
            $out['ip'] = (string) ($req->ip() ?? '');
            $out['method'] = (string) ($req->method() ?? '');
            $out['url'] = (string) ($req->fullUrl() ?? '');
            $out['user_agent'] = (string) ($req->userAgent() ?? '');

            $u = $req->user();
            if ($u) {
                $orgId = $u->organization_id ? (int) $u->organization_id : (int) $u->id;
                $out['org_id'] = $orgId;

                $out['actor_user_id'] = (int) $u->id;
                $out['actor_email'] = (string) ($u->email ?? '');
                $out['actor_name'] = (string) ($u->name ?? '');

                if ($u->staff_id) {
                    $out['actor_type'] = 'staff';
                    $out['actor_staff_id'] = (int) $u->staff_id;
                } else {
                    $out['actor_type'] = 'owner';
                }

                $out['source'] = $out['source'] ?? 'api_mobile';
            }
        }

        if (!isset($out['actor_type'])) {
            $out['actor_type'] = 'system';
        }

        return $out;
    }
}

