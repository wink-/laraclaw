<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('heartbeat_runs', function (Blueprint $table) {
            $table->id();
            $table->string('heartbeat_id')->index();
            $table->text('instruction');
            $table->string('status', 20)->default('success');
            $table->text('response')->nullable();
            $table->timestamp('executed_at')->nullable();

            $table->index(['heartbeat_id', 'executed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heartbeat_runs');
    }
};
