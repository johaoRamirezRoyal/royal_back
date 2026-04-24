<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetTokens extends Model
{
    protected $table = 'password_reset_tokens';

    protected $fillable = [
        'email',
        'token',
        'used',
    ];

    const CREATE_AT = 'createAt';

    const UPDATE_AT = 'updateAt';

    public function usedReset(): bool
    {
        $used = $this->used;
        if (! $used) {
            return false;
        }
        $this->used = true;
        $this->save();

        return true;
    }
}
