<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Support\Dsar;
use Illuminate\Http\Request;

class ClientDsarController extends Controller
{
    private function ownerOrgOrAbort(Request $request): User
    {
        $actor = $request->user();
        if (!Dsar::isOwner($actor)) {
            abort(403, 'access_denied');
        }

        $orgId = Dsar::orgId($actor);
        return User::query()->findOrFail($orgId);
    }

    public function export(Request $request, Client $client)
    {
        $org = $this->ownerOrgOrAbort($request);
        abort_unless((int) $client->user_id === (int) $org->id, 404);

        $data = $request->validate([
            'language_code' => ['nullable', 'string', 'max:8'],
        ]);

        if (empty($org->email)) {
            return response()->json(['message' => 'email_required'], 422);
        }

        $lang = Dsar::normalizeLanguage($data['language_code'] ?? $org->language_code);
        if (($org->language_code ?? null) !== $lang) {
            $org->language_code = $lang;
            $org->save();
        }

        $actor = $request->user();
        $op = Dsar::createOperation(
            orgId: (int) $org->id,
            actorUserId: (int) $actor->id,
            actorStaffId: $actor->staff_id ? (int) $actor->staff_id : null,
            clientId: (int) $client->id,
            type: 'export_client',
            languageCode: $lang,
            targetEmail: (string) $org->email,
            meta: ['source' => 'mobile'],
        );

        $op = Dsar::runClientExport($op, $org, $client, $lang, (string) $org->email);

        return response()->json([
            'id' => $op->id,
            'type' => $op->type,
            'status' => $op->status,
            'target_email' => $op->target_email,
            'error_message' => $op->error_message,
            'created_at' => $op->created_at?->toISOString(),
            'finished_at' => $op->finished_at?->toISOString(),
        ]);
    }

    public function anonymize(Request $request, Client $client)
    {
        $org = $this->ownerOrgOrAbort($request);
        abort_unless((int) $client->user_id === (int) $org->id, 404);

        $data = $request->validate([
            'language_code' => ['nullable', 'string', 'max:8'],
            'confirm' => ['required', 'boolean'],
        ]);
        if (!$data['confirm']) {
            return response()->json(['message' => 'confirmation_required'], 422);
        }

        $lang = Dsar::normalizeLanguage($data['language_code'] ?? $org->language_code);
        if (($org->language_code ?? null) !== $lang) {
            $org->language_code = $lang;
            $org->save();
        }

        $actor = $request->user();
        $op = Dsar::createOperation(
            orgId: (int) $org->id,
            actorUserId: (int) $actor->id,
            actorStaffId: $actor->staff_id ? (int) $actor->staff_id : null,
            clientId: (int) $client->id,
            type: 'anonymize_client',
            languageCode: $lang,
            targetEmail: null,
            meta: ['source' => 'mobile'],
        );

        $op = Dsar::runAnonymizeClient($op, $org, $actor, $client, $lang);

        return response()->json([
            'id' => $op->id,
            'type' => $op->type,
            'status' => $op->status,
            'error_message' => $op->error_message,
            'created_at' => $op->created_at?->toISOString(),
            'finished_at' => $op->finished_at?->toISOString(),
        ]);
    }
}

