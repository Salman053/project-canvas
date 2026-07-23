<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Tag extends Model
{
    protected $fillable = ['name', 'taggable_id', 'taggable_type'];

    public function taggable(): MorphTo
    {
        return $this->morphTo();
    }
}
