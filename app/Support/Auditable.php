<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Adds automatic audit logs for create/update/delete/restore.
 *
 * Models may define:
 * - protected array $auditExclude = ['field1', ...];
 * - protected array $auditOnly = ['field1', ...]; // optional allow-list
 */
trait Auditable
{
    /** @var array<string,mixed>|null */
    protected $auditPending = null;

    public static function bootAuditable(): void
    {
        static::creating(function (Model $model) {
            // nothing; we log after created
        });

        static::created(function (Model $model) {
            [$old, $new, $keys] = self::auditPayloadForCreate($model);
            Audit::model($model, 'created', $old, $new, $keys);
        });

        static::updating(function (Model $model) {
            [$old, $new, $keys] = self::auditPayloadForUpdate($model);
            $model->auditPending = [
                'old' => $old,
                'new' => $new,
                'keys' => $keys,
            ];
        });

        static::updated(function (Model $model) {
            $p = is_array($model->auditPending) ? $model->auditPending : null;
            $model->auditPending = null;
            if (!$p) return;
            Audit::model($model, 'updated', $p['old'] ?? null, $p['new'] ?? null, $p['keys'] ?? null);
        });

        static::deleting(function (Model $model) {
            [$old, $new, $keys] = self::auditPayloadForDelete($model);
            $model->auditPending = [
                'old' => $old,
                'new' => $new,
                'keys' => $keys,
            ];
        });

        static::deleted(function (Model $model) {
            $p = is_array($model->auditPending) ? $model->auditPending : null;
            $model->auditPending = null;
            if (!$p) return;
            Audit::model($model, 'deleted', $p['old'] ?? null, $p['new'] ?? null, $p['keys'] ?? null);
        });

        // "restored" exists only for SoftDeletes models.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::registerModelEvent('restored', function (Model $model) {
                [$old, $new, $keys] = self::auditPayloadForCreate($model);
                Audit::model($model, 'restored', $old, $new, $keys);
            });
        }
    }

    /**
     * @return array{0: array<string,mixed>|null, 1: array<string,mixed>|null, 2: list<string>|null}
     */
    private static function auditPayloadForCreate(Model $model): array
    {
        $attrs = $model->getAttributes();
        $new = self::auditFilter($model, $attrs);
        $keys = array_keys($new);
        return [null, $new, $keys];
    }

    /**
     * @return array{0: array<string,mixed>|null, 1: array<string,mixed>|null, 2: list<string>|null}
     */
    private static function auditPayloadForUpdate(Model $model): array
    {
        $dirty = $model->getDirty();
        if (empty($dirty)) return [null, null, null];

        $old = [];
        $new = [];
        foreach ($dirty as $k => $v) {
            $old[$k] = $model->getOriginal($k);
            $new[$k] = $v;
        }

        $old = self::auditFilter($model, $old);
        $new = self::auditFilter($model, $new);

        $keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));
        sort($keys);
        return [$old, $new, $keys];
    }

    /**
     * @return array{0: array<string,mixed>|null, 1: array<string,mixed>|null, 2: list<string>|null}
     */
    private static function auditPayloadForDelete(Model $model): array
    {
        $attrs = $model->getAttributes();
        $old = self::auditFilter($model, $attrs);
        $keys = array_keys($old);
        return [$old, null, $keys];
    }

    /**
     * Apply allow/exclude lists and normalize values.
     *
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    private static function auditFilter(Model $model, array $values): array
    {
        // allow-list
        if (property_exists($model, 'auditOnly') && is_array($model->auditOnly) && !empty($model->auditOnly)) {
            $values = array_intersect_key($values, array_flip($model->auditOnly));
        }

        // exclude-list
        $exclude = [
            'password',
            'remember_token',
        ];
        if (property_exists($model, 'auditExclude') && is_array($model->auditExclude)) {
            $exclude = array_values(array_unique(array_merge($exclude, $model->auditExclude)));
        }
        foreach ($exclude as $k) {
            unset($values[$k]);
        }

        // normalize objects/arrays into JSON-friendly shapes
        foreach ($values as $k => $v) {
            if ($v instanceof \DateTimeInterface) {
                $values[$k] = $v->format('c');
            } elseif (is_object($v)) {
                $values[$k] = method_exists($v, '__toString') ? (string) $v : json_decode(json_encode($v), true);
            }
        }

        return $values;
    }
}

