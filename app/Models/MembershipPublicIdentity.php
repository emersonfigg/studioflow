<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPublicIdentity extends Model
{
    protected $guarded = [];

    protected $hidden = ['email', 'email_hash'];

    protected function casts(): array
    {
        return ['email' => 'encrypted'];
    }
}
