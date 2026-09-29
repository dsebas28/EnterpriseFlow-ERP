<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'is_owner' => $this->isOwner(),
            'members_count' => $this->whenCounted('memberships'),
            'permissions' => $this->permissions()->map(fn (Permission $p) => $p->value)->values(),
        ];
    }
}
