<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('story_texts', function (Blueprint $table) {
            $table->integer('font_size')->default(16)->after('order_index'); // Font size in px
            $table->string('font_color', 20)->default('#000000')->after('font_size'); // Font color hex
        });
    }

    public function down(): void
    {
        Schema::table('story_texts', function (Blueprint $table) {
            $table->dropColumn(['font_size', 'font_color']);
        });
    }
};
