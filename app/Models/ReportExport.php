<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
    use BelongsToCompany, HasUlids;

    public const DISK = 'local';

    public const FORMATS = ['csv', 'xlsx', 'pdf'];

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
}
