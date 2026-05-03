<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use WireNinja\Accelerator\Model\AcceleratedUser;

#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends AcceleratedUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
}
