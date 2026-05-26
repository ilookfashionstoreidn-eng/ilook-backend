<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BahanImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'image_path',
    ];

    protected $appends = [
        'image_url',
    ];

    public function bahans()
    {
        return $this->hasMany(Bahan::class);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path
            ? url('/api/bahan-images/' . rawurlencode(basename($this->image_path)))
            : null);
    }
}
