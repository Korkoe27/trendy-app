<?php

namespace App\Models;

use App\Support\Phone;
use App\Traits\HasPermissions;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasPermissions, Notifiable;
    /** @use HasFactory<\Database\Factories\UserFactory> */

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    // protected $with = ['role.modules'];
    protected $fillable = [
        'name',
        'username',
        'phone',
        'role_id',
        'email',
        'password',
        'must_change_password',
        'password_changed_at',
        'is_active',
    ];

    public function user_logs()
    {
        return $this->hasMany(ActivityLogs::class, 'user_id');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function role(): BelongsTo
    {

        return $this->belongsTo(Role::class, 'role_id');
    }

    /** Only the Super Admin role manages the block list (and other owner-only settings). */
    public function isSuperAdmin(): bool
    {
        return $this->role !== null && mb_strtolower(trim((string) $this->role->name)) === 'super admin';
    }

    /** Phones are always stored in one format (+233...), however they were typed. */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null || $value === '' ? null : (Phone::normalize($value) ?? $value),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
