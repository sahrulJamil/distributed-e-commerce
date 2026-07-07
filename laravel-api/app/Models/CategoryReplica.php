<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryReplica extends Model
{
    protected $connection = 'product_db_replica';

    protected $table = 'categories';
}