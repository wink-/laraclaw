<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create FTS5 virtual table for full-text search on memory fragments
        // Only create if using SQLite
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("
                CREATE VIRTUAL TABLE IF NOT EXISTS memory_fragments_fts
                USING fts5(
                    content,
                    key,
                    content='memory_fragments',
                    content_rowid='id',
                    tokenize='porter unicode61'
                )
            ");

            // Create triggers to keep FTS index in sync
            DB::statement('
                CREATE TRIGGER IF NOT EXISTS memory_fragments_ai
                AFTER INSERT ON memory_fragments BEGIN
                    INSERT INTO memory_fragments_fts(rowid, content, key)
                    VALUES (new.id, new.content, new.key);
                END
            ');

            DB::statement("
                CREATE TRIGGER IF NOT EXISTS memory_fragments_ad
                AFTER DELETE ON memory_fragments BEGIN
                    INSERT INTO memory_fragments_fts(memory_fragments_fts, rowid, content, key)
                    VALUES('delete', old.id, old.content, old.key);
                END
            ");

            DB::statement("
                CREATE TRIGGER IF NOT EXISTS memory_fragments_au
                AFTER UPDATE ON memory_fragments BEGIN
                    INSERT INTO memory_fragments_fts(memory_fragments_fts, rowid, content, key)
                    VALUES('delete', old.id, old.content, old.key);
                    INSERT INTO memory_fragments_fts(rowid, content, key)
                    VALUES (new.id, new.content, new.key);
                END
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS memory_fragments_au');
            DB::statement('DROP TRIGGER IF EXISTS memory_fragments_ad');
            DB::statement('DROP TRIGGER IF EXISTS memory_fragments_ai');
            DB::statement('DROP TABLE IF EXISTS memory_fragments_fts');
        }
    }
};
