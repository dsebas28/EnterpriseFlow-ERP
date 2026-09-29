<?php

namespace App\Http\Resources;

use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Membership
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'joined_at' => $this->joined_at?->toIso8601String(),
            'is_owner' => $this->isOwner(),
            'is_current_user' => $this->user_id === $request->user()?->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'status' => $this->user->status->value,
                'last_login_at' => $this->user->last_login_at?->toIso8601String(),
            ],
            'roles' => $this->roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->values(),
        ];
    }
}
