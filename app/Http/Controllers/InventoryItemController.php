<?php

namespace App\Http\Controllers;

use App\Channels\Data\Money;
use App\Enums\Condition;
use App\Enums\InventoryStatus;
use App\Models\InventoryItem;
use App\Services\InventoryService;
use App\Services\OpenLibraryService;
use App\Services\Pricing\ManualMultiplierStrategy;
use App\Services\Pricing\PricingContext;
use App\Services\Pricing\PricingService;
use App\Support\Isbn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryItemController extends Controller
{
    public function __construct(
        private readonly OpenLibraryService $openLibrary,
        private readonly InventoryService $inventory,
        private readonly PricingService $pricing,
    ) {}

    public function index(Request $request): View
    {
        $items = $this->filteredQuery($request)
            ->with('product')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.index', [
            'items' => $items,
            'filters' => $request->only(['q', 'status', 'condition']),
            'conditions' => Condition::cases(),
            'statuses' => InventoryStatus::cases(),
        ]);
    }

    /**
     * Download the current (filtered) inventory as an Amazon Inventory Loader
     * CSV — for manually adding offers via Seller Central while SP-API access is
     * pending. Only priced, in-stock items are exported.
     */
    public function export(Request $request): StreamedResponse
    {
        $items = $this->filteredQuery($request)
            ->with('product')
            ->where('quantity', '>', 0)
            ->get();

        $columns = [
            'sku', 'product-id', 'product-id-type', 'price', 'item-condition',
            'quantity', 'add-delete', 'item-note', 'fulfillment-center-id',
        ];

        return response()->streamDownload(function () use ($items, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);

            foreach ($items as $item) {
                fputcsv($out, [
                    $item->sku,
                    // ISBN-10 matches Amazon's ISBN type most reliably; fall back to ISBN-13.
                    $item->product->isbn10 ?: $item->product->isbn13,
                    2, // product-id-type: 2 = ISBN
                    $item->list_price ?? $item->suggested_price,
                    $item->condition->amazonInventoryLoaderCode(),
                    $item->quantity,
                    'a', // add
                    $item->condition_note,
                    '', // fulfillment-center-id blank = merchant-fulfilled
                ]);
            }

            fclose($out);
        }, 'amazon-inventory-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Shared, filter-aware inventory query for the current user.
     */
    private function filteredQuery(Request $request)
    {
        $filters = $request->only(['q', 'status', 'condition']);

        return $request->user()->inventoryItems()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['condition'] ?? null, fn ($query, $condition) => $query->where('condition', $condition))
            ->when($filters['q'] ?? null, function ($query, $term) {
                $query->where(function ($q) use ($term) {
                    $q->where('sku', 'like', "%{$term}%")
                        ->orWhereHas('product', fn ($p) => $p
                            ->where('title', 'like', "%{$term}%")
                            ->orWhere('isbn13', 'like', "%{$term}%")
                            ->orWhere('isbn10', 'like', "%{$term}%"));
                });
            })
            ->latest();
    }

    public function create(Request $request): View
    {
        return view('inventory.create', [
            'conditions' => Condition::cases(),
            'multipliers' => $this->multipliersFor($request),
        ]);
    }

    /**
     * AJAX endpoint: look up a book by ISBN and return its catalogue data so the
     * add form can preview it before saving.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate(['isbn' => ['required', 'string']]);

        if (! Isbn::isValid($validated['isbn'])) {
            return response()->json(['message' => 'That doesn\'t look like a valid ISBN.'], 422);
        }

        $product = $this->openLibrary->lookup($validated['isbn']);

        if (! $product) {
            return response()->json(['message' => 'No book found for that ISBN.'], 404);
        }

        return response()->json([
            'isbn13' => $product->isbn13,
            'title' => $product->title,
            'subtitle' => $product->subtitle,
            'authors' => $product->authorLine(),
            'publisher' => $product->publisher,
            'published_year' => $product->published_year,
            'cover_url' => $product->cover_url,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateItem($request, requireIsbn: true);

        $product = $this->openLibrary->lookup($validated['isbn']);
        if (! $product) {
            return back()->withErrors(['isbn' => 'Could not find that book to add.'])->withInput();
        }

        $condition = Condition::from($validated['condition']);
        $suggested = $this->suggestedPrice($request, $condition, $validated['reference_price'] ?? null);

        $item = $this->inventory->createFromProduct($request->user(), $product, [
            'condition' => $condition,
            'condition_note' => $validated['condition_note'] ?? null,
            'quantity' => $validated['quantity'],
            'cost' => $validated['cost'] ?? null,
            'suggested_price' => $suggested,
            'list_price' => $validated['list_price'] ?? $suggested,
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('inventory.index')
            ->with('status', "Added “{$product->title}” ({$item->sku}) to your inventory.");
    }

    public function show(InventoryItem $inventoryItem): View
    {
        $inventoryItem->load('product.latestObservation', 'listings.marketplaceAccount', 'sales');

        return view('inventory.show', ['item' => $inventoryItem]);
    }

    public function edit(Request $request, InventoryItem $inventoryItem): View
    {
        $inventoryItem->load('product');

        return view('inventory.edit', [
            'item' => $inventoryItem,
            'conditions' => Condition::cases(),
            'statuses' => InventoryStatus::cases(),
            'multipliers' => $this->multipliersFor($request),
        ]);
    }

    public function update(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $validated = $this->validateItem($request, requireIsbn: false) + $request->validate([
            'status' => ['required', 'string', 'in:'.$this->enumValues(InventoryStatus::cases())],
        ]);

        $condition = Condition::from($validated['condition']);
        $suggested = $this->suggestedPrice($request, $condition, $validated['reference_price'] ?? null);

        $inventoryItem->update([
            'condition' => $condition,
            'condition_note' => $validated['condition_note'] ?? null,
            'quantity' => $validated['quantity'],
            'cost' => $validated['cost'] ?? null,
            'suggested_price' => $suggested ?? $inventoryItem->suggested_price,
            'list_price' => $validated['list_price'] ?? null,
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => InventoryStatus::from($validated['status']),
        ]);

        return redirect()
            ->route('inventory.show', $inventoryItem)
            ->with('status', 'Item updated.');
    }

    public function destroy(InventoryItem $inventoryItem): RedirectResponse
    {
        $inventoryItem->delete();

        return redirect()
            ->route('inventory.index')
            ->with('status', 'Item removed from inventory.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateItem(Request $request, bool $requireIsbn): array
    {
        return $request->validate([
            'isbn' => [$requireIsbn ? 'required' : 'sometimes', 'string'],
            'condition' => ['required', 'string', 'in:'.$this->enumValues(Condition::cases())],
            'condition_note' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'reference_price' => ['nullable', 'numeric', 'min:0'],
            'list_price' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function suggestedPrice(Request $request, Condition $condition, ?string $referencePrice): ?string
    {
        if ($referencePrice === null) {
            return null;
        }

        // A reference-price suggestion is always a manual multiplier calculation;
        // live competitive pricing is a separate, explicit action.
        $rule = $this->pricing->ruleFor($request->user());
        $rule->strategy = ManualMultiplierStrategy::KEY;

        $suggestion = $this->pricing->suggest(new PricingContext(
            condition: $condition,
            rule: $rule,
            referencePrice: Money::of($referencePrice),
        ));

        return $suggestion?->amount;
    }

    /**
     * Per-condition multipliers for the current user, for the client-side preview.
     *
     * @return array<string, float>
     */
    private function multipliersFor(Request $request): array
    {
        $rule = $this->pricing->ruleFor($request->user());
        $out = [];
        foreach (Condition::cases() as $case) {
            $out[$case->value] = $rule->multiplierFor($case);
        }

        return $out;
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     */
    private function enumValues(array $cases): string
    {
        return implode(',', array_column($cases, 'value'));
    }
}
