<?php

namespace Modules\Core\Language\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LanguageSetting extends Model
{
    protected $guarded = [];

    public function baseLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'base_language_id');
    }

    public function frontendDefaultLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'frontend_default_language_id');
    }

    public function backofficeDefaultLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'backoffice_default_language_id');
    }
}
