<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Product;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalProducts = Product::count();
        $totalStock = Product::sum('product_quantity');
        $totalActive = Product::sum('is_active');
        

        return [
            Stat::make('Total Product', $totalProducts),
            Stat::make('Total Item Stock', $totalStock),
            Stat::make('Total Item Active', $totalActive),
        ];
    }
}
