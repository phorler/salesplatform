<?php

namespace App\Http\Controllers;

use App\Enums\Condition;
use App\Services\Pricing\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PricingRuleController extends Controller
{
    public function __construct(private readonly PricingService $pricing) {}

    public function edit(Request $request): View
    {
        return view('settings.pricing', [
            'rule' => $this->pricing->ruleFor($request->user()),
            'conditions' => Condition::cases(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $conditionValues = array_column(Condition::cases(), 'value');

        $validated = $request->validate([
            'strategy' => ['required', 'string', 'in:'.implode(',', array_keys(config('pricing.strategies')))],
            'multipliers' => ['required', 'array'],
            'multipliers.*' => ['required', 'numeric', 'min:0', 'max:5'],
            'price_floor' => ['nullable', 'numeric', 'min:0'],
            'price_ceiling' => ['nullable', 'numeric', 'min:0', 'gte:price_floor'],
            'undercut_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Keep only known conditions out of the submitted multipliers.
        $multipliers = array_intersect_key($validated['multipliers'], array_flip($conditionValues));

        $rule = $this->pricing->ruleFor($request->user());
        $rule->update([
            'strategy' => $validated['strategy'],
            'multipliers' => $multipliers,
            'price_floor' => $validated['price_floor'] ?? null,
            'price_ceiling' => $validated['price_ceiling'] ?? null,
            'undercut_amount' => $validated['undercut_amount'] ?? null,
        ]);

        return redirect()
            ->route('settings.pricing.edit')
            ->with('status', 'Pricing rules saved.');
    }
}
