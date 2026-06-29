<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $item->product->title }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('inventory.edit', $item) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('Edit') }}
                </a>
                <form method="POST" action="{{ route('inventory.destroy', $item) }}"
                      onsubmit="return confirm('Remove this item from inventory?');">
                    @csrf @method('DELETE')
                    <button class="inline-flex items-center px-4 py-2 bg-red-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                        {{ __('Delete') }}
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="px-4 py-3 bg-green-100 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('status') }}
                </div>
            @endif
            @foreach (['publish', 'price'] as $errKey)
                @error($errKey)
                    <div class="px-4 py-3 bg-red-100 border border-red-200 text-red-800 rounded-md text-sm">{{ $message }}</div>
                @enderror
            @endforeach

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex gap-6">
                    @if ($item->product->cover_url)
                        <img src="{{ $item->product->cover_url }}" alt="" class="w-28 h-auto rounded shadow" />
                    @endif
                    <div class="flex-1">
                        <div class="text-lg font-semibold text-gray-900">{{ $item->product->title }}</div>
                        @if ($item->product->subtitle)
                            <div class="text-gray-600">{{ $item->product->subtitle }}</div>
                        @endif
                        <div class="text-gray-700 mt-1">{{ $item->product->authorLine() }}</div>
                        <div class="text-gray-500 text-sm mt-1">
                            {{ $item->product->publisher }}
                            @if ($item->product->published_year) · {{ $item->product->published_year }} @endif
                            · ISBN {{ $item->product->isbn13 }}
                        </div>
                        <div class="mt-2">
                            <a href="https://www.amazon.co.uk/s?k={{ $item->product->isbn13 }}"
                               target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800 underline">
                                {{ __('Search on Amazon UK') }}
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        </div>

                        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-6 text-sm">
                            <div><dt class="text-gray-500">{{ __('SKU') }}</dt><dd class="font-mono">{{ $item->sku }}</dd></div>
                            <div><dt class="text-gray-500">{{ __('Condition') }}</dt><dd>{{ $item->condition->label() }}</dd></div>
                            <div><dt class="text-gray-500">{{ __('Status') }}</dt><dd>{{ $item->status->label() }}</dd></div>
                            <div><dt class="text-gray-500">{{ __('Quantity') }}</dt><dd>{{ $item->quantity }}</dd></div>
                            <div><dt class="text-gray-500">{{ __('Cost') }}</dt><dd>{{ $item->cost !== null ? '£'.number_format($item->cost, 2) : '—' }}</dd></div>
                            <div><dt class="text-gray-500">{{ __('Suggested') }}</dt><dd>{{ $item->suggested_price !== null ? '£'.number_format($item->suggested_price, 2) : '—' }}</dd></div>
                            <div><dt class="text-gray-500">{{ __('List price') }}</dt><dd>{{ $item->list_price !== null ? '£'.number_format($item->list_price, 2) : '—' }}</dd></div>
                            <div><dt class="text-gray-500">{{ __('Location') }}</dt><dd>{{ $item->location ?: '—' }}</dd></div>
                        </dl>

                        @if ($item->condition_note)
                            <p class="mt-4 text-sm"><span class="text-gray-500">{{ __('Condition note:') }}</span> {{ $item->condition_note }}</p>
                        @endif
                        @if ($item->notes)
                            <p class="mt-2 text-sm"><span class="text-gray-500">{{ __('Notes:') }}</span> {{ $item->notes }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Amazon market (Keepa) --}}
            @if ($item->product->latestObservation)
                @php $obs = $item->product->latestObservation; @endphp
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-gray-800">{{ __('Amazon market') }}</h3>
                        <span class="text-xs text-gray-400">{{ __('via Keepa · as of') }} {{ $obs->observed_at?->diffForHumans() }}</span>
                    </div>
                    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div><dt class="text-gray-500">{{ __('Lowest new') }}</dt><dd>{{ $obs->new_price !== null ? '£'.number_format($obs->new_price, 2) : '—' }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Lowest used') }}</dt><dd>{{ $obs->used_price !== null ? '£'.number_format($obs->used_price, 2) : '—' }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Sales rank') }}</dt><dd>{{ $obs->sales_rank !== null ? '#'.number_format($obs->sales_rank) : '—' }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Your list') }}</dt><dd>{{ $item->list_price !== null ? '£'.number_format($item->list_price, 2) : '—' }}</dd></div>
                    </dl>
                    @if ($item->list_price !== null && $obs->used_price !== null && $item->list_price > $obs->used_price)
                        <p class="mt-3 text-xs text-amber-700">{{ __('Your price is above the lowest used offer — consider Live price to undercut.') }}</p>
                    @endif
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
