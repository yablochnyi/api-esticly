<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\Dsar;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DsarExportController extends Controller
{
    private function assertAdmin(Request $request): User
    {
        $user = $request->user();
        if (!AdminAccess::allows($user)) {
            abort(403);
        }
        return $user;
    }

    private function jsonDownload(array $payload, string $fileName): StreamedResponse
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            abort(500, 'json_encode_failed');
        }

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $fileName, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public function client(Request $request): StreamedResponse
    {
        $actor = $this->assertAdmin($request);
        $data = $request->validate([
            'org_id' => ['required', 'integer'],
            'client_id' => ['required', 'integer'],
            'lang' => ['nullable', 'string', 'max:8'],
        ]);

        $org = User::query()->findOrFail((int) $data['org_id']);
        $client = Client::withTrashed()
            ->where('user_id', $org->id)
            ->findOrFail((int) $data['client_id']);
        $lang = Dsar::normalizeLanguage($data['lang'] ?? $org->language_code);

        $op = Dsar::createOperation(
            orgId: (int) $org->id,
            actorUserId: (int) $actor->id,
            actorStaffId: null,
            clientId: (int) $client->id,
            type: 'export_client',
            languageCode: $lang,
            targetEmail: null,
            meta: ['source' => 'filament'],
        );
        $op->status = 'running';
        $op->started_at = now();
        $op->save();

        try {
            $payload = Dsar::buildClientPayload($org, $client, $lang);
            $op->status = 'done';
            $op->finished_at = now();
            $op->save();
            return $this->jsonDownload($payload, "client_{$client->id}.json");
        } catch (\Throwable $e) {
            $op->status = 'failed';
            $op->finished_at = now();
            $op->error_message = $e->getMessage();
            $op->save();
            throw $e;
        }
    }

    public function allClients(Request $request): StreamedResponse
    {
        $actor = $this->assertAdmin($request);
        $data = $request->validate([
            'org_id' => ['required', 'integer'],
            'lang' => ['nullable', 'string', 'max:8'],
        ]);

        $org = User::query()->findOrFail((int) $data['org_id']);
        $lang = Dsar::normalizeLanguage($data['lang'] ?? $org->language_code);

        $op = Dsar::createOperation(
            orgId: (int) $org->id,
            actorUserId: (int) $actor->id,
            actorStaffId: null,
            clientId: null,
            type: 'export_all_clients',
            languageCode: $lang,
            targetEmail: null,
            meta: ['source' => 'filament'],
        );
        $op->status = 'running';
        $op->started_at = now();
        $op->save();

        try {
            $payload = Dsar::buildAllClientsPayload($org, $lang);
            $op->status = 'done';
            $op->finished_at = now();
            $op->save();
            return $this->jsonDownload($payload, "org_{$org->id}_clients.json");
        } catch (\Throwable $e) {
            $op->status = 'failed';
            $op->finished_at = now();
            $op->error_message = $e->getMessage();
            $op->save();
            throw $e;
        }
    }

    public function organization(Request $request): StreamedResponse
    {
        $actor = $this->assertAdmin($request);
        $data = $request->validate([
            'org_id' => ['required', 'integer'],
            'lang' => ['nullable', 'string', 'max:8'],
        ]);

        $org = User::query()->findOrFail((int) $data['org_id']);
        $lang = Dsar::normalizeLanguage($data['lang'] ?? $org->language_code);

        $op = Dsar::createOperation(
            orgId: (int) $org->id,
            actorUserId: (int) $actor->id,
            actorStaffId: null,
            clientId: null,
            type: 'export_org',
            languageCode: $lang,
            targetEmail: null,
            meta: ['source' => 'filament'],
        );
        $op->status = 'running';
        $op->started_at = now();
        $op->save();

        try {
            $payload = Dsar::buildOrganizationPayload($org, $lang);
            $op->status = 'done';
            $op->finished_at = now();
            $op->save();
            return $this->jsonDownload($payload, "org_{$org->id}.json");
        } catch (\Throwable $e) {
            $op->status = 'failed';
            $op->finished_at = now();
            $op->error_message = $e->getMessage();
            $op->save();
            throw $e;
        }
    }
}
