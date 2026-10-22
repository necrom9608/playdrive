<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Domain\MailAssistant\MailboxFetcher;
use App\Domain\MailAssistant\MailCategories;
use App\Domain\MailAssistant\ReplyDrafter;
use App\Domain\MailAssistant\ReplySender;
use App\Http\Controllers\Controller;
use App\Models\MailAssistantFaq;
use App\Models\MailAssistantMessage;
use App\Models\MailAssistantSetting;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Backoffice: mailassistent — inbox met concept-antwoorden, instellingen en kennisbank.
 */
class MailAssistantController extends Controller
{
    private const TABS = [
        'review' => [MailAssistantMessage::STATUS_DRAFT, MailAssistantMessage::STATUS_NEW, MailAssistantMessage::STATUS_ERROR],
        'manual' => [MailAssistantMessage::STATUS_MANUAL],
        'sent' => [MailAssistantMessage::STATUS_SENT, MailAssistantMessage::STATUS_DONE],
        'ignored' => [MailAssistantMessage::STATUS_IGNORED],
    ];

    // ------------------------------------------------------------------
    // Inbox
    // ------------------------------------------------------------------

    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden.');
        $tenantId = $currentTenant->id();

        $tab = $request->query('tab', 'review');
        $statuses = self::TABS[$tab] ?? self::TABS['review'];
        $search = trim((string) $request->query('search', ''));

        $messages = MailAssistantMessage::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', $statuses)
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('from_email', 'like', "%{$search}%")
                ->orWhere('from_name', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")))
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (MailAssistantMessage $m) => $this->listItem($m))
            ->values();

        $settings = MailAssistantSetting::forTenant($tenantId);

        return response()->json([
            'data' => [
                'messages' => $messages,
                'stats' => $this->stats($tenantId),
                'categories' => MailCategories::forFrontend(),
                'mailbox' => [
                    'enabled' => (bool) $settings->enabled,
                    'configured' => $settings->isMailboxConfigured(),
                    'address' => $settings->senderAddress(),
                    'last_checked_at' => $settings->last_checked_at?->toIso8601String(),
                    'last_error' => $settings->last_error,
                ],
            ],
        ]);
    }

    public function show(MailAssistantMessage $message, CurrentTenant $currentTenant): JsonResponse
    {
        $this->authorizeMessage($message, $currentTenant);

        return response()->json(['data' => $this->detail($message, $currentTenant->id())]);
    }

    public function updateDraft(Request $request, MailAssistantMessage $message, CurrentTenant $currentTenant): JsonResponse
    {
        $this->authorizeMessage($message, $currentTenant);

        $data = $request->validate(['draft_body' => ['required', 'string', 'max:20000']]);
        $message->update(['draft_body' => $data['draft_body']]);

        return response()->json([
            'message' => 'Concept bewaard.',
            'data' => $this->detail($message, $currentTenant->id()),
        ]);
    }

    public function send(Request $request, MailAssistantMessage $message, CurrentTenant $currentTenant, ReplySender $sender): JsonResponse
    {
        $this->authorizeMessage($message, $currentTenant);
        abort_if($message->status === MailAssistantMessage::STATUS_SENT, 422, 'Deze mail is al beantwoord.');

        $data = $request->validate(['body' => ['required', 'string', 'max:20000']]);
        $settings = MailAssistantSetting::forTenant($currentTenant->id());
        $user = $request->attributes->get('backoffice_user');

        try {
            $sender->send($currentTenant->tenant, $settings, $message, $data['body'], $user?->id);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Versturen mislukt: ' . $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Antwoord verstuurd naar ' . $message->replyAddress() . '.',
            'data' => $this->detail($message->refresh(), $currentTenant->id()),
            'stats' => $this->stats($currentTenant->id()),
        ]);
    }

    public function regenerate(MailAssistantMessage $message, CurrentTenant $currentTenant, ReplyDrafter $drafter): JsonResponse
    {
        $this->authorizeMessage($message, $currentTenant);
        abort_if($message->status === MailAssistantMessage::STATUS_SENT, 422, 'Deze mail is al beantwoord.');

        $settings = MailAssistantSetting::forTenant($currentTenant->id());

        try {
            $drafter->draft($currentTenant->tenant, $settings, $message);
        } catch (Throwable $e) {
            $message->forceFill(['status' => MailAssistantMessage::STATUS_ERROR, 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();

            return response()->json(['message' => 'Opstellen mislukt: ' . $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Nieuw voorstel opgesteld.',
            'data' => $this->detail($message->refresh(), $currentTenant->id()),
            'stats' => $this->stats($currentTenant->id()),
        ]);
    }

    /** Status manueel wijzigen: afgehandeld, naar medewerker, negeren of terug te beoordelen. */
    public function updateStatus(Request $request, MailAssistantMessage $message, CurrentTenant $currentTenant): JsonResponse
    {
        $this->authorizeMessage($message, $currentTenant);
        abort_if($message->status === MailAssistantMessage::STATUS_SENT, 422, 'Deze mail is al beantwoord.');

        $data = $request->validate(['status' => ['required', Rule::in(['done', 'manual', 'ignored', 'review'])]]);
        $user = $request->attributes->get('backoffice_user');

        $status = match ($data['status']) {
            'review' => $message->draft_body ? MailAssistantMessage::STATUS_DRAFT : MailAssistantMessage::STATUS_NEW,
            default => $data['status'],
        };

        $message->forceFill([
            'status' => $status,
            'ignore_reason' => $status === MailAssistantMessage::STATUS_IGNORED ? 'Manueel genegeerd' : $message->ignore_reason,
            'handled_by' => in_array($status, ['done', 'ignored'], true) ? $user?->id : null,
            'handled_at' => in_array($status, ['done', 'ignored'], true) ? now() : null,
        ])->save();

        return response()->json([
            'data' => $this->detail($message, $currentTenant->id()),
            'stats' => $this->stats($currentTenant->id()),
        ]);
    }

    /** Nu meteen de mailbox controleren. */
    public function check(CurrentTenant $currentTenant, MailboxFetcher $fetcher): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden.');
        $settings = MailAssistantSetting::forTenant($currentTenant->id());

        abort_unless($settings->exists && $settings->isMailboxConfigured(), 422, 'Stel eerst de mailbox in (tab Instellingen).');

        try {
            $stats = $fetcher->run($currentTenant->tenant, $settings);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Mailbox controleren mislukt: ' . $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $stats['fetched'] === 0
                ? 'Geen nieuwe mails.'
                : "{$stats['fetched']} nieuwe mail(s), {$stats['drafted']} concept(en) opgesteld.",
            'stats' => $this->stats($currentTenant->id()),
        ]);
    }

    // ------------------------------------------------------------------
    // Instellingen
    // ------------------------------------------------------------------

    public function settings(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden.');

        return response()->json(['data' => $this->settingsPayload($currentTenant->id())]);
    }

    public function updateSettings(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden.');

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'imap_host' => ['nullable', 'string', 'max:255'],
            'imap_port' => ['required', 'integer', 'between:1,65535'],
            'imap_encryption' => ['required', Rule::in(MailAssistantSetting::ENCRYPTIONS)],
            'imap_username' => ['nullable', 'string', 'max:255'],
            'imap_password' => ['nullable', 'string', 'max:500'],
            'imap_folder' => ['nullable', 'string', 'max:255'],
            'sent_folder' => ['nullable', 'string', 'max:255'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:500'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'tone' => ['required', Rule::in(MailAssistantSetting::TONES)],
            'language' => ['required', Rule::in(MailAssistantSetting::LANGUAGES)],
            'signature' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'sources' => ['nullable', 'array'],
            'sources.*' => ['boolean'],
            'auto_suggest_after' => ['required', 'integer', 'between:1,50'],
            'fetch_since_days' => ['required', 'integer', 'between:0,30'],
        ]);

        $settings = MailAssistantSetting::forTenant($currentTenant->id());
        $mailboxChanged = $settings->imap_host !== ($data['imap_host'] ?? null)
            || $settings->imap_username !== ($data['imap_username'] ?? null)
            || $settings->imap_folder !== (($data['imap_folder'] ?? null) ?: 'INBOX');

        $settings->fill([
            'enabled' => (bool) $data['enabled'],
            'imap_host' => $data['imap_host'] ?? null,
            'imap_port' => (int) $data['imap_port'],
            'imap_encryption' => $data['imap_encryption'],
            'imap_username' => $data['imap_username'] ?? null,
            'imap_folder' => ($data['imap_folder'] ?? '') ?: 'INBOX',
            'sent_folder' => $data['sent_folder'] ?? null,
            'smtp_host' => $data['smtp_host'] ?? null,
            'smtp_port' => (int) $data['smtp_port'],
            'smtp_username' => $data['smtp_username'] ?? null,
            'from_address' => $data['from_address'] ?? null,
            'from_name' => $data['from_name'] ?? null,
            'tone' => $data['tone'],
            'language' => $data['language'],
            'signature' => $data['signature'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'sources' => array_intersect_key(
                array_merge(MailAssistantSetting::DEFAULT_SOURCES, $data['sources'] ?? []),
                MailAssistantSetting::DEFAULT_SOURCES
            ),
            'auto_suggest_after' => (int) $data['auto_suggest_after'],
            'fetch_since_days' => (int) $data['fetch_since_days'],
        ]);

        // Wachtwoorden enkel overschrijven als er een nieuw ingevuld is
        if (filled($data['imap_password'] ?? null)) {
            $settings->imap_password = $data['imap_password'];
        }
        if (filled($data['smtp_password'] ?? null)) {
            $settings->smtp_password = $data['smtp_password'];
        }

        // Andere mailbox of map: opnieuw beginnen vanaf "fetch_since_days" geleden
        if ($mailboxChanged) {
            $settings->last_uid = null;
            $settings->uid_validity = null;
        }

        if ($settings->enabled && ! $settings->isMailboxConfigured()) {
            return response()->json(['message' => 'Vul host, gebruikersnaam en wachtwoord van de mailbox in om de assistent aan te zetten.'], 422);
        }

        $settings->save();

        return response()->json([
            'message' => 'Instellingen opgeslagen.',
            'data' => $this->settingsPayload($currentTenant->id()),
        ]);
    }

    /** Test IMAP (aanmelden + mappen) en SMTP (aanmelden) met de opgeslagen instellingen. */
    public function testConnection(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden.');
        $settings = MailAssistantSetting::forTenant($currentTenant->id());

        $result = ['imap' => ['ok' => false], 'smtp' => ['ok' => false]];

        try {
            $client = MailboxFetcher::client($settings);
            $client->select($settings->imap_folder ?: 'INBOX');
            $sent = $settings->sent_folder ?: $client->findSentFolder();
            $client->logout();

            $result['imap'] = [
                'ok' => true,
                'message' => 'Verbonden met ' . $settings->imap_host . ($sent ? " · Verzonden-map: {$sent}" : ' · geen Verzonden-map gevonden'),
            ];
        } catch (Throwable $e) {
            $result['imap'] = ['ok' => false, 'message' => $e->getMessage()];
        }

        try {
            $transport = ReplySender::transport($settings);
            $transport->start();
            $transport->stop();
            $result['smtp'] = ['ok' => true, 'message' => 'Verbonden met ' . $settings->smtp_host . ':' . $settings->smtp_port];
        } catch (Throwable $e) {
            $result['smtp'] = ['ok' => false, 'message' => $e->getMessage()];
        }

        return response()->json(['data' => $result]);
    }

    // ------------------------------------------------------------------
    // Kennisbank
    // ------------------------------------------------------------------

    public function faqs(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden.');

        return response()->json(['data' => $this->faqList($currentTenant->id())]);
    }

    public function storeFaq(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden.');
        $data = $this->validateFaq($request);

        MailAssistantFaq::query()->create($data + [
            'tenant_id' => $currentTenant->id(),
            'sort_order' => (int) MailAssistantFaq::query()->where('tenant_id', $currentTenant->id())->max('sort_order') + 1,
        ]);

        return response()->json(['message' => 'Toegevoegd.', 'data' => $this->faqList($currentTenant->id())], 201);
    }

    public function updateFaq(Request $request, MailAssistantFaq $faq, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists() && $faq->tenant_id === $currentTenant->id(), 404);
        $faq->update($this->validateFaq($request));

        return response()->json(['message' => 'Bewaard.', 'data' => $this->faqList($currentTenant->id())]);
    }

    public function destroyFaq(MailAssistantFaq $faq, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists() && $faq->tenant_id === $currentTenant->id(), 404);
        $faq->delete();

        return response()->json(['message' => 'Verwijderd.', 'data' => $this->faqList($currentTenant->id())]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function authorizeMessage(MailAssistantMessage $message, CurrentTenant $currentTenant): void
    {
        abort_unless($currentTenant->exists() && $message->tenant_id === $currentTenant->id(), 404);
    }

    private function stats(int $tenantId): array
    {
        $counts = MailAssistantMessage::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sum = fn (array $statuses) => (int) collect($statuses)->sum(fn ($s) => (int) ($counts[$s] ?? 0));

        return [
            'review' => $sum(self::TABS['review']),
            'manual' => $sum(self::TABS['manual']),
            'sent' => $sum(self::TABS['sent']),
            'ignored' => $sum(self::TABS['ignored']),
            'sent_today' => MailAssistantMessage::query()
                ->where('tenant_id', $tenantId)
                ->where('status', MailAssistantMessage::STATUS_SENT)
                ->where('sent_at', '>=', now()->startOfDay())
                ->count(),
        ];
    }

    /**
     * Hoeveel antwoorden in deze categorie na elkaar zonder aanpassing verstuurd werden
     * (basis voor het latere "automatisch versturen"-voorstel).
     */
    private function categoryProgress(int $tenantId, ?string $category): ?array
    {
        if (! $category) {
            return null;
        }

        $threshold = MailAssistantSetting::forTenant($tenantId)->auto_suggest_after ?: 5;

        $recent = MailAssistantMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('category', $category)
            ->where('status', MailAssistantMessage::STATUS_SENT)
            ->whereNotNull('sent_edited')
            ->orderByDesc('sent_at')
            ->limit($threshold)
            ->pluck('sent_edited');

        $streak = 0;
        foreach ($recent as $edited) {
            if ($edited) {
                break;
            }
            $streak++;
        }

        return [
            'key' => $category,
            'label' => MailCategories::label($category),
            'locked' => MailCategories::isLocked($category),
            'streak' => $streak,
            'threshold' => $threshold,
        ];
    }

    private function listItem(MailAssistantMessage $m): array
    {
        return [
            'id' => $m->id,
            'from_email' => $m->from_email,
            'from_name' => $m->from_name,
            'subject' => $m->subject,
            'summary' => $m->summary,
            'received_at' => $m->received_at?->toIso8601String(),
            'status' => $m->status,
            'category' => $m->category,
            'category_label' => MailCategories::label($m->category),
            'confidence' => $m->confidence,
            'needs_human' => $m->needs_human,
            'ignore_reason' => $m->ignore_reason,
        ];
    }

    private function detail(MailAssistantMessage $m, int $tenantId): array
    {
        $m->loadMissing(['account', 'sender:id,name', 'handler:id,name']);

        $history = MailAssistantMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('from_email', $m->from_email)
            ->where('id', '!=', $m->id)
            ->latest('received_at')
            ->limit(5)
            ->get(['id', 'subject', 'status', 'received_at', 'sent_at'])
            ->map(fn ($h) => [
                'id' => $h->id,
                'subject' => $h->subject,
                'status' => $h->status,
                'received_at' => $h->received_at?->toIso8601String(),
            ]);

        return $this->listItem($m) + [
            'reply_to' => $m->replyAddress(),
            'body_text' => $m->body_text,
            'language' => $m->language,
            'needs_human_reason' => $m->needs_human_reason,
            'draft_body' => $m->draft_body,
            'draft_edited' => $m->draft_body !== null && $m->draft_original !== null
                && trim($m->draft_body) !== trim($m->draft_original),
            'draft_sources' => $m->draft_sources ?? [],
            'draft_warnings' => $m->draft_warnings ?? [],
            'drafted_at' => $m->drafted_at?->toIso8601String(),
            'account' => $m->account ? ['id' => $m->account->id, 'name' => $m->account->full_name] : null,
            'sent_body' => $m->sent_body,
            'sent_at' => $m->sent_at?->toIso8601String(),
            'sent_by' => $m->sender?->name,
            'handled_by' => $m->handler?->name,
            'handled_at' => $m->handled_at?->toIso8601String(),
            'error' => $m->error,
            'progress' => $this->categoryProgress($tenantId, $m->category),
            'history' => $history,
        ];
    }

    private function settingsPayload(int $tenantId): array
    {
        $s = MailAssistantSetting::forTenant($tenantId);

        return [
            'enabled' => (bool) $s->enabled,
            'imap_host' => $s->imap_host,
            'imap_port' => (int) $s->imap_port,
            'imap_encryption' => $s->imap_encryption,
            'imap_username' => $s->imap_username,
            'imap_password_set' => filled($s->imap_password),
            'imap_folder' => $s->imap_folder,
            'sent_folder' => $s->sent_folder,
            'smtp_host' => $s->smtp_host,
            'smtp_port' => (int) $s->smtp_port,
            'smtp_username' => $s->smtp_username,
            'smtp_password_set' => filled($s->smtp_password),
            'from_address' => $s->from_address,
            'from_name' => $s->from_name,
            'tone' => $s->tone,
            'language' => $s->language,
            'signature' => $s->signature,
            'instructions' => $s->instructions,
            'sources' => $s->sourceFlags(),
            'auto_suggest_after' => (int) $s->auto_suggest_after,
            'fetch_since_days' => (int) $s->fetch_since_days,
            'last_checked_at' => $s->last_checked_at?->toIso8601String(),
            'last_error' => $s->last_error,
            'last_error_at' => $s->last_error_at?->toIso8601String(),
            'api_configured' => filled(config('services.anthropic.key')),
            'categories' => $this->categoryOverview($tenantId, (int) ($s->auto_suggest_after ?: 5)),
        ];
    }

    private function categoryOverview(int $tenantId, int $threshold): array
    {
        $counts = MailAssistantMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('received_at', '>=', now()->subDays(30))
            ->whereNotNull('category')
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        return collect(MailCategories::forFrontend())
            ->map(fn (array $c) => $c + [
                'count_30d' => (int) ($counts[$c['key']] ?? 0),
                'progress' => $this->categoryProgress($tenantId, $c['key'])['streak'] ?? 0,
                'threshold' => $threshold,
            ])
            ->all();
    }

    private function faqList(int $tenantId): array
    {
        return MailAssistantFaq::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'question', 'answer', 'is_active'])
            ->toArray();
    }

    private function validateFaq(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:4000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'question' => $data['question'],
            'answer' => $data['answer'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
