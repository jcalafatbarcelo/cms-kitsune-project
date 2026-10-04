<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Guarded([])]
class AdminAuditEvent extends Model
{
    public const SCHEMA_VERSION = 1;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Admin audit events are immutable.'));
        static::deleting(fn () => throw new LogicException('Admin audit events are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'before_state' => 'array',
            'after_state' => 'array',
        ];
    }
}
