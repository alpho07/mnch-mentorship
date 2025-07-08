<?php

namespace App\Filament\Resources\StockTransferResource\Pages;

use App\Filament\Resources\StockTransferResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\StockBalance;
use App\Models\ItemTransaction;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

class CreateStockTransfer extends CreateRecord
{
    protected static string $resource = StockTransferResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $itemId = $data['inventory_item_id'];
        $fromLocationId = $data['from_location_id'];
        $toLocationId = $data['to_location_id'];
        $quantity = $data['quantity'];

        // Subtract from source
        $fromBalance = StockBalance::firstOrCreate([
            'inventory_item_id' => $itemId,
            'location_id' => $fromLocationId,
        ]);
        if ($fromBalance->quantity < $quantity) {
            Notification::make()
                ->title('Insufficient stock in source store!')
                ->danger()
                ->send();
            throw new \Exception('Insufficient stock');
        }
        $fromBalance->quantity -= $quantity;
        $fromBalance->save();

        // Add to destination
        $toBalance = StockBalance::firstOrCreate([
            'inventory_item_id' => $itemId,
            'location_id' => $toLocationId,
        ]);
        $toBalance->quantity += $quantity;
        $toBalance->save();

        // Create a transfer transaction record
        return ItemTransaction::create([
            'inventory_item_id' => $itemId,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'type' => 'transfer',
            'quantity' => $quantity,
            'user_id' => auth()->id(),
            'remarks' => $data['remarks'] ?? null,
            'transaction_date' => now(),
        ]);
    }
}
