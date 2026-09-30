<?php

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Records created / updated / deleted / restored events of a model in the
 * audit trail, with only the attributes that changed.
 *
 * Models may declare `protected array $auditExclude = [...]` for extra
 * attributes that must never be recorded.
 *
 * @mixin Model
 */
trait Auditable
{
    /**
     * Never recorded, for any model.
     *
     * @var list<string>
     */
    private static array $auditAlwaysExclude = [
        'password', 'remember_token', 'token_hash', 'created_at', 'updated_at', 'deleted_at',
    ];

    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            /** @var Model&self $model */
            $model->audit('created', null, $model->auditableValues($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            /** @var Model&self $model */
            $new = $model->auditableValues($model->getChanges());

            if ($new === []) {
                return; // only excluded columns (e.g. timestamps) changed
            }

            $old = array_intersect_key($model->auditableValues($model->getRawOriginal()), $new);
            $model->audit('updated', $old, $new);
        });

        static::deleted(function (Model $model): void {
            /** @var Model&self $model */
            $model->audit('deleted', $model->auditableValues($model->getRawOriginal()), null);
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::registerModelEvent('restored', function (Model $model): void {
                /** @var Model&self $model */
                $model->audit('restored', null, null);
            });
        }
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    protected function audit(string $event, ?array $old, ?array $new): void
    {
        app(AuditLogger::class)->record($event, $this, $old, $new);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableValues(array $attributes): array
    {
        $extra = property_exists($this, 'auditExclude') ? (array) $this->auditExclude : [];
        $exclude = [...self::$auditAlwaysExclude, ...$extra];

        return array_diff_key($attributes, array_flip($exclude));
    }
}
