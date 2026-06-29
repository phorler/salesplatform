<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Sales') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white shadow-sm rounded-lg p-4"><div class="text-xs uppercase text-gray-500">{{ __('Units sold') }}</div><div class="text-2xl font-semibold">{{ number_format($totals['count']) }}</div></div>
                <div class="bg-white shadow-sm rounded-lg p-4"><div class="text-xs uppercase text-gray-500">{{ __('Revenue') }}</div><div class="text-2xl font-semibold">£{{ number_format($totals['revenue'], 2) }}</div></div>
                <div class="bg-white shadow-sm rounded-lg p-4"><div class="text-xs uppercase text-gray-500">{{ __('Fees') }}</div><div class="text-2xl font-semibold">£{{ number_format($totals['fees'], 2) }}</div></div>
                <div class="bg-white shadow-sm rounded-lg p-4"><div class="text-xs uppercase text-gray-500">{{ __('Profit') }}</div><div class="text-2xl font-semibold">£{{ number_format($totals['profit'], 2) }}</div></div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if ($sales->isEmpty())
                    <div class="p-8 text-center text-gray-500">{{ __('No sales recorded yet. Sales sync automatically from connected marketplaces.') }}</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3">{{ __('Sold') }}</th>
                                <th class="px-4 py-3">{{ __('Book') }}</th>
                                <th class="px-4 py-3">{{ __('Channel') }}</th>
                                <th class="px-4 py-3">{{ __('Order') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Qty') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Price £') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Profit £') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($sales as $sale)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $sale->sold_at?->format('Y-m-d') }}</td>
                                    <td class="px-4 py-3">{{ $sale->inventoryItem?->product?->title ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ ucfirst($sale->channel) }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $sale->external_order_id }}</td>
                                    <td class="px-4 py-3 text-right">{{ $sale->quantity }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($sale->sale_price, 2) }}</td>
                                    <td class="px-4 py-3 text-right">{{ $sale->profit() !== null ? number_format($sale->profit(), 2) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="px-4 py-3">{{ $sales->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
