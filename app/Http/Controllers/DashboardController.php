<?php

namespace App\Http\Controllers;

use App\Enums\InventoryStatus;
use App\Enums\ListingStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $saleRows = $user->sales()->with('inventoryItem:id,cost')->get();

        return view('dashboard', [
            'stats' => [
                'in_stock' => (int) $user->inventoryItems()
                    ->where('status', '!=', InventoryStatus::Sold->value)
                    ->sum('quantity'),
                'inventory_value' => (float) $user->inventoryItems()
                    ->whereNotNull('list_price')
                    ->sum(DB::raw('list_price * quantity')),
                'active_listings' => $user->listings()->where('status', ListingStatus::Active->value)->count(),
                'units_sold' => (int) $saleRows->sum('quantity'),
                'revenue' => $saleRows->sum(fn ($s) => (float) $s->sale_price * $s->quantity),
                'profit' => $saleRows->sum(fn ($s) => ((float) $s->sale_price - (float) ($s->fees ?? 0) - (float) ($s->inventoryItem->cost ?? 0)) * $s->quantity),
            ],
        ]);
    }
}
