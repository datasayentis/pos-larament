<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_date',
        'total_amount',
        'status',
        // tambahkan field lain yang diperlukan
    ];

    // function kurangi stok setelah transaksi dibuat
    // protected static function booted()
    // {
    //     static::created(function ($transaction) {
    //         // Ambil produk terkait dengan transaksi
    //         $product = $transaction->product;

    //         if ($product) {
    //             // Kurangi stok berdasarkan kuantitas transaksi
    //             $product->product_quantity -= $transaction->transaction_quantity;

    //             // Pastikan stok tidak kurang dari 0
    //             if ($product->product_quantity < 0) {
    //                 $product->product_quantity = 0;
    //             }

    //             // Simpan perubahan ke tabel produk
    //             $product->save();
    //         }
    //     });
    // }

    // Add this relationship method
    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }

    // Your existing product relationship can stay
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
