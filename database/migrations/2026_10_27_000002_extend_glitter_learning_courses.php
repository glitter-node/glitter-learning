<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('glitter_learning_courses', function (Blueprint $table): void {
            $table->string('category', 40)->nullable()->comment('강좌 분류 식별자');
            $table->string('difficulty', 24)->nullable()->comment('강좌 난이도 식별자');
            $table->unsignedBigInteger('thumbnail_attachment_id')->nullable()->comment('대표 이미지 첨부파일 ID');
            $table->json('learning_objectives')->nullable()->comment('순서가 있는 학습 목표 목록');
            $table->text('prerequisites')->nullable()->comment('선수 지식');
            $table->text('target_audience')->nullable()->comment('권장 학습 대상');
            $table->unsignedInteger('estimated_minutes')->nullable()->comment('예상 학습시간(분)');
            $table->string('description_mode', 8)->default('text')->comment('상세 설명 형식: text 또는 html');
            $table->foreign('thumbnail_attachment_id', 'gl_learning_courses_thumbnail_fk')
                ->references('id')->on('attachments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('glitter_learning_courses', function (Blueprint $table): void {
            $table->dropForeign('gl_learning_courses_thumbnail_fk');
            $table->dropColumn([
                'category', 'difficulty', 'thumbnail_attachment_id', 'learning_objectives',
                'prerequisites', 'target_audience', 'estimated_minutes', 'description_mode',
            ]);
        });
    }
};
