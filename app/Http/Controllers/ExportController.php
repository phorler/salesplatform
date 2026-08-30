<?php

namespace App\Http\Controllers;

use App\Models\ExportBatch;
use App\Services\AmazonExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    public function __construct(private readonly AmazonExportService $exports) {}

    public function index(Request $request): View
    {
        return view('exports.index', [
            'batches' => $request->user()->exportBatches()->latest()->paginate(25),
            'readyCount' => $this->exports->readyItems($request->user())->count(),
        ]);
    }

    /**
     * Build a CSV of all "Ready to list" items, save it as a batch, and download it.
     */
    public function store(Request $request): Response|RedirectResponse
    {
        $batch = $this->exports->createBatch($request->user());

        if (! $batch) {
            return back()->with('status', 'No items are ready to list yet. Grade and price some books first.');
        }

        return $this->download($batch);
    }

    /** Re-download a previously exported batch's CSV, unchanged. */
    public function download(ExportBatch $exportBatch): Response
    {
        return response($exportBatch->csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$exportBatch->filename.'"',
        ]);
    }

    /** Mark the batch's items as Listed (they've been uploaded to Amazon). */
    public function markListed(ExportBatch $exportBatch): RedirectResponse
    {
        $count = $this->exports->markListed($exportBatch);

        return redirect()
            ->route('exports.index')
            ->with('status', "Marked {$count} item(s) as listed.");
    }
}
