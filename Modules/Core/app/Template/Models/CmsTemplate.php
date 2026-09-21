<?php

namespace Modules\Core\Template\Models;

use Illuminate\Database\Eloquent\Model;

class CmsTemplate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'registered_at' => 'datetime'];
    }
}
