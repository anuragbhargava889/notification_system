<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Prevent the same task title being assigned to the same user
            // at the database level — guards against race conditions that
            // bypass the application-level unique validation in StoreTaskRequest.
            $table->unique(['title', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['title', 'assigned_to']);
        });
    }
};
