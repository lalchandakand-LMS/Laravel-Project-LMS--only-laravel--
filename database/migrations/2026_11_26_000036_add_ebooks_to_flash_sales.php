<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('flash_sale_ebooks', function (Blueprint $table) {
            $table->foreignId('flash_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ebook_id')->constrained()->cascadeOnDelete();
            $table->primary(['flash_sale_id', 'ebook_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_ebooks');
    }
};
