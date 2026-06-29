<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit') }}: {{ $item->product->title }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6"
                 x-data="{
                     multipliers: @js($multipliers),
                     condition: '{{ old('condition', $item->condition->value) }}',
                     referencePrice: null,
                     get suggested() {
                         if (this.referencePrice == null || this.referencePrice === '') return null;
                         const m = this.multipliers[this.condition] ?? 1;
                         return (Math.round(this.referencePrice * m * 100) / 100).toFixed(2);
                     },
                 }">
                <form method="POST" action="{{ route('inventory.update', $item) }}">
                    @csrf @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="condition" :value="__('Condition')" />
                            <select id="condition" name="condition" x-model="condition"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                @foreach ($conditions as $c)
                                    <option value="{{ $c->value }}">{{ $c->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('condition')" class="mt-2" />
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
                            </p>
                        </div>

                        <div>
                            <x-input-label for="list_price" :value="__('List price (£)')" />
                            <x-text-input id="list_price" name="list_price" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                          :value="old('list_price', $item->list_price)" />
                        </div>

                        <div>
                            <x-input-label for="location" :value="__('Location / shelf')" />
                            <x-text-input id="location" name="location" type="text" class="mt-1 block w-full"
                                          :value="old('location', $item->location)" />
                        </div>

                        <div>
                            <x-input-label for="condition_note" :value="__('Condition note')" />
                            <x-text-input id="condition_note" name="condition_note" type="text" class="mt-1 block w-full"
                                          :value="old('condition_note', $item->condition_note)" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-input-label for="notes" :value="__('Notes')" />
                        <textarea id="notes" name="notes" rows="2"
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('notes', $item->notes) }}</textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-6">
                        <a href="{{ route('inventory.show', $item) }}" class="text-sm text-gray-600 underline">{{ __('Cancel') }}</a>
                        <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
