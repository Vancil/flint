<?php
declare(strict_types=1);

namespace App\Models;

use Flint\Model;

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password'];
    protected array $casts = ['is_active' => 'bool'];
}
