<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_benchmarks', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('model_id');
            $table->string('model_name');
            $table->string('last_status', 20)->nullable();
            $table->unsignedInteger('last_response_time_ms')->nullable();
            $table->unsignedInteger('fastest_response_time_ms')->nullable();
            $table->unsignedInteger('slowest_response_time_ms')->nullable();
            $table->decimal('average_response_time_ms', 10, 2)->nullable();
            $table->text('last_response_excerpt')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->unsignedInteger('total_runs')->default(0);
            $table->unsignedInteger('successful_runs')->default(0);
            $table->unsignedInteger('failed_runs')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'model_id']);
            $table->index(['provider', 'last_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_benchmarks');
    }
};
