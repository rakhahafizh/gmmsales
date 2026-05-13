<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_produk',
        'harga',
    ];

    protected $casts = [
        'harga' => 'decimal:2',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}