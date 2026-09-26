<?php

namespace Modules\Core\Language\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['locale', 'url_prefix', 'name', 'native_name', 'text_direction', 'is_active', 'is_url_general', 'installed_at'])]
class Language extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_url_general' => 'boolean',
            'installed_at' => 'datetime',
        ];
    }

    public function baseSetting(): HasOne
    {
        return $this->hasOne(LanguageSetting::class, 'base_language_id');
    }
}
