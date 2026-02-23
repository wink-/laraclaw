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
        Schema::create('channel_bindings', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            $table->string('channel_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['gateway', 'channel_id']);
            $table->index(['gateway', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_bindings');
    }
};
