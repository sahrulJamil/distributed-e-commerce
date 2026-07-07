<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    protected $connection = 'transaction_db';

    public function up(): void
    {
        Schema::connection($this->connection)->create('transaction_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('product_id');

            $table->string('product_name', 150);
            $table->decimal('product_price', 12, 2);

            $table->unsignedInteger('quantity');

            $table->decimal('subtotal', 12, 2);

            $table->timestamps();

            $table->index('product_id');
            $table->index(['transaction_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('transaction_details');
    }
};
