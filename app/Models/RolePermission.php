<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Link between a role and a permission name from App\Enums\Permission.
 * Composite primary key (role_id, permission); rows are only inserted or
 * deleted, never updated.
 *
 * @property int $role_id
 * @property string $permission
 */
class RolePermission extends Model
{
    protected $table = 'role_permissions';

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['role_id', 'permission'];

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
