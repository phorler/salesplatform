<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceAccount;
use App\Services\Amazon\AmazonListingsReportImporter;
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

    /**
     * Import an Amazon listings report (Seller Central → Reports → Inventory
     * Reports) to refresh listing status/price/quantity by SKU.
     */
    public function importListings(Request $request, AmazonListingsReportImporter $importer): RedirectResponse
    {
        $request->validate([
            'report' => ['required', 'file', 'max:20480', 'mimes:txt,tsv,csv,tab'],
        ]);

        $result = $importer->import($request->user(), $request->file('report')->get());

        if (isset($result['error'])) {
            return back()->withErrors(['report' => $result['error']]);
        }

        $message = "Imported {$result['matched']} listing(s).";
        if ($result['unmatched'] > 0) {
            $message .= " {$result['unmatched']} SKU(s) had no matching inventory item.";
        }

        return back()->with('status', $message);
    }
}
