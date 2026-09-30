<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $company_id
 * @property int $user_id
 * @property string $report
 * @property string $format csv|xlsx|pdf
 * @property array<string, mixed> $filters
 * @property string $status pending|processing|completed|failed
 * @property string|null $file_path
 * @property int|null $rows_count
 * @property string|null $error
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 */
class ReportExport extends Model
{
    use BelongsToCompany, HasUlids, Prunable;

    public const DISK = 'local';

    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    /** Exports are downloads, not archives: files are removed after this. */
    public const RETENTION_DAYS = 7;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'rows_count' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Platform-wide maintenance (model:prune runs without a tenant), hence
     * the explicit, deliberate scope bypass.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    protected function pruning(): void
    {
        if ($this->file_path !== null) {
            Storage::disk(self::DISK)->delete($this->file_path);
        }
    }
}
