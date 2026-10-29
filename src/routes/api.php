<?php

use Illuminate\Support\Facades\Route;
use Modules\Glitter\Learning\Http\Controllers\Admin\CourseController;
use Modules\Glitter\Learning\Http\Controllers\Admin\LessonController;
use Modules\Glitter\Learning\Http\Controllers\Admin\RecordController;
use Modules\Glitter\Learning\Http\Controllers\User\LearningController;

Route::prefix('admin')->middleware(['auth:sanctum', 'throttle:600,1'])->name('admin.')->group(function () {
    Route::get('/courses', [CourseController::class, 'index'])->middleware('permission:admin,glitter-learning.courses.read')->name('courses.index');
    Route::post('/courses/preview', [CourseController::class, 'preview'])->middleware('permission:admin,glitter-learning.courses.read')->name('courses.preview');
    Route::post('/courses', [CourseController::class, 'store'])->middleware('permission:admin,glitter-learning.courses.create')->name('courses.store');
    Route::get('/courses/{course}', [CourseController::class, 'show'])->middleware('permission:admin,glitter-learning.courses.read')->name('courses.show');
    Route::put('/courses/{course}', [CourseController::class, 'update'])->middleware('permission:admin,glitter-learning.courses.update')->name('courses.update');
    Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->middleware('permission:admin,glitter-learning.courses.delete')->name('courses.destroy');
    Route::patch('/courses/{course}/archive', [CourseController::class, 'archive'])->middleware('permission:admin,glitter-learning.courses.update')->name('courses.archive');
    Route::post('/courses/{course}/lessons', [LessonController::class, 'store'])->middleware('permission:admin,glitter-learning.courses.update')->name('lessons.store');
    Route::put('/courses/{course}/lessons/{lesson}', [LessonController::class, 'update'])->middleware('permission:admin,glitter-learning.courses.update')->name('lessons.update');
    Route::delete('/courses/{course}/lessons/{lesson}', [LessonController::class, 'destroy'])->middleware('permission:admin,glitter-learning.courses.delete')->name('lessons.destroy');
    Route::patch('/courses/{course}/lessons/reorder', [LessonController::class, 'reorder'])->middleware('permission:admin,glitter-learning.courses.update')->name('lessons.reorder');
    Route::get('/courses/{course}/enrollments', [RecordController::class, 'enrollments'])->middleware('permission:admin,glitter-learning.records.read')->name('enrollments.index');
    Route::get('/completions', [RecordController::class, 'completions'])->middleware('permission:admin,glitter-learning.records.read')->name('completions.index');
});

Route::prefix('learning')->middleware(['optional.sanctum', 'throttle:600,1'])->name('learning.')->group(function () {
    Route::get('/courses', [LearningController::class, 'courses'])->name('courses.index');
    Route::get('/courses/{course:slug}', [LearningController::class, 'course'])->name('courses.show');
});

Route::prefix('learning')->middleware(['auth:sanctum', 'throttle:120,1'])->name('learning.')->group(function () {
    Route::post('/courses/{course:slug}/enroll', [LearningController::class, 'enroll'])->name('courses.enroll');
    Route::get('/courses/{course:slug}/lessons/{lesson}', [LearningController::class, 'lesson'])->name('lessons.show');
    Route::post('/courses/{course:slug}/lessons/{lesson}/complete', [LearningController::class, 'completeLesson'])->name('lessons.complete');
    Route::get('/me/enrollments', [LearningController::class, 'dashboard'])->name('me.enrollments');
    Route::get('/me/completions', [LearningController::class, 'completions'])->name('me.completions');
});
