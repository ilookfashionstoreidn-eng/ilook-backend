<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductListImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'image_path',
    ];

    protected $appends = [
        'image_url',
    ];

    public function productLists()
    {
        return $this->hasMany(ProductList::class);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path
            ? '/api/product-list-images/' . rawurlencode(basename($this->image_path))
            : null);
    }
}
