<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('story_id'); // Story ka ID (no FK)
            $table->unsignedBigInteger('user_id');  // User ka ID (no FK)
            $table->timestamp('viewed_at')->useCurrent(); // Viewed time
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_views');
    }
};
