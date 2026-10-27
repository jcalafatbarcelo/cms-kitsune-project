<?php

namespace Modules\Pages\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageTranslationContent extends Model
{
    protected $guarded = [];

    public function translation(): BelongsTo
    {
        return $this->belongsTo(PageTranslation::class, 'page_translation_id');
    }
}
