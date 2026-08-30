<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class ExportBatch extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id', 'filename', 'item_ids', 'item_count', 'csv', 'marked_listed_at',
    ];

    protected function casts(): array
    {
        return [
            'item_ids' => 'array',
            'item_count' => 'integer',
            'marked_listed_at' => 'datetime',
        ];
    }

    public function isMarkedListed(): bool
    {
        return $this->marked_listed_at !== null;
    }
}
