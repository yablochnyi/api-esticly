<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientNote;
use App\Support\StaffGuard;
use Illuminate\Http\Request;

class ClientNoteController extends Controller
{
    private function orgId(Request $request): int
    {
        $u = $request->user();
        return (int) ($u->organization_id ?? $u->id);
    }

    private function assertClientAccess(Request $request, Client $client): void
    {
        $u = $request->user();
        $orgId = $this->orgId($request);
        abort_unless((int) $client->user_id === $orgId, 404);

        if ($u->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
            StaffGuard::requirePermission($staff, 'clients_access');
        }
    }

    public function index(Request $request, Client $client)
    {
        $orgId = $this->orgId($request);
        $this->assertClientAccess($request, $client);

        return $client->notes()
            ->whereIn('user_id', [$orgId, (int) $request->user()->id])
            ->orderByDesc('updated_at')
            ->get(['id','client_id','text','updated_at']);
    }

    public function store(Request $request, Client $client)
    {
        $orgId = $this->orgId($request);
        $this->assertClientAccess($request, $client);

        $data = $request->validate([
            'text' => ['required','string'],
        ]);

        $note = $client->notes()->create([
            // Keep notes at organization scope so owner + permitted staff can see them.
            'user_id' => $orgId,
            'text' => $data['text'],
        ]);

        return response()->json($note->only(['id','client_id','text','updated_at']), 201);
    }

    public function update(Request $request, ClientNote $note)
    {
        $note->loadMissing('client');
        $client = $note->client;
        abort_unless($client, 404);
        $this->assertClientAccess($request, $client);

        $orgId = $this->orgId($request);
        abort_unless(in_array((int)$note->user_id, [$orgId, (int)$request->user()->id], true), 404);

        $data = $request->validate([
            'text' => ['required','string'],
        ]);

        $note->update(['text' => $data['text']]);

        return response()->json($note->only(['id','client_id','text','updated_at']));
    }
}
