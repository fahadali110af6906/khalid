<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\Production\ProductionService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductionReportController extends Controller
{
    public function __construct(
        protected ProductionService $productionService
    ) {}

    /**
     * Display the production analytics & summary report.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = [
            'from_date' => $request->query('from_date'),
            'to_date' => $request->query('to_date'),
            'location_id' => $request->query('location_id'),
            'status' => $request->query('status'),
            'product_id' => $request->query('product_id'),
        ];

        $reportData = $this->productionService->getProductionReport($user, $filters);

        // Factory locations for filter dropdown
        $locations = $user->isOwner()
            ? Location::where('type', 'factory')->where('is_active', true)->orderBy('name')->get()
            : Location::where('type', 'factory')->where('is_active', true)->whereIn('id', $user->locations->pluck('id'))->orderBy('name')->get();

        return view('production.reports.index', [
            'filters' => $reportData['filters'],
            'summary' => $reportData['summary'],
            'orders' => $reportData['orders'],
            'locations' => $locations,
        ]);
    }

    /**
     * Export the production report as CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();

        $filters = [
            'from_date' => $request->query('from_date'),
            'to_date' => $request->query('to_date'),
            'location_id' => $request->query('location_id'),
            'status' => $request->query('status'),
            'product_id' => $request->query('product_id'),
        ];

        $reportData = $this->productionService->getProductionReport($user, $filters);

        return $this->productionService->exportProductionReportCsv($reportData);
    }
}
