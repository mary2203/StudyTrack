<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('classroom_connections')) {
            Schema::create('classroom_connections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('google_email');
                $table->text('refresh_token');
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('classroom_course_imports')) {
            Schema::create('classroom_course_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('google_course_id', 180);
                $table->string('name', 150);
                $table->string('code', 50)->nullable();
                $table->string('section')->nullable();
                $table->string('course_state', 30);
                $table->string('status', 20)->default('pending');
                $table->unsignedBigInteger('course_id')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'google_course_id']);
            });
        }

        if (Schema::hasTable('courses') && !Schema::hasColumn('courses', 'classroom_course_id')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('classroom_course_id', 180)->nullable()->index();
            });
        }

        if (Schema::hasTable('activities') && !Schema::hasColumn('activities', 'classroom_coursework_id')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->string('classroom_coursework_id', 180)->nullable();
                $table->string('classroom_submission_state', 30)->nullable();
                $table->unique(['user_id', 'classroom_coursework_id']);
            });
        }

        if (Schema::hasTable('activities') && Schema::hasColumn('activities', 'due_datetime')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->dateTime('due_datetime')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table) {
                if (Schema::hasColumn('activities', 'classroom_coursework_id')) {
                    $table->dropUnique(['user_id', 'classroom_coursework_id']);
                    $table->dropColumn(['classroom_coursework_id', 'classroom_submission_state']);
                }
            });
        }

        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table) {
                if (Schema::hasColumn('courses', 'classroom_course_id')) {
                    $table->dropIndex(['classroom_course_id']);
                    $table->dropColumn('classroom_course_id');
                }
            });
        }

        Schema::dropIfExists('classroom_course_imports');
        Schema::dropIfExists('classroom_connections');
    }
};
