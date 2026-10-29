<?php

namespace Modules\Glitter\Learning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Completion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'glitter_learning_completions';

    protected $fillable = [
        'enrollment_id', 'course_id', 'user_id', 'completed_at', 'required_lessons_count',
        'criteria_version', 'criteria_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'criteria_snapshot' => 'array',
            'required_lessons_count' => 'integer',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
