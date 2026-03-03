<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform_source', 32)->index();
            $table->text('content');
            $table->json('embedding')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement('ALTER TABLE memories DROP COLUMN embedding');
            DB::statement('ALTER TABLE memories ADD COLUMN embedding vector(1536)');
            DB::statement('CREATE INDEX IF NOT EXISTS memories_embedding_idx ON memories USING ivfflat (embedding vector_cosine_ops)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
