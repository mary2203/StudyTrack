<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(true);
            $table->timestamps();
        });

        Schema::create('priority_rules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->string('color_hex', 7);
            $table->string('badge_label', 30);
            $table->decimal('min_score_threshold', 5, 2)->nullable();
            $table->decimal('max_score_threshold', 5, 2)->nullable();
            $table->decimal('weight_multiplier', 3, 2)->default(1);
            $table->string('description')->nullable();
        });

        DB::table('priority_rules')->insert([
            [
                'id' => 1,
                'code' => 'CRITICAL_RED',
                'name' => 'Prioridad Crítica',
                'color_hex' => '#FF4757',
                'badge_label' => 'Urgente',
                'min_score_threshold' => 0,
                'max_score_threshold' => 60.99,
                'weight_multiplier' => 2.5,
                'description' => 'Promedio bajo y entregas inmediatas',
            ],
            [
                'id' => 2,
                'code' => 'WARNING_YELLOW',
                'name' => 'Prioridad Media',
                'color_hex' => '#FFA502',
                'badge_label' => 'Atención',
                'min_score_threshold' => 61,
                'max_score_threshold' => 75,
                'weight_multiplier' => 1.5,
                'description' => 'Promedio regular o entregas a mediano plazo',
            ],
            [
                'id' => 3,
                'code' => 'SAFE_GREEN',
                'name' => 'Prioridad Baja',
                'color_hex' => '#2ED573',
                'badge_label' => 'Al día',
                'min_score_threshold' => 75.01,
                'max_score_threshold' => 100,
                'weight_multiplier' => 1,
                'description' => 'Buen rendimiento y sin entregas críticas',
            ],
            [
                'id' => 4,
                'code' => 'INFO_BLUE',
                'name' => 'Informativo',
                'color_hex' => '#1E90FF',
                'badge_label' => 'Evento',
                'min_score_threshold' => null,
                'max_score_threshold' => null,
                'weight_multiplier' => 0.5,
                'description' => 'Actividades informativas o generales',
            ],
        ]);

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained('academic_semesters')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->string('color_hex', 7)->default('#6C5CE7');
            $table->decimal('target_grade', 5, 2)->default(61);
            $table->decimal('current_average', 5, 2)->default(0);
            $table->unsignedInteger('priority_level_id')->default(3);
            $table->foreign('priority_level_id')->references('id')->on('priority_rules');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('activity_type', ['task', 'exam', 'project', 'reading', 'administrative', 'meeting'])->default('task');
            $table->boolean('is_graded')->default(true);
            $table->decimal('points_possible', 5, 2)->default(0);
            $table->decimal('points_obtained', 5, 2)->nullable();
            $table->dateTime('due_datetime');
            $table->integer('estimated_minutes')->default(45);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'overdue'])->default('pending');
            $table->unsignedInteger('priority_level_id')->nullable();
            $table->foreign('priority_level_id')->references('id')->on('priority_rules');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->dateTime('event_datetime');
            $table->integer('calculated_weight')->default(10);
            $table->unsignedInteger('priority_level_id')->default(3);
            $table->foreign('priority_level_id')->references('id')->on('priority_rules');
            $table->timestamps();
            $table->unique(['user_id', 'activity_id'], 'uq_user_activity');
        });

        Schema::create('system_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->text('message');
            $table->enum('urgency_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->string('action_type', 50)->default('study_suggestion');
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('entity_name', 50);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 25);
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('system_recommendations');
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('priority_rules');
        Schema::dropIfExists('academic_semesters');
    }
};
