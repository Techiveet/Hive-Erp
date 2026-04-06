<?php

namespace Modules\MailBox\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\MailBox\Models\MailMessage;
use Modules\MailBox\Models\MailParticipant;
use Modules\MailBox\Events\MailReceived;
use Illuminate\Support\Facades\DB;

class MailBoxController extends Controller
{
    public function index(Request $request)
    {
        $folder = $request->query('folder', 'inbox');
        $user = $request->user();

        $query = MailParticipant::with(['message.sender', 'message.participants.user'])
            ->where('user_id', $user->id)
            ->where('folder', $folder)
            ->orderBy('created_at', 'desc');

        return response()->json($query->paginate(20));
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

    public function store(Request $request)
    {
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

            // Add receivers
            $tenantId = function_exists('tenant') && tenant('id') ? tenant('id') : null;

            $addParticipants = function($userIds, $type) use ($message, $tenantId) {
                if (empty($userIds)) return;
                foreach ($userIds as $recipientId) {
                    MailParticipant::create([
                        'mail_message_id' => $message->id,
                        'user_id'         => $recipientId,
                        'type'            => $type,
                        'folder'          => 'inbox',
                        'is_read'         => false,
                    ]);

                    // Broadcast
                    broadcast(new MailReceived($message->toArray(), $recipientId, $tenantId));
                }
            };
            
            $addParticipants($request->to, 'to');
            if ($request->has('cc')) {
                $addParticipants($request->cc, 'cc');
            }
            if ($request->has('bcc')) {
                $addParticipants($request->bcc, 'bcc');
            }

            DB::commit();

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

        return response()->json(['message' => 'Updated successfully', 'data' => $participant]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        
        $participant = MailParticipant::where('user_id', $user->id)
            ->where('mail_message_id', $id)
            ->firstOrFail();

        if ($participant->folder === 'trash') {
            $participant->delete(); // Soft delete
        } else {
            $participant->update(['folder' => 'trash']);
        }

        return response()->json(['message' => 'Deleted successfully']);
    }
}
