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
        Schema::create('reel_saves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reel_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['reel_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reel_saves');
    }
};
