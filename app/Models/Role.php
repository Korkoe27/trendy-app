<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    /** @use HasFactory<\Database\Factories\RoleFactory> */
    use HasFactory;
    protected $fillable = [
        'name',
        'description'
    ];
    protected $table = 'roles';

    public function modules(){
        return $this->belongsToMany(Module::class,'permissions');
    }
}
