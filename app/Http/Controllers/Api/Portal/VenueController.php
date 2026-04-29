<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantActivity;
use App\Models\TenantAmenity;
use App\Models\TenantLink;
use App\Models\TenantPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portal — venuepagina beheer.
 *
 * Alle endpoints werken op de tenant van de ingelogde portal-user.
 * De middleware RequirePortalAuth heeft de tenant al gevalideerd en in
 * request->attributes 'portal_tenant' gezet.
 */
class VenueController extends Controller
{
    // ==================================================================
    // INFO — naam, tagline, adres, contact, doelgroep
    // ==================================================================

    public function getInfo(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        return response()->json([
            'name' => $tenant->name,
            'company_name' => $tenant->company_name,
            'tagline' => $tenant->tagline,
            'public_description' => $tenant->public_description,
            'street' => $tenant->street,
            'number' => $tenant->number,
            'postal_code' => $tenant->postal_code,
            'city' => $tenant->city,
            'country' => $tenant->country,
            'latitude' => $tenant->latitude,
            'longitude' => $tenant->longitude,
            'phone' => $tenant->phone,
            'email' => $tenant->email,
            'website_url' => $tenant->website_url,
            'video_url' => $tenant->video_url,
            'target_audiences' => $tenant->target_audiences ?? [],
        ]);
    }

    public function updateInfo(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        $availableAudiences = array_keys(config('venue_audiences', []));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'public_description' => ['nullable', 'string', 'max:5000'],
            'street' => ['nullable', 'string', 'max:160'],
            'number' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:80'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'video_url' => ['nullable', 'url', 'max:255'],
            'target_audiences' => ['nullable', 'array'],
            'target_audiences.*' => ['string', Rule::in($availableAudiences)],
        ]);

        $tenant->fill($data);
        $tenant->save();

        return $this->getInfo($request);
    }

    // ==================================================================
    // MEDIA — logo, hero, foto's, video
    // ==================================================================

    public function getMedia(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        return response()->json([
            'logo_url' => $tenant->logo_url,
            'hero_image_url' => $tenant->hero_image_url,
            'video_url' => $tenant->video_url,
            'photos' => $tenant->photos()->orderBy('sort_order')->get()->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => $photo->url,
                'alt_text' => $photo->alt_text,
                'sort_order' => $photo->sort_order,
            ])->all(),
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'max:4096'],
        ]);

        $tenant = $this->tenant($request);
        $path = $request->file('file')->store("tenants/{$tenant->id}/logo", 'public');

        // Vorige verwijderen
        if ($tenant->logo_path && $tenant->logo_path !== $path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($tenant->logo_path);
        }

        $tenant->logo_path = $path;
        $tenant->save();

        return response()->json([
            'logo_url' => $tenant->fresh()->logo_url,
        ]);
    }

    public function uploadHero(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'max:8192'],
        ]);

        $tenant = $this->tenant($request);
        $path = $request->file('file')->store("tenants/{$tenant->id}/hero", 'public');

        if ($tenant->hero_image_path && $tenant->hero_image_path !== $path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($tenant->hero_image_path);
        }

        $tenant->hero_image_path = $path;
        $tenant->save();

        return response()->json([
            'hero_image_url' => $tenant->fresh()->hero_image_url,
        ]);
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'max:8192'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $tenant = $this->tenant($request);
        $path = $request->file('file')->store("tenants/{$tenant->id}/photos", 'public');

        $maxOrder = $tenant->photos()->max('sort_order') ?? 0;

        $photo = TenantPhoto::create([
            'tenant_id' => $tenant->id,
            'path' => $path,
            'alt_text' => $request->input('alt_text'),
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json([
            'id' => $photo->id,
            'url' => $photo->url,
            'alt_text' => $photo->alt_text,
            'sort_order' => $photo->sort_order,
        ], Response::HTTP_CREATED);
    }

    public function deletePhoto(Request $request, int $photoId): JsonResponse
    {
        $tenant = $this->tenant($request);

        $photo = TenantPhoto::where('tenant_id', $tenant->id)->find($photoId);

        if (! $photo) {
            return response()->json(['message' => 'Foto niet gevonden.'], 404);
        }

        if ($photo->path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($photo->path);
        }

        $photo->delete();

        return response()->json(['success' => true]);
    }

    public function reorderPhotos(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $tenant = $this->tenant($request);
        $tenantPhotoIds = $tenant->photos()->pluck('id')->all();

        // Alleen ID's die bij deze tenant horen — beveiliging
        $validIds = array_values(array_intersect($data['order'], $tenantPhotoIds));

        foreach ($validIds as $index => $id) {
            TenantPhoto::where('tenant_id', $tenant->id)
                ->where('id', $id)
                ->update(['sort_order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    public function deleteLogo(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        if ($tenant->logo_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($tenant->logo_path);
            $tenant->logo_path = null;
            $tenant->save();
        }

        return response()->json(['success' => true]);
    }

    public function deleteHero(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        if ($tenant->hero_image_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($tenant->hero_image_path);
            $tenant->hero_image_path = null;
            $tenant->save();
        }

        return response()->json(['success' => true]);
    }

    // ==================================================================
    // ACTIVITIES
    // ==================================================================

    public function getActivities(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        return response()->json([
            'activities' => $tenant->activities()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($activity) => [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'description' => $activity->description,
                    'icon_key' => $activity->icon_key,
                    'sort_order' => $activity->sort_order,
                    'is_visible' => (bool) $activity->is_visible,
                ])
                ->all(),
        ]);
    }

    public function createActivity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon_key' => ['nullable', 'string', 'max:80'],
            'is_visible' => ['nullable', 'boolean'],
        ]);

        $tenant = $this->tenant($request);
        $maxOrder = $tenant->activities()->max('sort_order') ?? 0;

        $activity = TenantActivity::create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'icon_key' => $data['icon_key'] ?? null,
            'is_visible' => $data['is_visible'] ?? true,
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json([
            'id' => $activity->id,
            'name' => $activity->name,
            'description' => $activity->description,
            'icon_key' => $activity->icon_key,
            'sort_order' => $activity->sort_order,
            'is_visible' => (bool) $activity->is_visible,
        ], Response::HTTP_CREATED);
    }

    public function updateActivity(Request $request, int $activityId): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon_key' => ['nullable', 'string', 'max:80'],
            'is_visible' => ['nullable', 'boolean'],
        ]);

        $tenant = $this->tenant($request);
        $activity = TenantActivity::where('tenant_id', $tenant->id)->find($activityId);

        if (! $activity) {
            return response()->json(['message' => 'Activiteit niet gevonden.'], 404);
        }

        $activity->fill($data);
        $activity->save();

        return response()->json([
            'id' => $activity->id,
            'name' => $activity->name,
            'description' => $activity->description,
            'icon_key' => $activity->icon_key,
            'sort_order' => $activity->sort_order,
            'is_visible' => (bool) $activity->is_visible,
        ]);
    }

    public function deleteActivity(Request $request, int $activityId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $activity = TenantActivity::where('tenant_id', $tenant->id)->find($activityId);

        if (! $activity) {
            return response()->json(['message' => 'Activiteit niet gevonden.'], 404);
        }

        $activity->delete();

        return response()->json(['success' => true]);
    }

    public function reorderActivities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $tenant = $this->tenant($request);
        $tenantActivityIds = $tenant->activities()->pluck('id')->all();
        $validIds = array_values(array_intersect($data['order'], $tenantActivityIds));

        foreach ($validIds as $index => $id) {
            TenantActivity::where('tenant_id', $tenant->id)
                ->where('id', $id)
                ->update(['sort_order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    // ==================================================================
    // AMENITIES — vooraf gedefinieerde keys
    // ==================================================================

    public function getAmenities(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $available = config('venue_amenities', []);

        $current = $tenant->amenities()
            ->get()
            ->keyBy('key');

        $result = [];
        foreach ($available as $key => $meta) {
            $existing = $current->get($key);
            $result[] = [
                'key' => $key,
                'label' => $meta['label'] ?? $key,
                'is_available' => $existing ? (bool) $existing->is_available : false,
                'value' => $existing?->value,
            ];
        }

        return response()->json(['amenities' => $result]);
    }

    public function updateAmenities(Request $request): JsonResponse
    {
        $availableKeys = array_keys(config('venue_amenities', []));

        $data = $request->validate([
            'amenities' => ['required', 'array'],
            'amenities.*.key' => ['required', 'string', Rule::in($availableKeys)],
            'amenities.*.is_available' => ['required', 'boolean'],
            'amenities.*.value' => ['nullable', 'string', 'max:255'],
        ]);

        $tenant = $this->tenant($request);

        foreach ($data['amenities'] as $item) {
            TenantAmenity::updateOrCreate(
                ['tenant_id' => $tenant->id, 'key' => $item['key']],
                [
                    'is_available' => $item['is_available'],
                    'value' => $item['value'] ?? null,
                ]
            );
        }

        return $this->getAmenities($request);
    }

    // ==================================================================
    // LINKS — externe URL's (social media etc)
    // ==================================================================

    public function getLinks(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        return response()->json([
            'links' => $tenant->links()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($link) => [
                    'id' => $link->id,
                    'type' => $link->type,
                    'url' => $link->url,
                    'sort_order' => $link->sort_order,
                ])
                ->all(),
        ]);
    }

    public function createLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in([
                'facebook', 'instagram', 'tiktok', 'youtube', 'twitter', 'linkedin', 'website', 'other',
            ])],
            'url' => ['required', 'url', 'max:255'],
        ]);

        $tenant = $this->tenant($request);
        $maxOrder = $tenant->links()->max('sort_order') ?? 0;

        $link = TenantLink::create([
            'tenant_id' => $tenant->id,
            'type' => $data['type'],
            'url' => $data['url'],
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json([
            'id' => $link->id,
            'type' => $link->type,
            'url' => $link->url,
            'sort_order' => $link->sort_order,
        ], Response::HTTP_CREATED);
    }

    public function updateLink(Request $request, int $linkId): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in([
                'facebook', 'instagram', 'tiktok', 'youtube', 'twitter', 'linkedin', 'website', 'other',
            ])],
            'url' => ['required', 'url', 'max:255'],
        ]);

        $tenant = $this->tenant($request);
        $link = TenantLink::where('tenant_id', $tenant->id)->find($linkId);

        if (! $link) {
            return response()->json(['message' => 'Link niet gevonden.'], 404);
        }

        $link->fill($data);
        $link->save();

        return response()->json([
            'id' => $link->id,
            'type' => $link->type,
            'url' => $link->url,
            'sort_order' => $link->sort_order,
        ]);
    }

    public function deleteLink(Request $request, int $linkId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $link = TenantLink::where('tenant_id', $tenant->id)->find($linkId);

        if (! $link) {
            return response()->json(['message' => 'Link niet gevonden.'], 404);
        }

        $link->delete();

        return response()->json(['success' => true]);
    }

    // ==================================================================
    // PUBLICATION — slug, status, publish/unpublish
    // ==================================================================

    public function getPublication(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        return response()->json([
            'public_status' => $tenant->public_status,
            'public_slug' => $tenant->public_slug,
            'published_at' => $tenant->published_at,
            'subscription_tier' => $tenant->subscription_tier,
            'public_url' => $tenant->public_slug
                ? url("/venues/{$tenant->public_slug}")
                : null,
            'requirements' => $this->checkRequirements($tenant),
        ]);
    }

    public function updateSlug(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        $data = $request->validate([
            'public_slug' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('tenants', 'public_slug')->ignore($tenant->id),
            ],
        ]);

        $tenant->public_slug = $data['public_slug'];
        $tenant->save();

        return $this->getPublication($request);
    }

    public function publish(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $req = $this->checkRequirements($tenant);

        if (! $req['ready']) {
            return response()->json([
                'message' => 'De pagina kan nog niet gepubliceerd worden.',
                'requirements' => $req,
            ], 422);
        }

        $tenant->public_status = 'live';
        if (! $tenant->published_at) {
            $tenant->published_at = now();
        }
        $tenant->save();

        return $this->getPublication($request);
    }

    public function unpublish(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $tenant->public_status = 'draft';
        $tenant->save();

        return $this->getPublication($request);
    }

    /**
     * Minimum-content vereisten om te publiceren.
     * Bewust low-key: naam, slug, beschrijving en minstens één foto of logo.
     */
    private function checkRequirements(Tenant $tenant): array
    {
        $hasName = (bool) trim((string) $tenant->name);
        $hasSlug = (bool) $tenant->public_slug;
        $hasDescription = (bool) trim((string) $tenant->public_description);
        $hasMedia = (bool) $tenant->logo_path
            || (bool) $tenant->hero_image_path
            || $tenant->photos()->exists();

        return [
            'ready' => $hasName && $hasSlug && $hasDescription && $hasMedia,
            'checks' => [
                'has_name' => $hasName,
                'has_slug' => $hasSlug,
                'has_description' => $hasDescription,
                'has_media' => $hasMedia,
            ],
        ];
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    private function tenant(Request $request): Tenant
    {
        return $request->attributes->get('portal_tenant');
    }
}
