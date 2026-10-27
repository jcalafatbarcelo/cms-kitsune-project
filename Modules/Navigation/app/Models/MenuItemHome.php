<?php

namespace Modules\Navigation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemHome extends Model
{
    protected $primaryKey = 'language_id';

    public $incrementing = false;

    protected $guarded = [];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }
}
