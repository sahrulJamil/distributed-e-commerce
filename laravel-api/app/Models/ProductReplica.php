<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReplica extends Model
{
    protected $connection = 'product_db_replica';

    protected $table = 'products';

    public function category()
    {
        return $this->belongsTo(CategoryReplica::class, 'category_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImageReplica::class, 'product_id');
    }
}