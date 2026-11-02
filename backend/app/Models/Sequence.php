<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-key, per-year counter used to generate gapless document references.
 */
class Sequence extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }
}
