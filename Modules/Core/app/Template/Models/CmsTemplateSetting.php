<?php

namespace Modules\Core\Template\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsTemplateSetting extends Model
{
    protected $guarded = [];

    public function defaultTemplate(): BelongsTo
    {
        return $this->belongsTo(CmsTemplate::class, 'default_template_id');
    }
}
