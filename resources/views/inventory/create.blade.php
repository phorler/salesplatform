<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Add a book') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6"
                 x-data="bookAdd(@js($multipliers))">

                {{-- Step 1: find the book by ISBN --}}
                <div>
                    <x-input-label for="isbn" :value="__('ISBN (10 or 13 digits)')" />
                    <div class="flex gap-2 mt-1">
                        <x-text-input id="isbn" type="text" class="block w-full" x-model="isbn"
                                      inputmode="numeric" autocomplete="off" autofocus
                                      placeholder="9780140328721"
                                      @keydown.enter.prevent="lookup()" />
                        <x-secondary-button type="button" @click="startScan()" x-show="canScan" x-cloak title="{{ __('Scan barcode') }}">
                            {{ __('Scan') }}
                        </x-secondary-button>
                        <x-primary-button type="button" @click="lookup()" x-bind:disabled="loading">
                            <span x-show="!loading">{{ __('Look up') }}</span>
                            <span x-show="loading">{{ __('Searching…') }}</span>
                        </x-primary-button>
                    </div>
                    <p class="mt-2 text-sm text-red-600" x-show="error" x-text="error"></p>
                    <x-input-error :messages="$errors->get('isbn')" class="mt-2" />

                    {{-- Camera scan overlay --}}
                    <div x-show="scanning" x-cloak
                         class="fixed inset-0 z-50 bg-black/80 flex flex-col items-center justify-center p-4">
                        <div class="relative w-full max-w-md">
                            <video x-ref="video" class="w-full rounded-lg bg-black" muted playsinline></video>
                            <div class="absolute inset-x-6 top-1/2 -translate-y-1/2 h-0.5 bg-red-500/80"></div>
                        </div>
                        <p class="text-white text-sm mt-4">{{ __('Scan the main barcode (the one starting 978/979). Hold steady.') }}</p>
                        <p class="text-red-300 text-sm mt-1" x-show="scanError" x-text="scanError"></p>
                        <button type="button" @click="stopScan()"
                                class="mt-4 px-4 py-2 bg-white/90 rounded-md text-sm font-medium">
                            {{ __('Cancel') }}
                        </button>
                    </div>
                </div>

                {{-- Step 2: preview + details, shown once a book is found --}}
                <form method="POST" action="{{ route('inventory.store') }}" class="mt-6" x-show="found" x-cloak>
                    @csrf
                    <input type="hidden" name="isbn" x-bind:value="book.isbn13" />

                    <div class="flex gap-4 p-4 bg-gray-50 rounded-lg">
                        <template x-if="book.cover_url">
                            <img x-bind:src="book.cover_url" alt="" class="w-20 h-auto rounded shadow" />
                        </template>
                        <div class="text-sm">
                            <div class="font-semibold text-gray-900" x-text="book.title"></div>
                            <div class="text-gray-600" x-text="book.subtitle"></div>
                            <div class="text-gray-700 mt-1" x-text="book.authors"></div>
                            <div class="text-gray-500 mt-1">
                                <span x-text="book.publisher"></span>
                                <span x-show="book.published_year" x-text="'· ' + book.published_year"></span>
                                <span class="ml-1 text-gray-400" x-text="'· ISBN ' + book.isbn13"></span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
                        <div>
                            <x-input-label for="condition" :value="__('Condition')" />
                            <select id="condition" name="condition" x-model="condition"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach ($conditions as $c)
                                    <option value="{{ $c->value }}">{{ $c->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('condition')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="quantity" :value="__('Quantity')" />
                            <x-text-input id="quantity" name="quantity" type="number" min="1" value="1" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="cost" :value="__('Your cost (£, optional)')" />
                            <x-text-input id="cost" name="cost" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                        </div>

                        <div>
                            <x-input-label for="reference_price" :value="__('Market price (£, optional)')" />
                            <x-text-input id="reference_price" name="reference_price" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                          x-model.number="referencePrice" />
                            <p class="mt-1 text-xs text-gray-500">
                                {{ __('Used to suggest a price for this condition. Live Amazon pricing comes later.') }}
                            </p>
                        </div>

                        <div>
                            <x-input-label for="list_price" :value="__('List price (£)')" />
                            <x-text-input id="list_price" name="list_price" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                          x-model.number="listPrice" />
                            <p class="mt-1 text-xs text-gray-600" x-show="suggested !== null">
                                {{ __('Suggested:') }} £<span x-text="suggested"></span>
                                <button type="button" class="ml-1 underline" @click="listPrice = suggested">{{ __('use') }}</button>
                            </p>
                        </div>

                        <div>
                            <x-input-label for="location" :value="__('Location / shelf (optional)')" />
                            <x-text-input id="location" name="location" type="text" class="mt-1 block w-full" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-input-label for="condition_note" :value="__('Condition note (optional)')" />
                        <x-text-input id="condition_note" name="condition_note" type="text" class="mt-1 block w-full"
                                      placeholder="e.g. light shelf wear, name on inside cover" />
                    </div>

                    <div class="flex items-center justify-end mt-6">
                        <x-primary-button>{{ __('Add to inventory') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function bookAdd(multipliers) {
            return {
                isbn: '', loading: false, error: '', found: false, book: {},
                condition: 'good', referencePrice: null, listPrice: null,
                scanning: false, scanError: '', scanner: null,
                canScan: window.BarcodeScanner && window.BarcodeScanner.isSupported(),
                async startScan() {
                    this.scanError = ''; this.scanning = true;
                    this.scanner = new window.BarcodeScanner();
                    try {
                        await this.scanner.start(this.$refs.video, (code) => {
                            this.isbn = code;
                            this.stopScan();
                            this.lookup();
                        });
                    } catch (e) {
                        if (e && e.name === 'NotAllowedError') {
                            this.scanError = 'Camera permission denied.';
                        } else if (!window.isSecureContext) {
                            this.scanError = 'Camera needs a secure (https) connection.';
                        } else {
                            this.scanError = 'Could not start the camera: ' + (e?.message || e?.name || 'unknown error');
                        }
                    }
                },
                stopScan() {
                    if (this.scanner) { this.scanner.stop(); this.scanner = null; }
                    this.scanning = false;
                },
                get suggested() {
                    if (this.referencePrice == null || this.referencePrice === '') return null;
                    const m = multipliers[this.condition] ?? 1;
                    return (Math.round(this.referencePrice * m * 100) / 100).toFixed(2);
                },
                async lookup() {
                    this.error = ''; this.loading = true; this.found = false;
                    try {
                        const res = await fetch('{{ route('inventory.lookup') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({ isbn: this.isbn }),
                        });
                        const data = await res.json();
                        if (!res.ok) { this.error = data.message || 'Lookup failed.'; return; }
                        this.book = data; this.found = true;
                    } catch (e) {
                        this.error = 'Network error during lookup.';
                    } finally {
                        this.loading = false;
                    }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
