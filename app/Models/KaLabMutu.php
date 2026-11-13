<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KaLabMutu extends Model
{
    protected $table = 'ka_lab_mutus';

    protected $fillable = [
        'user_id',
        'nama',
        'email',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
