<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Inventory') }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('inventory.export', request()->only(['q', 'status', 'condition'])) }}"
                   class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    {{ __('Export Amazon CSV') }}
                </a>
                <a href="{{ route('inventory.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('Add book') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 bg-green-100 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Filters --}}
            <form method="GET" class="mb-4 flex flex-wrap gap-2 items-end bg-white p-4 rounded-lg shadow-sm">
                <div class="flex-1 min-w-48">
                    <x-input-label for="q" :value="__('Search (title, ISBN, SKU)')" />
                    <x-text-input id="q" name="q" type="search" class="mt-1 block w-full" :value="$filters['q'] ?? ''" />
                </div>
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <select id="status" name="status" class="mt-1 border-gray-300 rounded-md shadow-sm">
                        <option value="">{{ __('Any') }}</option>
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="condition" :value="__('Condition')" />
                    <select id="condition" name="condition" class="mt-1 border-gray-300 rounded-md shadow-sm">
                        <option value="">{{ __('Any') }}</option>
                        @foreach ($conditions as $c)
                            <option value="{{ $c->value }}" @selected(($filters['condition'] ?? '') === $c->value)>{{ $c->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button>{{ __('Filter') }}</x-primary-button>
                @if (array_filter($filters))
                    <a href="{{ route('inventory.index') }}" class="px-3 py-2 text-sm text-gray-600 underline">{{ __('Clear') }}</a>
                @endif
            </form>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if ($items->isEmpty())
                    <div class="p-8 text-center text-gray-500">
                        {{ __('No books match.') }}
                        <a href="{{ route('inventory.create') }}" class="text-indigo-600 underline">{{ __('Add one') }}</a>.
                    </div>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3">{{ __('Book') }}</th>
                                <th class="px-4 py-3">{{ __('Condition') }}</th>
                                <th class="px-4 py-3">{{ __('SKU') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Qty') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('List £') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($items as $item)
                                <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('inventory.show', $item) }}'">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            @if ($item->product->cover_url)
                                                <img src="{{ $item->product->cover_url }}" alt="" class="w-8 h-auto rounded" loading="lazy" />
                                            @endif
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $item->product->title }}</div>
                                                <div class="text-gray-500">{{ $item->product->authorLine() }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">{{ $item->condition->label() }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $item->sku }}</td>
                                    <td class="px-4 py-3 text-right">{{ $item->quantity }}</td>
                                    <td class="px-4 py-3 text-right">{{ $item->list_price !== null ? number_format($item->list_price, 2) : '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700">
                                            {{ $item->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="px-4 py-3">
                        {{ $items->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
