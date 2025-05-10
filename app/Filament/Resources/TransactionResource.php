<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Product;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Carbon\Carbon;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // public static function form(Form $form): Form
    // {
    //     return $form
    //         ->schema([
    //             //
    //         ]);
    // }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\DatePicker::make('transaction_date')
                            ->required()
                            ->default(now()),
                        Forms\Components\TextInput::make('total_amount')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated()
                            ->default(0),
                        Forms\Components\Select::make('status')
                            ->options([
                                'completed' => 'Completed',
                                'pending' => 'Pending',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('completed')
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Products')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->options(Product::query()->where('is_active', true)->pluck('product_name', 'id'))
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $set('price', $product->product_price);
                                                $quantity = $get('quantity') ?: 1;
                                                $set('total_price', $product->product_price * $quantity);
                                            }
                                        } else {
                                            $set('price', null);
                                            $set('total_price', null);
                                        }
                                        static::updateTotalAmount($get, $set);
                                    })
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\TextInput::make('quantity')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->step(1)
                                    ->reactive()
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        $price = $get('price') ?: 0;
                                        $quantity = $state ?: 0;
                                        $set('total_price', $price * $quantity);
                                        static::updateTotalAmount($get, $set);
                                    }),

                                Forms\Components\TextInput::make('price')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->disabled()
                                    ->reactive()
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        $price = $state ?: 0;
                                        $quantity = $get('quantity') ?: 0;
                                        $set('total_price', $price * $quantity);
                                        static::updateTotalAmount($get, $set);
                                    }),

                                Forms\Components\TextInput::make('total_price')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->dehydrated()
                                    ->reactive(),
                            ])
                            ->columns(4)
                            ->itemLabel(function (array $state): ?string {
                                if (!empty($state['product_id'])) {
                                    $product = Product::find($state['product_id']);
                                    return $product ? $product->product_name : null;
                                }
                                return null;
                            })
                            ->defaultItems(1)
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->cloneable()
                            ->createItemButtonLabel('Add Product')
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                // Ensure all item data is properly set
                                if (!isset($data['price']) || !isset($data['total_price'])) {
                                    $product = Product::find($data['product_id']);
                                    if ($product) {
                                        $data['price'] = $product->product_price;
                                        $data['total_price'] = $product->product_price * ($data['quantity'] ?? 1);
                                    }
                                }
                                return $data;
                            }),
                    ]),
            ]);
    }

    /**
     * Update the total amount for the transaction based on all items
     */
    protected static function updateTotalAmount(Get $get, Set $set): void
    {
        $items = $get('../../items') ?? [];
        $totalAmount = 0;

        foreach ($items as $itemKey => $item) {
            $totalPrice = $get("../../items.{$itemKey}.total_price") ?? 0;
            $totalAmount += (float) $totalPrice;
        }

        $set('../../total_amount', $totalAmount);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('items.product.product_name')
                    ->label('Products')
                    ->listWithLineBreaks()
                    ->limitList(3) // Membatasi hanya 3 item yang ditampilkan
                    ->expandableLimitedList(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Total Items')
                    ->counts('items')
                    ->suffix(' item')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('IDR')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR'),
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'completed',
                        'warning' => 'pending',
                        'danger' => 'cancelled',
                    ])
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\Filter::make('transaction_date'),
                Tables\Filters\Filter::make('created_from')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created from'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('transaction_date', '>=', $date),
                            );
                    }),

                Tables\Filters\Filter::make('created_until')
                    ->form([
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Created until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('transaction_date', '<=', $date),
                            );
                    }),

            ])
            ->filters([
                Tables\Filters\Filter::make('transaction_date_range')
                ->form([
                    Forms\Components\DatePicker::make('from_date')
                        ->label('From Date'),
                    Forms\Components\DatePicker::make('to_date')
                        ->label('To Date'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['from_date'],
                            fn(Builder $query, $date): Builder => $query->whereDate('transaction_date', '>=', $date)
                        )
                        ->when(
                            $data['to_date'],
                            fn(Builder $query, $date): Builder => $query->whereDate('transaction_date', '<=', $date)
                        );
                })
                ->indicateUsing(function (array $data): array {
                    $indicators = [];
                    
                    if ($data['from_date'] ?? null) {
                        $indicators['from_date'] = 'From ' . Carbon::parse($data['from_date'])->toFormattedDateString();
                    }
                    
                    if ($data['to_date'] ?? null) {
                        $indicators['to_date'] = 'To ' . Carbon::parse($data['to_date'])->toFormattedDateString();
                    }
                    
                    return $indicators;
                }),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}
