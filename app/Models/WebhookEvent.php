<?php

namespace App\Models;

use App\Enums\WebhookEventStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $provider
 * @property string $external_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property WebhookEventStatus $status
 * @property int $attempts
 * @property string|null $last_error
 * @property string|null $company_id
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 */
class WebhookEvent extends Model
{
    use HasUlids, Prunable;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => WebhookEventStatus::class,
            'attempts' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Finished events past the retention window. Failed ones are kept until
     * someone looks at them.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereIn('status', [WebhookEventStatus::Processed, WebhookEventStatus::Ignored])
            ->where('received_at', '<', now()->subDays((int) config('webhooks.retention_days')));
    }
}
