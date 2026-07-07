<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $connection = 'transaction_db';

    protected $fillable = [
        'user_id',
        'address_id',
        'invoice_number',
        'transaction_date',
        'total_price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime',
            'total_price' => 'decimal:2',
        ];
    }

    public function details()
    {
        return $this->hasMany(TransactionDetail::class);
    }
}
