<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    /** @use HasFactory<\Database\Factories\PermissionFactory> */
    use HasFactory;

    protected $fillable = [
        'role_id',
        'module_id',
        'can_view',
        'can_create',
        'can_modify',
        'can_delete'
    ];

    protected $table = 'permissions';

    protected $casts = [
        'can_create' => 'boolean',
        'can_view' => 'boolean',
        'can_modify' => 'boolean',
        'can_delete' => 'boolean',
    ];
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }


}
