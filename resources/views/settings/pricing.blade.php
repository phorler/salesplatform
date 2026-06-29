<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Pricing rules') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 px-4 py-3 bg-green-100 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-600 mb-6">
                    {{ __('How prices are suggested. The multiplier is applied to a reference/market price for each condition; the floor and ceiling clamp the result.') }}
                </p>

                <form method="POST" action="{{ route('settings.pricing.update') }}">
                    @csrf @method('PUT')

                    <div class="mb-6">
                        <x-input-label for="strategy" :value="__('Strategy')" />
                        <select id="strategy" name="strategy" class="mt-1 block w-full sm:w-72 border-gray-300 rounded-md shadow-sm">
                            @foreach (config('pricing.strategies') as $key => $class)
                                <option value="{{ $key }}" @selected($rule->strategy === $key)>
                                    {{ \Illuminate\Support\Str::headline($key) }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('strategy')" class="mt-2" />
                        <p class="mt-1 text-xs text-gray-500">{{ __('Live Amazon competitive pricing becomes available in a later release.') }}</p>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">{{ __('Condition multipliers') }}</h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            @foreach ($conditions as $c)
                                <div>
                                    <x-input-label :for="'mult_'.$c->value" :value="$c->label()" />
                                    <x-text-input :id="'mult_'.$c->value" type="number" step="0.01" min="0" max="5"
                                                  name="multipliers[{{ $c->value }}]" class="mt-1 block w-full"
                                                  :value="old('multipliers.'.$c->value, $rule->multiplierFor($c))" />
                                    <x-input-error :messages="$errors->get('multipliers.'.$c->value)" class="mt-1" />
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="price_floor" :value="__('Price floor (£)')" />
                            <x-text-input id="price_floor" name="price_floor" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                          :value="old('price_floor', $rule->price_floor)" />
                            <x-input-error :messages="$errors->get('price_floor')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="price_ceiling" :value="__('Price ceiling (£)')" />
                            <x-text-input id="price_ceiling" name="price_ceiling" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                          :value="old('price_ceiling', $rule->price_ceiling)" />
                            <x-input-error :messages="$errors->get('price_ceiling')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="undercut_amount" :value="__('Undercut by (£)')" />
                            <x-text-input id="undercut_amount" name="undercut_amount" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                          :value="old('undercut_amount', $rule->undercut_amount)" />
                            <x-input-error :messages="$errors->get('undercut_amount')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-6">
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
