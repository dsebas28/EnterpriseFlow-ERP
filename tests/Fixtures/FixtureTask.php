<?php

namespace Tests\Fixtures;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $company_id
 * @property int $project_id
 * @property string $title
 */
class FixtureTask extends Model
{
    use BelongsToCompany;

    protected $table = 'fixture_tasks';

    protected $guarded = [];

    /**
     * @return BelongsTo<FixtureProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(FixtureProject::class, 'project_id');
    }
}
