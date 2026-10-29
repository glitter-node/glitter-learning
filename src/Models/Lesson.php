<?php

namespace Modules\Glitter\Learning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $table = 'glitter_learning_lessons';

    protected $fillable = [
        'course_id', 'title', 'body', 'media_reference', 'sort_order', 'is_required', 'is_published',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'is_published' => 'boolean', 'sort_order' => 'integer'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
