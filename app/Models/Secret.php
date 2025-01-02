<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Secret extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'hash',
        'secret_text',
        'remaining_views',
        'expires_at',
        'tags'
    ];

    protected $casts = [
        'tags' => 'array',
    ];
}
