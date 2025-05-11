<?php

namespace App\Filament\Resources\TransactionResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\TransactionItem;


class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalTransactions = TransactionItem::count();
        $totalItemsSold = TransactionItem::sum('quantity');
        $totalRevenue = TransactionItem::sum('total_price');
        

        return [
            Stat::make('Total Transactions', $totalTransactions),
            Stat::make('Total Items Sold', $totalItemsSold),
            Stat::make('Total Revenue', 'Rp ' . number_format($totalRevenue, 0, ',', '.')),
        ];
    }
}
