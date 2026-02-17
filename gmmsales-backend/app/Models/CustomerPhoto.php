<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CustomerPhoto extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'photo_path',
    ];

    /**
     * Append atribut custom ke JSON output.
     *
     * @var array<int, string>
     */
    protected $appends = ['photo_url'];

    /**
     * Relasi ke Customer.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Accessor: generate full URL dari path foto.
     */
    public function getPhotoUrlAttribute(): string
    {
        return Storage::url($this->photo_path);
    }
}
