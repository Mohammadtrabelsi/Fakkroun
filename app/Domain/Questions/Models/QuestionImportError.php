<?php

namespace App\Domain\Questions\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionImportError extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['import_batch_id', 'row_number', 'messages'];

    protected function casts(): array
    {
        return ['messages' => 'array'];
    }
}
