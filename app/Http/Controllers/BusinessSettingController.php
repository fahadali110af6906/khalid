<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBusinessSettingsRequest;
use App\Models\BusinessSetting;
use App\Models\Location;
use App\Services\Administration\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessSettingController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display the settings configuration dashboard.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        if (!$user->isOwner()) {
            abort(403, 'Forbidden: Only business owners can manage ERP settings.');
        }

        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;
        $settingsGrouped = BusinessSetting::getAllGrouped($locationId);
        $locations = Location::where('is_active', true)->orderBy('name')->get();

        return view('admin.settings.index', compact('settingsGrouped', 'locations', 'locationId'));
    }

    /**
     * Update business settings and log audit differences.
     */
    public function update(UpdateBusinessSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;

        $groupsMap = [
            'business_name' => 'general',
            'legal_name' => 'general',
            'business_phone' => 'general',
            'business_email' => 'general',
            'business_address' => 'general',
            'tax_number' => 'general',
            'invoice_header_text' => 'branding',
            'invoice_footer_terms' => 'branding',
            'logo_url' => 'branding',
            'default_locale' => 'localization',
            'timezone' => 'localization',
            'date_format' => 'localization',
            'time_format' => 'localization',
            'currency_code' => 'localization',
            'currency_symbol' => 'localization',
            'currency_position' => 'localization',
            'decimal_places' => 'localization',
            'sales_invoice_prefix' => 'numbering',
            'purchase_order_prefix' => 'numbering',
            'goods_receipt_prefix' => 'numbering',
            'sales_return_prefix' => 'numbering',
            'purchase_return_prefix' => 'numbering',
            'payment_voucher_prefix' => 'numbering',
            'journal_entry_prefix' => 'numbering',
            'production_order_prefix' => 'numbering',
            'default_report_range' => 'operational',
            'backup_retention_count' => 'operational',
        ];

        $oldValues = [];
        $newValues = [];

        foreach ($validated as $key => $newVal) {
            $oldVal = BusinessSetting::get($key, null, $locationId);
            if ($oldVal !== $newVal) {
                $oldValues[$key] = $oldVal;
                $newValues[$key] = $newVal;
            }

            $group = $groupsMap[$key] ?? 'general';
            BusinessSetting::set($key, $newVal, $group, $locationId);
        }

        if (!empty($newValues)) {
            $this->auditService->log(
                action: 'settings_update',
                model: null,
                description: 'Updated business settings: ' . implode(', ', array_keys($newValues)),
                oldValues: $oldValues,
                newValues: $newValues,
                actor: $user,
                locationId: $locationId
            );
        }

        return redirect()->route('admin.settings.index', ['location_id' => $locationId])
            ->with('success', __('erp.settings.updated_successfully'));
    }
}
