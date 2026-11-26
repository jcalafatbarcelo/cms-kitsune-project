<?php

namespace Modules\Core\Localization\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['enabled', 'version', 'updated_by'])]
class UiCatalogOverrideState extends Model
{
    protected $table = 'ui_catalog_override_state';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
