<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'nama_customer',
        'alamat',
        'nomor_telepon',
        'latitude',
        'longitude',
        'visited_at',
    ];

    /**
     * Cast attributes to native types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude'   => 'float',
            'longitude'  => 'float',
            'visited_at' => 'datetime',
        ];
    }

    /**
     * Relasi ke User (sales yang menginput).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke CustomerPhoto (1 customer memiliki 1-3 foto).
     */
    public function photos()
    {
        return $this->hasMany(CustomerPhoto::class);
    }
}
