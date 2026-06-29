<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Marketplaces') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="px-4 py-3 bg-green-100 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('status') }}
                </div>
            @endif
            @error('amazon')
                <div class="px-4 py-3 bg-red-100 border border-red-200 text-red-800 rounded-md text-sm">{{ $message }}</div>
            @enderror

            {{-- Connected accounts --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-3">{{ __('Connected accounts') }}</h3>
                @forelse ($accounts as $account)
                    <div class="flex items-center justify-between py-3 border-b last:border-0">
                        <div>
                            <div class="font-medium text-gray-900">{{ $account->label ?: ucfirst($account->channel) }}</div>
                            <div class="text-sm text-gray-500">
                                {{ ucfirst($account->channel) }} · {{ $account->marketplace_id }}
                                · <span class="capitalize">{{ $account->status->label() }}</span>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('marketplace.destroy', $account) }}"
                              onsubmit="return confirm('Disconnect this marketplace?');">
                            @csrf @method('DELETE')
                            <button class="text-sm text-red-600 underline">{{ __('Disconnect') }}</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">{{ __('No marketplaces connected yet.') }}</p>
                @endforelse
            </div>

            {{-- Connect Amazon --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-2">{{ __('Connect Amazon') }}</h3>
                @if ($amazonConfigured)
                    <p class="text-sm text-gray-600 mb-4">
                        {{ __('Authorize this app to list books and read orders on your Amazon seller account.') }}
                    </p>
                    <a href="{{ route('marketplace.amazon.connect') }}"
                       class="inline-flex items-center px-4 py-2 bg-[#ff9900] border border-transparent rounded-md font-semibold text-xs text-gray-900 uppercase tracking-widest hover:bg-amber-400">
                        {{ __('Connect Amazon') }}
                    </a>
                @else
                    <p class="text-sm text-gray-600">
                        {{ __('Amazon integration is not configured on this server yet. Once the SP-API app credentials (SPAPI_APP_ID, SPAPI_LWA_CLIENT_ID, SPAPI_LWA_CLIENT_SECRET) are set, a “Connect Amazon” button will appear here.') }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
