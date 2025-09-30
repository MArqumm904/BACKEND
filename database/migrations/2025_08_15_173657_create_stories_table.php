<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // User ka ID (no FK)
            $table->string('type', 50); // photo, postphotostory, etc.
            $table->unsignedBigInteger('post_id')->nullable(); // post types ke liye
            $table->text('media_path')->nullable(); // media ka URL/path
            $table->string('media_type', 20)->nullable(); // photo or video
            $table->float('x_position')->default(0); // X axis position
            $table->float('y_position')->default(0); // Y axis position
            $table->integer('duration')->default(5); // seconds
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};
