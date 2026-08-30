<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Amazon exports') }}</h2>
            <form method="POST" action="{{ route('exports.store') }}">
                @csrf
                <button class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('Export ready to list') }}
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="px-4 py-3 bg-green-100 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-600">
                    {{ __('Exporting builds an Amazon Inventory Loader CSV of every book marked') }}
                    <span class="font-medium">{{ __('Ready to list') }}</span>
                    ({{ $readyCount }} {{ __('now') }}).
                    {{ __('Upload it in Seller Central, then mark the batch listed here so those books move to Listed and drop out of the next export.') }}
                </p>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if ($batches->isEmpty())
                    <div class="p-8 text-center text-gray-500">{{ __('No exports yet.') }}</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3">{{ __('Exported') }}</th>
                                <th class="px-4 py-3">{{ __('File') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Items') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($batches as $batch)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $batch->created_at->format('Y-m-d H:i') }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $batch->filename }}</td>
                                    <td class="px-4 py-3 text-right">{{ $batch->item_count }}</td>
                                    <td class="px-4 py-3">
                                        @if ($batch->isMarkedListed())
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">
                                                {{ __('Listed') }} · {{ $batch->marked_listed_at->format('Y-m-d') }}
                                            </span>
                                        @else
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700">{{ __('Awaiting listing') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="{{ route('exports.download', $batch) }}" class="text-indigo-600 underline">{{ __('Download') }}</a>
                                            @unless ($batch->isMarkedListed())
                                                <form method="POST" action="{{ route('exports.mark-listed', $batch) }}"
                                                      onsubmit="return confirm('Mark these {{ $batch->item_count }} item(s) as listed?');">
                                                    @csrf
                                                    <button class="text-gray-700 underline">{{ __('Mark listed') }}</button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="px-4 py-3">{{ $batches->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
