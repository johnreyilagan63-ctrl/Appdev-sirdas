<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'token_hash', 'purpose', 'expires_at', 'used', 'attempts', 'verified_at'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'used' => 'boolean',
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
