<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Enums\MembershipStatus;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserStatus $status
 * @property bool $is_super_admin
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'is_super_admin' => false,
    ];

    /**
     * Privileged columns (status, is_super_admin, last_login_at) are
     * intentionally not mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Company, $this, Membership, 'membership'>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->using(Membership::class)
            ->as('membership')
            ->withPivot(['id', 'status', 'invited_by', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Companies the user can currently operate in: active membership in an
     * active company.
     *
     * @return BelongsToMany<Company, $this, Membership, 'membership'>
     */
    public function activeCompanies(): BelongsToMany
    {
        return $this->companies()
            ->wherePivot('status', MembershipStatus::Active->value)
            ->where('companies.status', CompanyStatus::Active->value)
            ->orderBy('company_user.joined_at');
    }

    public function isActiveMemberOf(Company $company): bool
    {
        return $this->activeCompanies()->whereKey($company->getKey())->exists();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }
}
