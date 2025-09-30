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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');   // related post
            $table->unsignedBigInteger('user_id');   // comment writer
            $table->unsignedBigInteger('parent_id')->nullable(); // reply ka parent comment
            $table->text('content');
            $table->integer('likes_count')->default(0); // number of likes
            $table->timestamps();

            // Indexes for performance
            $table->index('post_id');
            $table->index('user_id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
