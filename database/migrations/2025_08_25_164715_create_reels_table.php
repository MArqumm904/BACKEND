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
    public function up()
    {
        Schema::create('reels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');       // kis user ne upload ki
            $table->text('description')->nullable();     // caption / description
            $table->json('tags')->nullable();            // hashtags as JSON array
            $table->string('video_file');                 // reel video path/url
            $table->string('thumbnail')->nullable(); // optional thumbnail
            $table->bigInteger('views')->default(0);     // sirf count
            $table->bigInteger('likes')->default(0);     // sirf count
            $table->bigInteger('comments_count')->default(0); // sirf count
            $table->enum('visibility', ['public', 'private', 'friends'])->default('public');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('reels');
    }
};