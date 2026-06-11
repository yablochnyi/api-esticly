<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use App\Support\TelegramAdminNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    private const MAX_MESSAGE_LENGTH = 50000;
    private const MAX_PREVIEW_LENGTH = 255;

    private function orgId(Request $request): int
    {
        $u = $request->user();
        return (int)($u->organization_id ?? $u->id);
    }

    private function findLatestOpenThread(int $orgId): ?SupportThread
    {
        return SupportThread::query()
            ->where('org_id', $orgId)
            ->where('status', 'open')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();
    }

    private function asThreadRow(SupportThread $t): array
    {
        return [
            'id' => (int)$t->id,
            'org_id' => (int)$t->org_id,
            'subject' => (string)($t->subject ?? ''),
            'status' => (string)($t->status ?? 'open'),
            'unread_for_user' => (int)($t->unread_for_user ?? 0),
            'last_message_preview' => (string)($t->last_message_preview ?? ''),
            'last_message_at' => $t->last_message_at,
        ];
    }

    private function messagePreview(string $body): string
    {
        $preview = preg_replace('/\s+/', ' ', trim($body)) ?: '';

        return Str::substr($preview, 0, self::MAX_PREVIEW_LENGTH);
    }

    // New: list tickets for current org.
    public function threads(Request $request)
    {
        $orgId = $this->orgId($request);

        $rows = SupportThread::query()
            ->where('org_id', $orgId)
            // Hide auto-created empty "Support" ticket: show only tickets that were created explicitly
            // or already contain messages.
            ->where(function ($q) {
                $q->whereNotNull('created_by_user_id')
                    ->orWhereNotNull('last_message_at');
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json($rows->map(fn ($t) => $this->asThreadRow($t))->values()->all());
    }

    // New: create a ticket.
    public function createThread(Request $request)
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:120'],
        ]);

        $orgId = $this->orgId($request);
        $u = $request->user();

        $subject = trim((string)($data['subject'] ?? ''));
        if ($subject === '') $subject = null;

        $t = SupportThread::query()->create([
            'org_id' => $orgId,
            'subject' => $subject,
            'status' => 'open',
            'created_by_user_id' => (int)$u->id,
            'last_message_at' => null,
            'last_message_preview' => null,
            'unread_for_support' => 0,
            'unread_for_user' => 0,
        ]);

        return response()->json($this->asThreadRow($t));
    }

    public function unreadCount(Request $request)
    {
        $orgId = $this->orgId($request);

        $count = SupportThread::query()
            ->where('org_id', $orgId)
            ->sum('unread_for_user');

        return response()->json([
            'unread_count' => (int) $count,
        ]);
    }

    private function findThreadOr404(int $orgId, int $threadId): SupportThread
    {
        return SupportThread::query()
            ->where('org_id', $orgId)
            ->where('id', $threadId)
            ->firstOrFail();
    }

    // New: messages for ticket.
    public function threadMessages(Request $request, int $threadId)
    {
        $orgId = $this->orgId($request);
        $t = $this->findThreadOr404($orgId, $threadId);

        $limit = (int)($request->query('limit', 100));
        if ($limit <= 0) $limit = 100;
        if ($limit > 300) $limit = 300;

        // Mark support messages as read for user.
        DB::transaction(function () use ($t, $orgId) {
            SupportMessage::query()
                ->where('thread_id', $t->id)
                ->where('org_id', $orgId)
                ->where('sender_type', 'support')
                ->whereNull('read_at_user')
                ->update(['read_at_user' => now()]);

            SupportThread::query()
                ->where('id', $t->id)
                ->update(['unread_for_user' => 0]);
        });

        $rows = SupportMessage::query()
            ->where('thread_id', $t->id)
            ->where('org_id', $orgId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'sender_type', 'sender_user_id', 'body', 'created_at']);

        $items = $rows->reverse()->values()->map(function ($m) {
            return [
                'id' => (int)$m->id,
                'sender_type' => (string)$m->sender_type,
                'sender_user_id' => $m->sender_user_id ? (int)$m->sender_user_id : null,
                'body' => (string)$m->body,
                'created_at' => (string)$m->created_at,
            ];
        })->all();

        return response()->json([
            'thread' => $this->asThreadRow($t),
            'items' => $items,
        ]);
    }

    // New: send message to ticket.
    public function sendToThread(Request $request, int $threadId)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:' . self::MAX_MESSAGE_LENGTH],
        ]);

        $orgId = $this->orgId($request);
        $t = $this->findThreadOr404($orgId, $threadId);
        $u = $request->user();

        $body = trim((string)$data['body']);
        $preview = $this->messagePreview($body);

        DB::transaction(function () use ($t, $orgId, $u, $body, $preview) {
            SupportMessage::query()->create([
                'thread_id' => $t->id,
                'org_id' => $orgId,
                'sender_type' => 'user',
                'sender_user_id' => (int)$u->id,
                'body' => $body,
                'read_at_support' => null,
                'read_at_user' => now(),
            ]);

            SupportThread::query()
                ->where('id', $t->id)
                ->update([
                    'last_message_at' => now(),
                    'last_message_preview' => $preview,
                    'unread_for_support' => DB::raw('unread_for_support + 1'),
                ]);
        });

        $this->notifySupportMessage($t, $orgId, $u, $body);

        return response()->json(['ok' => true]);
    }

    // Backward-compatible single-thread endpoints (map to latest open ticket).
    public function thread(Request $request)
    {
        $orgId = $this->orgId($request);
        $t = $this->findLatestOpenThread($orgId);

        return response()->json([
            'thread_id' => $t ? (int)$t->id : null,
            'org_id' => $orgId,
            'unread_for_user' => $t ? (int)$t->unread_for_user : 0,
            'last_message_at' => $t?->last_message_at,
        ]);
    }

    public function messages(Request $request)
    {
        $orgId = $this->orgId($request);
        $t = $this->findLatestOpenThread($orgId);
        if (!$t) {
            return response()->json([
                'thread_id' => null,
                'items' => [],
            ]);
        }

        $limit = (int)($request->query('limit', 100));
        if ($limit <= 0) $limit = 100;
        if ($limit > 300) $limit = 300;

        // Mark support messages as read for user.
        DB::transaction(function () use ($t, $orgId) {
            SupportMessage::query()
                ->where('thread_id', $t->id)
                ->where('org_id', $orgId)
                ->where('sender_type', 'support')
                ->whereNull('read_at_user')
                ->update(['read_at_user' => now()]);

            SupportThread::query()
                ->where('id', $t->id)
                ->update(['unread_for_user' => 0]);
        });

        $rows = SupportMessage::query()
            ->where('thread_id', $t->id)
            ->where('org_id', $orgId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'sender_type', 'sender_user_id', 'body', 'created_at']);

        // newest-first -> reverse for chat UX
        $items = $rows->reverse()->values()->map(function ($m) {
            return [
                'id' => (int)$m->id,
                'sender_type' => (string)$m->sender_type,
                'sender_user_id' => $m->sender_user_id ? (int)$m->sender_user_id : null,
                'body' => (string)$m->body,
                'created_at' => (string)$m->created_at,
            ];
        })->all();

        return response()->json([
            'thread_id' => (int)$t->id,
            'items' => $items,
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:' . self::MAX_MESSAGE_LENGTH],
        ]);

        $orgId = $this->orgId($request);
        $u = $request->user();
        $t = $this->findLatestOpenThread($orgId);
        if (!$t) {
            // Create only on explicit send.
            $t = SupportThread::query()->create([
                'org_id' => $orgId,
                'subject' => null,
                'status' => 'open',
                'created_by_user_id' => (int)$u->id,
                'last_message_at' => null,
                'last_message_preview' => null,
                'unread_for_support' => 0,
                'unread_for_user' => 0,
            ]);
        }

        $body = trim((string)$data['body']);
        $preview = $this->messagePreview($body);

        DB::transaction(function () use ($t, $orgId, $u, $body, $preview) {
            SupportMessage::query()->create([
                'thread_id' => $t->id,
                'org_id' => $orgId,
                'sender_type' => 'user',
                'sender_user_id' => (int)$u->id,
                'body' => $body,
                'read_at_support' => null,
                'read_at_user' => now(), // user obviously "read" own message
            ]);

            SupportThread::query()
                ->where('id', $t->id)
                ->update([
                    'last_message_at' => now(),
                    'last_message_preview' => $preview,
                    'unread_for_support' => DB::raw('unread_for_support + 1'),
                ]);
        });

        $this->notifySupportMessage($t, $orgId, $u, $body);

        return response()->json(['ok' => true]);
    }

    private function notifySupportMessage(SupportThread $thread, int $orgId, User $user, string $body): void
    {
        $org = User::query()->find($orgId);

        TelegramAdminNotifier::supportMessage([
            'thread_id' => $thread->id,
            'org_id' => $orgId,
            'company_name' => $org?->company_name,
            'phone' => $org?->phone ?? $user->phone,
            'user_id' => $user->id,
            'message' => Str::limit($body, 1000),
        ]);
    }
}
