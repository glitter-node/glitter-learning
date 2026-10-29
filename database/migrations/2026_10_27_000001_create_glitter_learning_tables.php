<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glitter_learning_courses', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->string('slug', 120)->unique();
            $table->text('introduction')->nullable();
            $table->longText('description')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
            $table->index('author_id');
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('glitter_learning_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('glitter_learning_courses')->cascadeOnDelete();
            $table->string('title', 191);
            $table->longText('body')->nullable();
            $table->string('media_reference', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['course_id', 'is_published', 'sort_order'], 'gl_learning_lessons_public_order_idx');
        });

        Schema::create('glitter_learning_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('glitter_learning_courses')->restrictOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('enrolled_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'user_id'], 'gl_learning_enrollment_course_user_unique');
            $table->index(['user_id', 'status'], 'gl_learning_enrollment_user_status_idx');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('glitter_learning_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('glitter_learning_enrollments')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('glitter_learning_lessons')->restrictOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();
            $table->unique(['enrollment_id', 'lesson_id'], 'gl_learning_progress_enrollment_lesson_unique');
            $table->index(['lesson_id', 'completed_at'], 'gl_learning_progress_lesson_completed_idx');
        });

        Schema::create('glitter_learning_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained('glitter_learning_enrollments')->restrictOnDelete();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('completed_at');
            $table->unsignedInteger('required_lessons_count');
            $table->string('criteria_version', 30)->default('required-lessons-v1');
            $table->json('criteria_snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['course_id', 'completed_at'], 'gl_learning_completion_course_date_idx');
            $table->index(['user_id', 'completed_at'], 'gl_learning_completion_user_date_idx');
            $table->foreign('course_id')->references('id')->on('glitter_learning_courses')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            foreach ([
                'glitter_learning_courses' => 'Glitter Learning 강좌',
                'glitter_learning_lessons' => 'Glitter Learning 차시',
                'glitter_learning_enrollments' => 'Glitter Learning 수강 등록',
                'glitter_learning_lesson_progress' => 'Glitter Learning 차시 진도',
                'glitter_learning_completions' => 'Glitter Learning 수료 스냅샷',
            ] as $table => $comment) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->comment($comment));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('glitter_learning_completions');
        Schema::dropIfExists('glitter_learning_lesson_progress');
        Schema::dropIfExists('glitter_learning_enrollments');
        Schema::dropIfExists('glitter_learning_lessons');
        Schema::dropIfExists('glitter_learning_courses');
    }
};
