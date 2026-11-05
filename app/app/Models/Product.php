<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'description', 'price', 'category', 'image_path'])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }
}
