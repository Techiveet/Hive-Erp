<?php

namespace Modules\MailBox\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\CommunicationEncryptionService;
use Modules\Identity\Models\User;
use Modules\MailBox\Events\MailboxSync;
use Modules\MailBox\Jobs\DispatchMailToParticipants;
use Modules\MailBox\Models\MailMessage;
use Modules\MailBox\Models\MailParticipant;
use Modules\MailBox\Services\MailboxStorageTracker;
use Modules\MailBox\Support\MailPayload;

class MailBoxController extends Controller
{
    public function config(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'encryption' => [
                'enabled' => app(CommunicationEncryptionService::class)->isEnabled(),
                'algorithm' => 'rsa-oaep-aes-gcm-v1',
                'public_key' => $user->chat_encryption_public_key,
                'fingerprint' => $user->chat_encryption_key_fingerprint,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $folder = $request->query('folder', 'inbox');
        $user = $request->user();

        $query = MailParticipant::with(['message.sender', 'message.participants.user'])
            ->where('user_id', $user->id);

        if ($folder === 'all') {
            $query->whereNotIn('folder', ['trash', 'spam']);
        } else {
            $query->where('folder', $folder);
        }

        $query->orderBy($folder === 'drafts' ? 'updated_at' : 'created_at', 'desc');

        $participants = $query->paginate(50);
        $participants->setCollection(
            $participants->getCollection()->map(
                fn (MailParticipant $participant) => MailPayload::participantForUser($participant, $user)
            )
        );

        return response()->json($participants);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $count = MailParticipant::where('user_id', $user->id)
            ->where('folder', 'inbox')
            ->where('is_read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function counts(Request $request)
    {
        $user = $request->user();

        $baseQuery = MailParticipant::where('user_id', $user->id);

        $counts = [
            'inbox' => (clone $baseQuery)->where('folder', 'inbox')->count(),
            'inbox_unread' => (clone $baseQuery)->where('folder', 'inbox')->where('is_read', false)->count(),
            'starred' => (clone $baseQuery)->where('is_starred', true)->count(),
            'sent' => (clone $baseQuery)->where('folder', 'sent')->count(),
            'drafts' => (clone $baseQuery)->where('folder', 'drafts')->count(),
            'archive' => (clone $baseQuery)->where('folder', 'archive')->count(),
            'trash' => (clone $baseQuery)->where('folder', 'trash')->count(),
            'spam' => (clone $baseQuery)->where('folder', 'spam')->count(),
            'important' => (clone $baseQuery)->where('folder', 'important')->count(),
            'storage_used' => MailboxStorageTracker::getUserStorageUsedBytes($user->id),
            'storage_limit' => MailboxStorageTracker::getUserQuotaLimitBytes(),
        ];

        if (function_exists('tenant') && tenant('id')) {
            $counts['tenant_storage_used'] = MailboxStorageTracker::getTenantTotalStorageUsedBytes();
            $counts['tenant_storage_limit'] = MailboxStorageTracker::getTenantGlobalQuotaLimitBytes();
        }

        return response()->json($counts);
    }

    public function store(Request $request)
    {
        $interceptAll = function ($arr) use ($request) {
            if (is_array($arr) && in_array('all', $arr, true)) {
                return User::where('is_active', true)
                    ->where('id', '!=', $request->user()->id)
                    ->pluck('id')
                    ->toArray();
            }

            return $arr;
        };

        if ($request->has('to')) {
            $request->merge(['to' => $interceptAll($request->to)]);
        }

        if ($request->has('cc')) {
            $request->merge(['cc' => $interceptAll($request->cc)]);
        }

        if ($request->has('bcc')) {
            $request->merge(['bcc' => $interceptAll($request->bcc)]);
        }

        $request->validate([
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'to' => 'nullable|array',
            'to.*' => 'exists:users,id',
            'cc' => 'nullable|array',
            'cc.*' => 'exists:users,id',
            'bcc' => 'nullable|array',
            'bcc.*' => 'exists:users,id',
            'save_as_draft' => 'sometimes|boolean',
            'draft_id' => 'nullable|integer',
            'participant_keys' => 'nullable|array',
            'participant_keys.*' => 'required|string|max:65535',
        ]);

        $user = $request->user();
        $tenantId = $this->resolveTenantId();
        $saveAsDraft = $request->boolean('save_as_draft');

        if (! $saveAsDraft && empty($request->to)) {
            return response()->json([
                'message' => 'At least one recipient is required.',
                'errors' => [
                    'to' => ['At least one recipient is required.'],
                ],
            ], 422);
        }

        if (! $saveAsDraft && blank($request->body)) {
            return response()->json([
                'message' => 'Message body is required.',
                'errors' => [
                    'body' => ['Message body is required.'],
                ],
            ], 422);
        }

        if (
            $saveAsDraft &&
            blank($request->subject) &&
            blank($request->body) &&
            empty($request->to) &&
            empty($request->cc) &&
            empty($request->bcc)
        ) {
            return response()->json([
                'message' => 'Add a recipient, subject, or message before saving a draft.',
            ], 422);
        }

        $participantKeyMap = collect($request->input('participant_keys', []))
            ->mapWithKeys(fn ($value, $key) => [(string) $key => $value]);

        $expectedParticipantIds = collect(array_merge(
            [$user->id],
            $request->to ?? [],
            $request->cc ?? [],
            $request->bcc ?? []
        ))
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values();

        if (! $saveAsDraft && $participantKeyMap->isNotEmpty() && ! app(CommunicationEncryptionService::class)->isEnabled()) {
            return response()->json([
                'message' => 'Communication encryption is disabled for this workspace.',
            ], 422);
        }

        if (! $saveAsDraft && $participantKeyMap->isNotEmpty()) {
            $missingParticipantIds = $expectedParticipantIds
                ->reject(fn (string $participantId) => $participantKeyMap->has($participantId))
                ->values();

            if ($missingParticipantIds->isNotEmpty()) {
                return response()->json([
                    'message' => 'Missing encryption material for one or more email participants.',
                ], 422);
            }
        }

        $incomingBytes = strlen($request->subject ?? '') + strlen($request->body ?? '');

        if (! MailboxStorageTracker::canAcceptMail($user->id, $incomingBytes)) {
            return response()->json([
                'error' => 'Mailbox Quota Exceeded. Please empty your trash to send more messages.',
            ], 422);
        }

        $existingDraftMessage = $request->filled('draft_id')
            ? $this->findOwnedDraftMessage((int) $user->id, (int) $request->integer('draft_id'))
            : null;

        if ($request->filled('draft_id') && ! $existingDraftMessage) {
            return response()->json([
                'message' => 'Draft not found.',
            ], 404);
        }

        if ($saveAsDraft) {
            return $this->saveDraft($request, $user, $tenantId, $existingDraftMessage);
        }

        DB::beginTransaction();

        try {
            $message = $existingDraftMessage ?? new MailMessage();
            $message->fill([
                'sender_id' => $user->id,
                'subject' => $request->subject,
                'body' => $request->body,
                'status' => 'sent',
                'draft_recipients' => null,
            ]);
            $message->save();

            MailParticipant::updateOrCreate(
                [
                    'mail_message_id' => $message->id,
                    'user_id' => $user->id,
                ],
                [
                    'type' => 'sender',
                    'folder' => 'sent',
                    'is_read' => true,
                    'encrypted_message_key' => $participantKeyMap->get((string) $user->id),
                    'message_key_algorithm' => $participantKeyMap->has((string) $user->id) ? 'rsa-oaep-aes-gcm-v1' : null,
                    'message_key_version' => $participantKeyMap->has((string) $user->id) ? 1 : null,
                ]
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['error' => 'Failed to send email: ' . $e->getMessage()], 500);
        }

        foreach ([
            ['user_ids' => $request->to ?? [], 'type' => 'to'],
            ['user_ids' => $request->cc ?? [], 'type' => 'cc'],
            ['user_ids' => $request->bcc ?? [], 'type' => 'bcc'],
        ] as $recipientBatch) {
            if (empty($recipientBatch['user_ids'])) {
                continue;
            }

            try {
                DispatchMailToParticipants::dispatch(
                    $message,
                    $recipientBatch['user_ids'],
                    $recipientBatch['type'],
                    $tenantId,
                    $participantKeyMap->all()
                );
            } catch (\Throwable $exception) {
                \Log::error('Failed to deliver mailbox recipients.', [
                    'mail_message_id' => $message->id,
                    'type' => $recipientBatch['type'],
                    'recipient_ids' => $recipientBatch['user_ids'],
                    'tenant_id' => $tenantId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $senderParticipant = MailParticipant::with(['message.sender', 'message.participants.user'])
            ->where('mail_message_id', $message->id)
            ->where('user_id', $user->id)
            ->first();

        $senderParticipantPayload = $senderParticipant
            ? MailPayload::participantForUser($senderParticipant, $user)
            : null;

        MailboxSync::dispatch(
            $user->id,
            'sent',
            [
                'message_id' => $message->id,
                'participantData' => $senderParticipantPayload,
                'previous_folder' => $existingDraftMessage ? 'drafts' : null,
            ],
            $tenantId
        );

        return response()->json(['message' => 'Email sent.', 'data' => $senderParticipantPayload], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $participant = MailParticipant::with(['message.sender', 'message.participants.user'])
            ->where('user_id', $user->id)
            ->where('mail_message_id', $id)
            ->firstOrFail();

        if (! $participant->is_read) {
            $participant->update(['is_read' => true]);

            MailboxSync::dispatch(
                $user->id,
                'update',
                ['message_id' => (int) $id, 'changes' => ['is_read' => true]],
                $this->resolveTenantId()
            );
        }

        return response()->json(MailPayload::participantForUser($participant, $user));
    }

    public function updatePublicKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'public_key' => 'required|string|max:65535',
            'algorithm' => 'required|string|max:255',
            'fingerprint' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        $user->forceFill([
            'chat_encryption_public_key' => $validated['public_key'],
            'chat_encryption_key_algorithm' => $validated['algorithm'],
            'chat_encryption_key_fingerprint' => $validated['fingerprint'] ?? null,
        ])->save();

        return response()->json([
            'message' => 'Mailbox encryption identity updated.',
            'data' => [
                'public_key' => $user->chat_encryption_public_key,
                'algorithm' => $user->chat_encryption_key_algorithm,
                'fingerprint' => $user->chat_encryption_key_fingerprint,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        $participant = MailParticipant::where('user_id', $user->id)
            ->where('mail_message_id', $id)
            ->firstOrFail();

        $updateData = [];

        if ($request->has('is_read')) {
            $updateData['is_read'] = $request->is_read;
        }

        if ($request->has('is_starred')) {
            $updateData['is_starred'] = $request->is_starred;
        }

        if ($request->has('folder')) {
            $updateData['folder'] = $request->folder;
        }

        $participant->update($updateData);

        MailboxSync::dispatch(
            $user->id,
            'update',
            ['message_id' => $id, 'changes' => $updateData],
            $this->resolveTenantId()
        );

        return response()->json(['message' => 'Updated successfully', 'data' => $participant]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $participant = MailParticipant::where('user_id', $user->id)
            ->where('mail_message_id', $id)
            ->firstOrFail();

        $wasTrashed = $participant->folder === 'trash';

        if ($wasTrashed) {
            $participant->delete();
        } else {
            $participant->update(['folder' => 'trash']);
        }

        MailboxSync::dispatch(
            $user->id,
            'delete',
            ['message_id' => $id, 'permanent' => $wasTrashed],
            $this->resolveTenantId()
        );

        return response()->json(['message' => 'Deleted successfully']);
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'action' => 'required|string|in:trash,delete,star,unstar,read,unread,archive,inbox,spam,important',
        ]);

        $user = $request->user();
        $ids = $request->ids;
        $action = $request->action;

        $query = MailParticipant::where('user_id', $user->id)->whereIn('mail_message_id', $ids);

        switch ($action) {
            case 'trash':
                $query->update(['folder' => 'trash']);
                break;
            case 'delete':
                $query->delete();
                break;
            case 'star':
                $query->update(['is_starred' => true]);
                break;
            case 'unstar':
                $query->update(['is_starred' => false]);
                break;
            case 'read':
                $query->update(['is_read' => true]);
                break;
            case 'unread':
                $query->update(['is_read' => false]);
                break;
            case 'archive':
                $query->update(['folder' => 'archive']);
                break;
            case 'inbox':
                $query->update(['folder' => 'inbox']);
                break;
            case 'spam':
                $query->update(['folder' => 'spam']);
                break;
            case 'important':
                $query->update(['folder' => 'important']);
                break;
        }

        MailboxSync::dispatch(
            $user->id,
            'bulk',
            ['ids' => $ids, 'action' => $action],
            $this->resolveTenantId()
        );

        return response()->json(['message' => 'Bulk action completed.']);
    }

    private function saveDraft(Request $request, User $user, ?string $tenantId, ?MailMessage $existingDraftMessage = null): JsonResponse
    {
        DB::beginTransaction();

        try {
            $message = $existingDraftMessage ?? new MailMessage();
            $message->fill([
                'sender_id' => $user->id,
                'subject' => $request->subject,
                'body' => $request->body,
                'status' => 'draft',
                'draft_recipients' => $this->buildDraftRecipientsPayload(
                    $request->input('to', []),
                    $request->input('cc', []),
                    $request->input('bcc', [])
                ),
            ]);
            $message->save();

            $senderDraftParticipant = MailParticipant::updateOrCreate(
                [
                    'mail_message_id' => $message->id,
                    'user_id' => $user->id,
                ],
                [
                    'type' => 'sender',
                    'folder' => 'drafts',
                    'is_read' => true,
                ]
            );

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            return response()->json([
                'error' => 'Failed to save draft: ' . $exception->getMessage(),
            ], 500);
        }

        $senderDraftParticipant->load(['message.sender', 'message.participants.user']);
        $draftPayload = MailPayload::participantForUser($senderDraftParticipant, $user);

        MailboxSync::dispatch(
            $user->id,
            'draft',
            [
                'message_id' => $message->id,
                'participantData' => $draftPayload,
                'is_new' => ! $existingDraftMessage,
            ],
            $tenantId
        );

        return response()->json([
            'message' => 'Draft saved.',
            'data' => $draftPayload,
        ], $existingDraftMessage ? 200 : 201);
    }

    private function findOwnedDraftMessage(int $userId, int $draftId): ?MailMessage
    {
        return MailMessage::query()
            ->whereKey($draftId)
            ->where('sender_id', $userId)
            ->where('status', 'draft')
            ->whereHas('participants', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('folder', 'drafts');
            })
            ->first();
    }

    private function buildDraftRecipientsPayload(array $to, array $cc, array $bcc): array
    {
        $allRecipientIds = collect([$to, $cc, $bcc])
            ->flatten()
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        if ($allRecipientIds->isEmpty()) {
            return [
                'to' => [],
                'cc' => [],
                'bcc' => [],
            ];
        }

        $users = User::query()
            ->whereIn('id', $allRecipientIds)
            ->get(['id', 'name', 'email', 'avatar_path', 'chat_encryption_public_key'])
            ->keyBy('id');

        $mapUsers = fn (array $ids) => collect($ids)
            ->map(fn ($id) => $users->get((int) $id))
            ->filter()
            ->map(fn (User $recipient) => MailPayload::userForPayload($recipient))
            ->values()
            ->all();

        return [
            'to' => $mapUsers($to),
            'cc' => $mapUsers($cc),
            'bcc' => $mapUsers($bcc),
        ];
    }

    private function resolveTenantId(): ?string
    {
        return function_exists('tenant') && tenant('id') ? (string) tenant('id') : null;
    }
}
