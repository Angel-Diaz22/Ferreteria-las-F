<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'policy_version',
        'consented_at',
        'ip_address',
        'user_agent',
        'channel',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
        ];
    }
}
