<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\CateringOption;
use App\Models\PricingProfile;
use App\Models\PricingRule;
use App\Models\Product;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PricingEngineController extends Controller
{
    public function overview(CurrentTenant $currentTenant): JsonResponse
    {
        $tenantId = $currentTenant->id();

        $profiles = PricingProfile::query()
            ->with(['rules'])
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PricingProfile $profile) => $this->mapProfile($profile))
            ->values();

        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'vat_rate', 'price_excl_vat']);

        $cateringOptions = CateringOption::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'profiles' => $profiles,
            'products' => $products,
            'catering_options' => $cateringOptions,
        ]);
    }

    public function storeProfile(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'extra_block_minutes' => ['required', 'integer', 'min:1', 'max:240'],
        ]);

        $tenantId = $currentTenant->id();
        $nextSortOrder = ((int) PricingProfile::query()->where('tenant_id', $tenantId)->max('sort_order')) + 1;

        $profile = DB::transaction(function () use ($data, $tenantId, $nextSortOrder) {
            if ($data['is_default']) {
                PricingProfile::query()->where('tenant_id', $tenantId)->update(['is_default' => false]);
            }

            return PricingProfile::query()->create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'slug' => filled($data['slug'] ?? null) ? Str::slug($data['slug']) : Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'is_active' => (bool) $data['is_active'],
                'is_default' => (bool) $data['is_default'],
                'grace_minutes' => (int) $data['grace_minutes'],
                'extra_block_minutes' => (int) $data['extra_block_minutes'],
                'sort_order' => $nextSortOrder,
            ]);
        });

        return response()->json($this->mapProfile($profile->load('rules')), 201);
    }

    public function updateProfile(Request $request, CurrentTenant $currentTenant, PricingProfile $profile): JsonResponse
    {
        $this->ensureTenantOwnership($currentTenant, $profile->tenant_id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'extra_block_minutes' => ['required', 'integer', 'min:1', 'max:240'],
        ]);

        DB::transaction(function () use ($data, $currentTenant, $profile) {
            if ($data['is_default']) {
                PricingProfile::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('id', '!=', $profile->id)
                    ->update(['is_default' => false]);
            }

            $profile->update([
                'name' => $data['name'],
                'slug' => filled($data['slug'] ?? null) ? Str::slug($data['slug']) : Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'is_active' => (bool) $data['is_active'],
                'is_default' => (bool) $data['is_default'],
                'grace_minutes' => (int) $data['grace_minutes'],
                'extra_block_minutes' => (int) $data['extra_block_minutes'],
            ]);
        });

        return response()->json($this->mapProfile($profile->fresh()->load('rules')));
    }

    public function deleteProfile(CurrentTenant $currentTenant, PricingProfile $profile): JsonResponse
    {
        $this->ensureTenantOwnership($currentTenant, $profile->tenant_id);
        $profile->delete();

        return response()->json(['success' => true]);
    }

    public function reorderProfiles(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $items = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
        ])['items'];

        DB::transaction(function () use ($items, $currentTenant) {
            foreach ($items as $index => $item) {
                PricingProfile::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('id', $item['id'])
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json(['success' => true]);
    }

    public function storeRule(Request $request, CurrentTenant $currentTenant, PricingProfile $profile): JsonResponse
    {
        $this->ensureTenantOwnership($currentTenant, $profile->tenant_id);
        $payload = $this->validateRulePayload($request, $currentTenant);

        $nextSortOrder = ((int) PricingRule::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('pricing_profile_id', $profile->id)
            ->max('sort_order')) + 1;

        $rule = PricingRule::query()->create([
            'tenant_id' => $currentTenant->id(),
            'pricing_profile_id' => $profile->id,
            'type' => $payload['type'],
            'name' => $payload['name'],
            'description' => $payload['description'],
            'conditions' => $payload['conditions'],
            'actions' => $payload['actions'],
            'is_active' => $payload['is_active'],
            'sort_order' => $nextSortOrder,
        ]);

        return response()->json($this->mapRule($rule), 201);
    }

    public function updateRule(Request $request, CurrentTenant $currentTenant, PricingRule $rule): JsonResponse
    {
        $this->ensureTenantOwnership($currentTenant, $rule->tenant_id);
        $payload = $this->validateRulePayload($request, $currentTenant);

        $rule->update([
            'type' => $payload['type'],
            'name' => $payload['name'],
            'description' => $payload['description'],
            'conditions' => $payload['conditions'],
            'actions' => $payload['actions'],
            'is_active' => $payload['is_active'],
        ]);

        return response()->json($this->mapRule($rule->fresh()));
    }

    public function deleteRule(CurrentTenant $currentTenant, PricingRule $rule): JsonResponse
    {
        $this->ensureTenantOwnership($currentTenant, $rule->tenant_id);
        $rule->delete();

        return response()->json(['success' => true]);
    }

    public function reorderRules(Request $request, CurrentTenant $currentTenant, PricingProfile $profile): JsonResponse
    {
        $this->ensureTenantOwnership($currentTenant, $profile->tenant_id);

        $items = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
        ])['items'];

        DB::transaction(function () use ($items, $currentTenant, $profile) {
            foreach ($items as $index => $item) {
                PricingRule::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('pricing_profile_id', $profile->id)
                    ->where('id', $item['id'])
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json(['success' => true]);
    }

    private function validateRulePayload(Request $request, CurrentTenant $currentTenant): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:duration,catering'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'conditions' => ['nullable', 'array'],
            'actions' => ['nullable', 'array'],
        ]);

        $conditions = $data['conditions'] ?? [];
        $actions = $data['actions'] ?? [];

        if ($data['type'] === PricingRule::TYPE_DURATION) {
            validator([
                'participant_scope' => $conditions['participant_scope'] ?? null,
                'from_minutes' => $conditions['from_minutes'] ?? null,
                'until_minutes' => $conditions['until_minutes'] ?? null,
                'billing_mode' => $actions['billing_mode'] ?? null,
                'product_id' => $actions['product_id'] ?? null,
                'extra_product_id' => $actions['extra_product_id'] ?? null,
                'extra_threshold_minutes' => $actions['extra_threshold_minutes'] ?? null,
            ], [
                'participant_scope' => ['required', 'in:children,adults,all'],
                'from_minutes' => ['required', 'integer', 'min:0'],
                'until_minutes' => ['nullable', 'integer', 'min:1'],
                'billing_mode' => ['required', 'in:fixed_product,product_plus_extra,next_rule'],
                'product_id' => ['nullable', 'integer'],
                'extra_product_id' => ['nullable', 'integer'],
                'extra_threshold_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            ])->validate();

            $this->ensureProductBelongsToTenant($currentTenant, $actions['product_id'] ?? null);
            $this->ensureProductBelongsToTenant($currentTenant, $actions['extra_product_id'] ?? null);
        }

        if ($data['type'] === PricingRule::TYPE_CATERING) {
            validator([
                'catering_option_id' => $conditions['catering_option_id'] ?? null,
                'participant_scope' => $conditions['participant_scope'] ?? null,
                'product_id' => $actions['product_id'] ?? null,
                'quantity_per_person' => $actions['quantity_per_person'] ?? null,
            ], [
                'catering_option_id' => ['required', 'integer'],
                'participant_scope' => ['required', 'in:children,adults,all'],
                'product_id' => ['required', 'integer'],
                'quantity_per_person' => ['required', 'numeric', 'min:0.01', 'max:999'],
            ])->validate();

            $this->ensureCateringOptionBelongsToTenant($currentTenant, $conditions['catering_option_id'] ?? null);
            $this->ensureProductBelongsToTenant($currentTenant, $actions['product_id'] ?? null);
        }

        return [
            'type' => $data['type'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'conditions' => $conditions,
            'actions' => $actions,
            'is_active' => (bool) $data['is_active'],
        ];
    }

    private function ensureProductBelongsToTenant(CurrentTenant $currentTenant, ?int $productId): void
    {
        if ($productId === null) {
            return;
        }

        Product::query()
            ->where('tenant_id', $currentTenant->id())
            ->findOrFail($productId);
    }

    private function ensureCateringOptionBelongsToTenant(CurrentTenant $currentTenant, ?int $optionId): void
    {
        if ($optionId === null) {
            abort(422, 'Ongeldige cateringoptie geselecteerd.');
        }

        CateringOption::query()
            ->where('is_active', true)
            ->findOrFail($optionId);
    }

    private function ensureTenantOwnership(CurrentTenant $currentTenant, int $tenantId): void
    {
        abort_unless((int) $tenantId === (int) $currentTenant->id(), 404);
    }

    private function mapProfile(PricingProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'slug' => $profile->slug,
            'description' => $profile->description,
            'is_active' => (bool) $profile->is_active,
            'is_default' => (bool) $profile->is_default,
            'grace_minutes' => (int) $profile->grace_minutes,
            'extra_block_minutes' => (int) $profile->extra_block_minutes,
            'sort_order' => (int) $profile->sort_order,
            'rules' => $profile->rules->map(fn (PricingRule $rule) => $this->mapRule($rule))->values(),
        ];
    }

    private function mapRule(PricingRule $rule): array
    {
        return [
            'id' => $rule->id,
            'pricing_profile_id' => $rule->pricing_profile_id,
            'type' => $rule->type,
            'name' => $rule->name,
            'description' => $rule->description,
            'conditions' => $rule->conditions ?? [],
            'actions' => $rule->actions ?? [],
            'is_active' => (bool) $rule->is_active,
            'sort_order' => (int) $rule->sort_order,
        ];
    }
}
