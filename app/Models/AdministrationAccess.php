<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdministrationAccess extends Model
{
    protected $table = 'administration_access';

    protected $primaryKey = 'singleton';

    public $incrementing = false;

    public $timestamps = false;

    public static function permits(?User $user): bool
    {
        return $user !== null && static::query()->whereKey(1)->where('super_admin_user_id', $user->id)->exists();
    }
}
