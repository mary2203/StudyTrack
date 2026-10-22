<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('classroom_course_imports') && Schema::hasColumn('classroom_course_imports', 'code')) {
            Schema::table('classroom_course_imports', function (Blueprint $table) {
                $table->string('code', 255)->nullable()->change();
            });
        }

        if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'code')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('code', 255)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['classroom_course_imports', 'courses'] as $tableName) {
            if (Schema::hasTable($tableName)
                && Schema::hasColumn($tableName, 'code')
                && DB::table($tableName)->whereRaw('LENGTH(code) > 50')->exists()) {
                throw new \RuntimeException("Cannot shrink {$tableName}.code because values exceed 50 characters.");
            }
        }

        if (Schema::hasTable('classroom_course_imports') && Schema::hasColumn('classroom_course_imports', 'code')) {
            Schema::table('classroom_course_imports', function (Blueprint $table) {
                $table->string('code', 50)->nullable()->change();
            });
        }

        if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'code')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('code', 50)->nullable()->change();
            });
        }
    }
};
