<?php

namespace Modules\Pages\Models;

use Illuminate\Database\Eloquent\Model;

class PageLanguageHome extends Model
{
    protected $primaryKey = 'language_id';

    public $incrementing = false;

    protected $guarded = [];
}
