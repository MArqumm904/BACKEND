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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();     
            $table->unsignedBigInteger('page_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->text('content')->nullable();
            $table->enum('type', ['text', 'image', 'video', 'document']);
            $table->enum('visibility', ['public', 'private', 'friends'])->default('public');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('posts');
    }
};
