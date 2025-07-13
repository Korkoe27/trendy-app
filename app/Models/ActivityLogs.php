<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLogs extends Model
{
    /** @use HasFactory<\Database\Factories\ActivityLogsFactory> */
    use HasFactory;
    

    protected $fillable = [
        'user_id',
        'action_type',
        'description',
        'entity_type',
        'entity_id',
        'metadata',
    ];

    protected $table = 'activity_logs';

    public function user(){
        return $this->belongsTo(User::class);
    }
}
