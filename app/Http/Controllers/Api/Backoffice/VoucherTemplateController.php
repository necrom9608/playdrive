<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\BadgeTemplate;
use App\Models\Product;
use App\Models\VoucherTemplate;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class VoucherTemplateController extends Controller
{
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        $tenantId = $currentTenant->id();

        $templates = VoucherTemplate::query()
            ->with(['product:id,name', 'badgeTemplate:id,name,template_type'])
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (VoucherTemplate $template) => $this->transformTemplate($template))
            ->values();

        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'is_active' => (bool) $product->is_active,
            ])
            ->values();

        $badgeTemplates = collect();

        if (Schema::hasTable('badge_templates')) {
            $badgeTemplates = BadgeTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('template_type', 'voucher')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'is_default'])
                ->map(fn (BadgeTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'is_default' => (bool) $template->is_default,
                ])
                ->values();
        }

        return response()->json([
            'data' => [
                'templates' => $templates,
                'products' => $products,
                'badge_templates' => $badgeTemplates,
            ],
        ]);
    }

    public function store(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $tenantId = $currentTenant->id();
        abort_if(! $tenantId, 500, 'No current tenant resolved.');

        $data = $this->validatePayload($request, $tenantId);

        $template = VoucherTemplate::query()->create([
            'tenant_id' => $tenantId,
            'name' => trim($data['name']),
            'product_id' => $data['product_id'],
            'badge_template_id' => $data['badge_template_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $this->nextSortOrder($tenantId),
        ]);

        return response()->json([
            'data' => $this->transformTemplate($template->load(['product:id,name', 'badgeTemplate:id,name,template_type'])),
        ], 201);
    }

    public function update(Request $request, CurrentTenant $currentTenant, VoucherTemplate $voucherTemplate): JsonResponse
    {
        $tenantId = $currentTenant->id();
        abort_if((int) $voucherTemplate->tenant_id !== (int) $tenantId, 404);

        $data = $this->validatePayload($request, $tenantId);

        $voucherTemplate->update([
            'name' => trim($data['name']),
            'product_id' => $data['product_id'],
            'badge_template_id' => $data['badge_template_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return response()->json([
            'data' => $this->transformTemplate($voucherTemplate->fresh(['product:id,name', 'badgeTemplate:id,name,template_type'])),
        ]);
    }

    public function destroy(CurrentTenant $currentTenant, VoucherTemplate $voucherTemplate): JsonResponse
    {
        abort_if((int) $voucherTemplate->tenant_id !== (int) $currentTenant->id(), 404);

        $voucherTemplate->delete();

        return response()->json(['success' => true]);
    }

    private function validatePayload(Request $request, int $tenantId): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'product_id' => ['required', 'integer'],
            'badge_template_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $productExists = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('id', (int) $data['product_id'])
            ->exists();

        abort_unless($productExists, 422, 'Ongeldig product geselecteerd.');

        if (! empty($data['badge_template_id'])) {
            abort_unless(Schema::hasTable('badge_templates'), 422, 'Badge templates tabel niet gevonden.');

            $badgeTemplateExists = BadgeTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('template_type', 'voucher')
                ->where('id', (int) $data['badge_template_id'])
                ->exists();

            abort_unless($badgeTemplateExists, 422, 'Ongeldige voucher badge geselecteerd.');
        }

        return $data;
    }

    private function nextSortOrder(int $tenantId): int
    {
        return ((int) VoucherTemplate::query()
            ->where('tenant_id', $tenantId)
            ->max('sort_order')) + 1;
    }

    private function transformTemplate(VoucherTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'product_id' => $template->product_id,
            'product_name' => $template->product?->name,
            'badge_template_id' => $template->badge_template_id,
            'badge_template_name' => $template->badgeTemplate?->name,
            'is_active' => (bool) $template->is_active,
            'sort_order' => (int) $template->sort_order,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }
}
