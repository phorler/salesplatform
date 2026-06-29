<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $sales = $user->sales()
            ->with('inventoryItem.product')
            ->latest('sold_at')
            ->paginate(25);

        // Totals across all sales (modest volumes; computed in PHP for clarity).
        $rows = $user->sales()->with('inventoryItem:id,cost')->get();
        $revenue = $rows->sum(fn ($s) => (float) $s->sale_price * $s->quantity);
        $fees = $rows->sum(fn ($s) => (float) ($s->fees ?? 0) * $s->quantity);
        $profit = $rows->sum(fn ($s) => ((float) $s->sale_price - (float) ($s->fees ?? 0) - (float) ($s->inventoryItem->cost ?? 0)) * $s->quantity);

        return view('sales.index', [
            'sales' => $sales,
            'totals' => [
                'count' => $rows->sum('quantity'),
                'revenue' => $revenue,
                'fees' => $fees,
                'profit' => $profit,
            ],
        ]);
    }
}
