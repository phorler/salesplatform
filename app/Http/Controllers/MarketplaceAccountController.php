<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceAccount;
use App\Services\Amazon\AmazonOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceAccountController extends Controller
{
    public function index(Request $request, AmazonOAuth $oauth): View
    {
        return view('marketplace.index', [
            'accounts' => $request->user()->marketplaceAccounts()->latest()->get(),
            'amazonConfigured' => $oauth->isConfigured(),
        ]);
    }

    public function destroy(MarketplaceAccount $marketplaceAccount): RedirectResponse
    {
        $marketplaceAccount->delete();

        return redirect()
            ->route('marketplace.index')
            ->with('status', 'Marketplace disconnected.');
    }
}
