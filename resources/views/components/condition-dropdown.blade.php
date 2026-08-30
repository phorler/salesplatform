{{-- Condition picker where each option shows the Amazon label + full guideline.
     Must sit inside an Alpine scope that defines `condition` (selected value) and
     `guidelines` (map of value => { label, description }). Submits via a hidden
     `condition` field. --}}
<div x-data="{ open: false }" class="relative mt-1">
    <input type="hidden" name="condition" :value="condition">

    <button type="button" @click="open = !open"
            class="w-full flex items-center justify-between border border-gray-300 rounded-md shadow-sm px-3 py-2 text-left bg-white hover:bg-gray-50">
        <span class="font-medium text-gray-900" x-text="guidelines[condition].label"></span>
        <span class="text-gray-400" x-text="open ? '▲' : '▼'"></span>
    </button>

    <div x-show="open" x-cloak @click.outside="open = false"
         class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-96 overflow-auto divide-y divide-gray-100">
        <template x-for="c in Object.keys(guidelines)" :key="c">
            <button type="button" @click="condition = c; open = false"
                    class="block w-full text-left px-3 py-3 hover:bg-indigo-50"
                    :class="condition === c ? 'bg-indigo-50' : ''">
                <div class="font-medium text-gray-900 text-sm flex items-center gap-2">
                    <span x-text="guidelines[c].label"></span>
                    <span x-show="condition === c" class="inline-flex px-1.5 py-0.5 rounded-full text-[10px] bg-indigo-600 text-white">{{ __('Selected') }}</span>
                </div>
                <div class="text-xs text-gray-600 mt-1 leading-relaxed" x-text="guidelines[c].description"></div>
            </button>
        </template>
    </div>
</div>
