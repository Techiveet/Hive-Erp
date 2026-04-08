<?php

namespace Modules\MailBox\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\MailBox\Models\MailMessage;
use Modules\MailBox\Models\MailParticipant;
use Modules\MailBox\Jobs\DispatchMailToParticipants;
use Illuminate\Support\Facades\DB;
use Modules\MailBox\Services\MailboxStorageTracker;
use Modules\MailBox\Events\MailboxSync;

class MailBoxController extends Controller
{
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

        $query->orderBy('created_at', 'desc');

        return response()->json($query->paginate(50));
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
            'storage_limit' => MailboxStorageTracker::getUserQuotaLimitBytes()
        ];

        if (function_exists('tenant') && tenant('id')) {
            $counts['tenant_storage_used'] = MailboxStorageTracker::getTenantTotalStorageUsedBytes();
            $counts['tenant_storage_limit'] = MailboxStorageTracker::getTenantGlobalQuotaLimitBytes();
        }

        return response()->json($counts);
    }

    public function store(Request $request)
    {
        $interceptAll = function($arr) use ($request) {
            if (is_array($arr) && in_array('all', $arr)) {
                return \Modules\Identity\Models\User::where('is_active', true)
                    ->where('id', '!=', $request->user()->id)
                    ->pluck('id')
                    ->toArray();
            }
            return $arr;
        };

        if ($request->has('to')) $request->merge(['to' => $interceptAll($request->to)]);
        if ($request->has('cc')) $request->merge(['cc' => $interceptAll($request->cc)]);
        if ($request->has('bcc')) $request->merge(['bcc' => $interceptAll($request->bcc)]);

        $request->validate([
            'subject' => 'nullable|string|max:255',
            'body'    => 'required|string',
            'to'      => 'required|array',
            'to.*'    => 'exists:users,id',
            'cc'      => 'nullable|array',
            'cc.*'    => 'exists:users,id',
            'bcc'     => 'nullable|array',
            'bcc.*'   => 'exists:users,id',
        ]);

        $user = $request->user();

        // 🛑 Enforce Quota: Check if sender has enough space
        $incomingBytes = strlen($request->subject ?? '') + strlen($request->body);
        if (!MailboxStorageTracker::canAcceptMail($user->id, $incomingBytes)) {
            return response()->json(['error' => 'Mailbox Quota Exceeded. Please empty your trash to send more messages.'], 422);
        }

        DB::beginTransaction();

        try {
            $message = MailMessage::create([
                'sender_id' => $user->id,
                'subject'   => $request->subject,
                'body'      => $request->body,
                'status'    => 'sent',
            ]);

            // Add Sender to sent folder
            MailParticipant::create([
                'mail_message_id' => $message->id,
                'user_id'         => $user->id,
                'type'            => 'sender',
                'folder'          => 'sent',
                'is_read'         => true,
            ]);

            // Add receivers to the queue pipeline
            $tenantId = function_exists('tenant') && tenant('id') ? tenant('id') : null;

            if (!empty($request->to)) DispatchMailToParticipants::dispatch($message, $request->to, 'to', $tenantId);
            if (!empty($request->cc)) DispatchMailToParticipants::dispatch($message, $request->cc, 'cc', $tenantId);
            if (!empty($request->bcc)) DispatchMailToParticipants::dispatch($message, $request->bcc, 'bcc', $tenantId);

            DB::commit();

            // Notify sender's session(s) in real time so the Sent folder updates instantly.
            MailboxSync::dispatch(
                $user->id,
                'sent',
                ['message_id' => $message->id],
                $tenantId
            );

            return response()->json(['message' => 'Email sent.', 'data' => $message], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to send email: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $participant = MailParticipant::with(['message.sender', 'message.participants.user'])
            ->where('user_id', $user->id)
            ->where('mail_message_id', $id)
            ->firstOrFail();

        if (!$participant->is_read) {
            $participant->update(['is_read' => true]);
        }

        return response()->json($participant);
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

        $tenantId = function_exists('tenant') && tenant('id') ? tenant('id') : null;
        MailboxSync::dispatch(
            $user->id,
            'update',
            ['message_id' => $id, 'changes' => $updateData],
            $tenantId
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
            $participant->delete(); // Permanent delete from trash
        } else {
            $participant->update(['folder' => 'trash']);
        }

        $tenantId = function_exists('tenant') && tenant('id') ? tenant('id') : null;
        MailboxSync::dispatch(
            $user->id,
            'delete',
            ['message_id' => $id, 'permanent' => $wasTrashed],
            $tenantId
        );

        return response()->json(['message' => 'Deleted successfully']);
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'action' => 'required|string|in:trash,delete,star,unstar,read,unread,archive,inbox,spam,important'
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
                $query->delete(); // Soft delete
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

        $tenantId = function_exists('tenant') && tenant('id') ? tenant('id') : null;
        MailboxSync::dispatch(
            $user->id,
            'bulk',
            ['ids' => $ids, 'action' => $action],
            $tenantId
        );

        return response()->json(['message' => 'Bulk action completed.']);
    }
}
