<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\PersonalNote;
use App\Support\StaffGuard;
use Illuminate\Http\Request;

class PersonalNoteController extends Controller
{
    private function currentUserId(Request $request): int
    {
        $u = $request->user();
        if ($u->staff_id) {
            StaffGuard::currentOrAbort($request);
        }

        return (int) $u->id;
    }

    private function findForCurrentUserOrAbort(Request $request, PersonalNote $note): PersonalNote
    {
        $userId = $this->currentUserId($request);
        abort_unless((int) $note->user_id === $userId, 404);
        return $note;
    }

    public function index(Request $request)
    {
        $userId = $this->currentUserId($request);

        return PersonalNote::query()
            ->where('user_id', $userId)
            ->orderByRaw('CASE WHEN remind_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('remind_at')
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'user_id',
                'text',
                'remind_at',
                'reminder_sent_at',
                'created_at',
                'updated_at',
            ]);
    }

    public function store(Request $request)
    {
        $userId = $this->currentUserId($request);
        $data = $this->validatePayload($request);

        $note = PersonalNote::query()->create([
            'user_id' => $userId,
            'text' => $data['text'],
            'remind_at' => $data['remind_at'] ?? null,
            'reminder_queued_at' => null,
            'reminder_sent_at' => null,
            'reminder_error' => null,
        ]);

        return response()->json($this->payload($note), 201);
    }

    public function update(Request $request, PersonalNote $note)
    {
        $note = $this->findForCurrentUserOrAbort($request, $note);
        $data = $this->validatePayload($request);

        $nextRemindAt = $data['remind_at'] ?? null;
        $changedReminder = (string) optional($note->remind_at)?->toIso8601String() !== (string) optional($nextRemindAt)?->toIso8601String();

        $note->text = $data['text'];
        $note->remind_at = $nextRemindAt;
        if ($changedReminder) {
            $note->reminder_queued_at = null;
            $note->reminder_sent_at = null;
            $note->reminder_error = null;
        }
        if ($nextRemindAt === null) {
            $note->reminder_queued_at = null;
            $note->reminder_sent_at = null;
            $note->reminder_error = null;
        }
        $note->save();

        return response()->json($this->payload($note));
    }

    public function destroy(Request $request, PersonalNote $note)
    {
        $note = $this->findForCurrentUserOrAbort($request, $note);
        $note->delete();
        return response()->json(['ok' => true]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'text' => ['required', 'string', 'max:5000'],
            'remind_at' => ['nullable', 'date'],
        ]);
    }

    private function payload(PersonalNote $note): array
    {
        return [
            'id' => (int) $note->id,
            'user_id' => (int) $note->user_id,
            'text' => (string) $note->text,
            'remind_at' => optional($note->remind_at)?->toIso8601String(),
            'reminder_sent_at' => optional($note->reminder_sent_at)?->toIso8601String(),
            'created_at' => optional($note->created_at)?->toIso8601String(),
            'updated_at' => optional($note->updated_at)?->toIso8601String(),
        ];
    }
}
