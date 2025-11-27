<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_lampiran_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_lampiran_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->longText('path');
            $table->timestamps();
            $table->index(['nota_lampiran_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_lampiran_signatures');
    }
};
