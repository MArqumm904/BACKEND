<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->enum('type', ['text', 'image', 'video', 'document', 'poll'])->after('content');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->enum('type', ['text', 'image', 'video', 'document', 'poll'])->after('post_id');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->enum('type', ['text', 'image', 'video', 'document'])->after('content');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->enum('type', ['text', 'image', 'video', 'document'])->after('post_id');
        });
    }
};
