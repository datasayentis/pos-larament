<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Filament;

use App\Models\Product;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Hitung total dari semua item jika belum diset
        if (!isset($data['total_amount']) || $data['total_amount'] == 0) {
            $totalAmount = 0;

            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as &$item) {
                    $product = Product::find($item['product_id']);

                    if ($product) {
                        if (!isset($item['price'])) {
                            $item['price'] = $product->product_price;
                        }

                        if (!isset($item['total_price'])) {
                            $item['total_price'] = $item['price'] * $item['quantity'];
                        }

                        $totalAmount += $item['total_price'];
                    }
                }
            }

            $data['total_amount'] = $totalAmount;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Kurangi stok produk setelah transaksi dibuat
        $transaction = $this->record;

        foreach ($transaction->items as $item) {
            $product = $item->product;

            if ($product) {
                $product->product_quantity -= $item->quantity;

                // Pastikan stok tidak kurang dari 0
                if ($product->product_quantity < 0) {
                    $product->product_quantity = 0;
                }

                $product->save();
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return route('filament.admin.resources.transactions.index');
    }
}
