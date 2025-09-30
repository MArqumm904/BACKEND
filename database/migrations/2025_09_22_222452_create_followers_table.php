<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('followers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('follower_id');   // jo follow kar raha hai
            $table->unsignedBigInteger('following_id');  // jisko follow kar rahe hain
            $table->timestamps();
            $table->unique(['follower_id', 'following_id']); // duplicate follow prevent
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('followers');
    }
};
