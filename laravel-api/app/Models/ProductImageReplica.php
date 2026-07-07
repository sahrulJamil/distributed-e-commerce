<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImageReplica extends Model
{
    protected $connection = 'product_db_replica';

    protected $table = 'product_images';
}