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
        Schema::create('membership_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('membership_id');
            $table->string('confirmation_letter')->nullable(); 
            $table->string('proof_document')->nullable(); 
            $table->unsignedBigInteger('uploaded_by_company');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_documents');
    }
};
