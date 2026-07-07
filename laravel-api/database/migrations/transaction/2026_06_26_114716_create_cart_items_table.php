<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    protected $connection = 'transaction_db';

    public function up(): void
    {
        Schema::connection($this->connection)->create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('product_id');

            $table->unsignedInteger('quantity')->default(1);

            $table->timestamps();

            $table->unique(['cart_id', 'product_id']);

            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('cart_items');
    }
};
