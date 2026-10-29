<?php

namespace Modules\Glitter\Learning\Models;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $table = 'glitter_learning_courses';

    protected $fillable = [
        'title', 'slug', 'introduction', 'description', 'author_id', 'status', 'published_at',
        'category', 'difficulty', 'thumbnail_attachment_id', 'learning_objectives',
        'prerequisites', 'target_audience', 'estimated_minutes', 'description_mode',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'learning_objectives' => 'array',
            'estimated_minutes' => 'integer',
            'thumbnail_attachment_id' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function thumbnail(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'thumbnail_attachment_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order')->orderBy('id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(Completion::class);
    }
}
