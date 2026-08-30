<?php

namespace App\Http\Controllers;

use App\Channels\Data\Money;
use App\Enums\Condition;
use App\Enums\InventoryStatus;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\Amazon\AmazonOfferScraper;
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

class InventoryItemController extends Controller
{
    public function __construct(
        private readonly OpenLibraryService $openLibrary,
        private readonly InventoryService $inventory,
        private readonly PricingService $pricing,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => $request->query('q'),
            'condition' => $request->query('condition'),
            // Default to Draft on first load; an explicit ?status= (Any) shows all.
            'status' => $request->has('status') ? $request->query('status') : InventoryStatus::Draft->value,
        ];

        $items = $this->filteredQuery($request->user(), $filters)
            ->with('product')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.index', [
            'items' => $items,
            'filters' => $filters,
            'conditions' => Condition::cases(),
            'statuses' => InventoryStatus::cases(),
        ]);
    }

    /**
     * Filter-aware inventory query for a user.
     *
     * @param  array{q: ?string, status: ?string, condition: ?string}  $filters
     */
    private function filteredQuery(User $user, array $filters)
    {
        return $user->inventoryItems()
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
            'statuses' => InventoryStatus::cases(),
            'multipliers' => $this->multipliersFor($request),
            'guidelines' => $this->conditionGuidelines(),
        ]);
    }

    /**
     * condition value => Amazon label + guideline text, for the descriptive
     * condition dropdown on the add/edit forms.
     *
     * @return array<string, array{label: string, description: string}>
     */
    private function conditionGuidelines(): array
    {
        $guidelines = [];
        foreach (Condition::cases() as $case) {
            $guidelines[$case->value] = [
                'label' => $case->amazonLabel(),
                'description' => $case->amazonDescription(),
            ];
        }

        return $guidelines;
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
        $validated = $this->validateItem($request, requireIsbn: true) + $request->validate([
            'status' => ['nullable', 'string', 'in:'.$this->enumValues(InventoryStatus::cases())],
        ]);

        $product = $this->openLibrary->lookup($validated['isbn']);
        if (! $product) {
            return back()->withErrors(['isbn' => 'Could not find that book to add.'])->withInput();
        }

        $condition = Condition::from($validated['condition']);
        $suggested = $this->suggestedPrice($request, $condition, $validated['reference_price'] ?? null);
        $listPrice = $validated['list_price'] ?? $suggested;
        $chosenStatus = isset($validated['status']) ? InventoryStatus::from($validated['status']) : InventoryStatus::Draft;

        $item = $this->inventory->createFromProduct($request->user(), $product, [
            'condition' => $condition,
            'condition_note' => $validated['condition_note'] ?? null,
            'quantity' => $validated['quantity'],
            'cost' => $validated['cost'] ?? null,
            'suggested_price' => $suggested,
            'list_price' => $listPrice,
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => $this->autoStatus($chosenStatus, $listPrice),
        ]);

        return redirect()
            ->route('inventory.index')
            ->with('status', "Added “{$product->title}” ({$item->sku}) to your inventory.");
    }

    public function show(Request $request, InventoryItem $inventoryItem): View
    {
        $inventoryItem->load('product.latestObservation', 'listings.marketplaceAccount', 'sales');

        return view('inventory.show', [
            'item' => $inventoryItem,
            'conditions' => Condition::cases(),
            'statuses' => InventoryStatus::cases(),
            'multipliers' => $this->multipliersFor($request),
            'guidelines' => $this->conditionGuidelines(),
        ]);
    }

    /**
     * AJAX: on-demand (cached) Amazon offer summary for this item, so the page
     * render isn't blocked on the fetch. Returns nulls gracefully on any failure.
     */
    public function amazonOffers(InventoryItem $inventoryItem, AmazonOfferScraper $scraper): JsonResponse
    {
        $summary = $scraper->forIsbn($inventoryItem->product->isbn13);

        return response()->json($summary?->toArray() ?? ['url' => null]);
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
        $listPrice = $validated['list_price'] ?? null;

        $inventoryItem->update([
            'condition' => $condition,
            'condition_note' => $validated['condition_note'] ?? null,
            'quantity' => $validated['quantity'],
            'cost' => $validated['cost'] ?? null,
            'suggested_price' => $suggested ?? $inventoryItem->suggested_price,
            'list_price' => $listPrice,
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => $this->autoStatus(InventoryStatus::from($validated['status']), $listPrice),
        ]);

        return redirect()
            ->route('inventory.show', $inventoryItem)
            ->with('status', 'Item updated.');
    }

    /**
     * A graded, priced book that's still a Draft becomes "Ready to list" on save.
     * Any explicitly-chosen status (Listed/Sold/Inactive/Ready to list) is kept.
     */
    private function autoStatus(InventoryStatus $chosen, $listPrice): InventoryStatus
    {
        $priced = $listPrice !== null && $listPrice !== '';

        return ($chosen === InventoryStatus::Draft && $priced)
            ? InventoryStatus::ReadyToList
            : $chosen;
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
