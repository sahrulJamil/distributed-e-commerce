<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    protected $connection = 'transaction_db';

    public function up(): void
    {
        Schema::connection($this->connection)->create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');

            $table->unsignedBigInteger('address_id');

            $table->string('invoice_number', 50)->unique();

            $table->timestamp('transaction_date')->useCurrent();

            $table->decimal('total_price', 12, 2)->default(0);

            $table->string('status', 30)->default('pending');

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('address_id');
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('transactions');
    }
};
