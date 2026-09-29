<?php

namespace Tests\Fixtures;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $company_id
 * @property string $name
 */
class FixtureProject extends Model
{
    use BelongsToCompany;

    protected $table = 'fixture_projects';

    protected $guarded = [];
}
