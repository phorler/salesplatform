<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Dashboard') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                @php
                    $cards = [
                        ['label' => 'In stock', 'value' => number_format($stats['in_stock'])],
                        ['label' => 'Stock value', 'value' => '£'.number_format($stats['inventory_value'], 2)],
                        ['label' => 'Active listings', 'value' => number_format($stats['active_listings'])],
                        ['label' => 'Units sold', 'value' => number_format($stats['units_sold'])],
                        ['label' => 'Revenue', 'value' => '£'.number_format($stats['revenue'], 2)],
                        ['label' => 'Profit', 'value' => '£'.number_format($stats['profit'], 2)],
                    ];
                @endphp
                @foreach ($cards as $card)
                    <div class="bg-white shadow-sm rounded-lg p-4">
                        <div class="text-xs uppercase tracking-wide text-gray-500">{{ $card['label'] }}</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $card['value'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('inventory.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">{{ __('Add book') }}</a>
                <a href="{{ route('inventory.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">{{ __('View inventory') }}</a>
                <a href="{{ route('sales.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">{{ __('Sales') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>
