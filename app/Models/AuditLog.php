<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = ['secret_id', 'action', 'timestamp'];

    public function secret()
    {
        return $this->belongsTo(Secret::class);
    }
}
