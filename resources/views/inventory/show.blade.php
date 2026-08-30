<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $item->product->title }}</h2>
            <form method="POST" action="{{ route('inventory.destroy', $item) }}"
                  onsubmit="return confirm('Remove this item from inventory?');">
                @csrf @method('DELETE')
                <button class="inline-flex items-center px-4 py-2 bg-red-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                    {{ __('Delete') }}
                </button>
            </form>
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
                        @php
                            // For books the Amazon ASIN is normally the ISBN-10, and the
                            // "More buying choices" (all sellers/prices) page is keyed by ASIN.
                            // Derive the ISBN-10 from the ISBN-13 when it isn't stored (works
                            // for all 978-prefixed books; 979 ones have no ISBN-10).
                            $amazonAsin = $item->product->isbn10 ?: \App\Support\Isbn::toIsbn10($item->product->isbn13);
                            $amazonIcon = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>';
                        @endphp
                        <div class="mt-2 flex flex-wrap gap-4">
                            @if ($amazonAsin)
                                <a href="https://www.amazon.co.uk/gp/offer-listing/{{ $amazonAsin }}"
                                   target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800 underline">
                                    {{ __('More buying choices on Amazon UK') }}
                                    {!! $amazonIcon !!}
                                </a>
                            @endif
                            <a href="https://www.amazon.co.uk/s?k={{ $item->product->isbn13 }}"
                               target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 underline">
                                {{ __('Search on Amazon UK') }}
                                {!! $amazonIcon !!}
                            </a>
                        </div>

                        <div class="mt-4 text-sm text-gray-500">{{ __('SKU') }}: <span class="font-mono text-gray-700">{{ $item->sku }}</span></div>
                    </div>
                </div>

                {{-- Inline editable fields --}}
                <form method="POST" action="{{ route('inventory.update', $item) }}" class="mt-6 border-t pt-6"
                      x-data="editItem(@js($guidelines), @js($multipliers))">
                    @csrf @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <x-input-label for="condition" :value="__('Condition')" />
                            <select id="condition" name="condition" x-model="condition"
                                    class="mt-1 block w-full sm:w-72 border-gray-300 rounded-md shadow-sm">
                                @foreach ($conditions as $c)
                                    <option value="{{ $c->value }}" @selected(old('condition', $item->condition->value) === $c->value)>{{ $c->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('condition')" class="mt-2" />

                            {{-- Amazon guideline for the selected condition (updates on change) --}}
                            <div class="mt-2">
                                <button type="button" @click="showGuideline = !showGuideline"
                                        class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800">
                                    <span x-text="showGuideline ? '▾' : '▸'"></span>
                                    {{ __('Amazon guideline for') }} “<span x-text="guidelines[condition].label"></span>”
                                </button>
                                <p x-show="showGuideline" x-cloak
                                   class="mt-1 text-xs text-gray-600 bg-gray-50 rounded-md p-3" x-text="guidelines[condition].description"></p>
                            </div>
                        </div>

                        <div>
                            <x-input-label for="status" :value="__('Status')" />
                            <select id="status" name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                @foreach ($statuses as $s)
                                    <option value="{{ $s->value }}" @selected(old('status', $item->status->value) === $s->value)>{{ $s->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="quantity" :value="__('Quantity')" />
                            <x-text-input id="quantity" name="quantity" type="number" min="1" class="mt-1 block w-full"
                                          :value="old('quantity', $item->quantity)" />
                            <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="cost" :value="__('Your cost (£)')" />
                            <x-text-input id="cost" name="cost" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                          :value="old('cost', $item->cost)" />
                        </div>

                        <div>
                            <x-input-label for="reference_price" :value="__('Market price (£, optional)')" />
                            <x-text-input id="reference_price" name="reference_price" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                          x-model.number="referencePrice" />
                            <p class="mt-1 text-xs text-gray-600" x-show="suggested !== null">
                                {{ __('Suggested:') }} £<span x-text="suggested"></span>
                                <button type="button" class="ml-1 underline" @click="listPrice = suggested">{{ __('use') }}</button>
                            </p>
                        </div>

                        <div>
                            <x-input-label for="list_price" :value="__('List price (£)')" />
                            <x-text-input id="list_price" name="list_price" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                          x-model.number="listPrice" />
                        </div>

                        <div>
                            <x-input-label for="location" :value="__('Location / shelf')" />
                            <x-text-input id="location" name="location" type="text" class="mt-1 block w-full"
                                          :value="old('location', $item->location)" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="condition_note" :value="__('Condition note')" />
                            <x-text-input id="condition_note" name="condition_note" type="text" class="mt-1 block w-full"
                                          :value="old('condition_note', $item->condition_note)" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="notes" :value="__('Notes')" />
                            <textarea id="notes" name="notes" rows="2"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('notes', $item->notes) }}</textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-6">
                        <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                    </div>
                </form>
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

    @push('scripts')
    <script>
        function editItem(guidelines, multipliers) {
            return {
                guidelines,
                multipliers,
                condition: @js(old('condition', $item->condition->value)),
                referencePrice: null,
                listPrice: @js($item->list_price !== null ? (float) $item->list_price : null),
                showGuideline: false,
                get suggested() {
                    if (this.referencePrice == null || this.referencePrice === '') return null;
                    const m = this.multipliers[this.condition] ?? 1;
                    return (Math.round(this.referencePrice * m * 100) / 100).toFixed(2);
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
