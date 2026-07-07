<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    protected $connection = 'product_db';

    public function up(): void
    {
        Schema::connection($this->connection)->create('products', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->string('name', 150);
            $table->string('slug', 180)->unique();

            $table->text('description')->nullable();

            $table->decimal('price', 12, 2);
            $table->unsignedInteger('stock')->default(0);

            $table->string('condition', 30)->default('new');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('products');
    }
};
