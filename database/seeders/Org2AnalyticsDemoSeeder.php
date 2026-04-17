<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Org2AnalyticsDemoSeeder extends Seeder
{
    private const ORG_ID = 2;
    private const MARKER = '[ANALYTICS DEMO 2026]';

    public function run(): void
    {
        $org = User::query()->findOrFail(self::ORG_ID);
        $tz = $org->timezone ?: (config('app.timezone') ?: 'UTC');

        $staff = Staff::query()
            ->where('user_id', self::ORG_ID)
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(3)
            ->get();

        if ($staff->count() < 3) {
            throw new \RuntimeException('Organization #2 must have at least 3 active staff members for Org2AnalyticsDemoSeeder.');
        }

        DB::transaction(function () use ($staff, $tz): void {
            $this->cleanupOldDemoData();

            $services = $this->seedServices($staff);
            $clients = $this->seedClients($staff);
            $visits = $this->seedVisits($services, $clients, $staff, $tz);

            $this->printSummary($staff, $services, $clients, $visits);
        });
    }

    private function cleanupOldDemoData(): void
    {
        $demoServiceIds = Service::query()
            ->where('user_id', self::ORG_ID)
            ->where('name', 'like', self::MARKER . ' %')
            ->pluck('id');

        if ($demoServiceIds->isNotEmpty()) {
            DB::table('service_staff')->whereIn('service_id', $demoServiceIds)->delete();
        }

        Visit::query()
            ->where('user_id', self::ORG_ID)
            ->where(function ($query) use ($demoServiceIds): void {
                $query->where('comment', 'like', self::MARKER . '%');
                if ($demoServiceIds->isNotEmpty()) {
                    $query->orWhereIn('service_id', $demoServiceIds);
                }
            })
            ->delete();

        Client::query()
            ->withTrashed()
            ->where('user_id', self::ORG_ID)
            ->where('name', 'like', self::MARKER . ' Client %')
            ->forceDelete();

        Service::query()
            ->where('user_id', self::ORG_ID)
            ->where('name', 'like', self::MARKER . ' %')
            ->delete();
    }

    private function seedServices(Collection $staff): Collection
    {
        $definitions = [
            ['Classic manicure', 'Nails', 120.00, 60],
            ['Builder gel refill', 'Nails', 150.00, 75],
            ['Brow shaping and tint', 'Brows', 180.00, 90],
            ['Express haircut', 'Hair', 90.00, 45],
            ['Complex coloring', 'Hair', 210.00, 120],
            ['Facial cleansing', 'Cosmetology', 160.00, 90],
            ['Evening makeup', 'Makeup', 140.00, 60],
            ['Lash lift', 'Lashes', 110.00, 45],
            ['Barber combo', 'Barber', 170.00, 75],
            ['SPA care ritual', 'Wellness', 130.00, 60],
        ];

        $services = collect();

        foreach ($definitions as [$name, $category, $price, $duration]) {
            $service = Service::query()->create([
                'user_id' => self::ORG_ID,
                'name' => self::MARKER . ' ' . $name,
                'description' => 'Demo analytics service for seeded reports.',
                'category' => $category,
                'price_type' => 'fixed',
                'price_fixed' => $price,
                'price_from' => null,
                'price_to' => null,
                'duration_from_min' => $duration,
                'duration_to_min' => $duration,
                'buffer_before_min' => 0,
                'buffer_after_min' => 0,
            ]);

            $service->staff()->syncWithoutDetaching($staff->pluck('id')->all());
            $services->push($service);
        }

        return $services;
    }

    private function seedClients(Collection $staff): Collection
    {
        $clients = collect();

        for ($i = 1; $i <= 24; $i++) {
            $staffId = match (true) {
                $i % 4 === 1 => $staff[0]->id,
                $i % 4 === 2 => $staff[1]->id,
                $i % 4 === 3 => $staff[2]->id,
                default => null,
            };

            $clients->push(Client::query()->create([
                'user_id' => self::ORG_ID,
                'created_by_staff_id' => $staffId,
                'name' => sprintf('%s Client %02d', self::MARKER, $i),
                'phone' => sprintf('+485550020%02d', $i),
            ]));
        }

        return $clients;
    }

    private function seedVisits(Collection $services, Collection $clients, Collection $staff, string $tz): Collection
    {
        $servicePlans = [
            ['total' => 14, 'completed' => 10, 'cancelled' => 2, 'pending' => 2],
            ['total' => 12, 'completed' => 9,  'cancelled' => 2, 'pending' => 1],
            ['total' => 11, 'completed' => 8,  'cancelled' => 2, 'pending' => 1],
            ['total' => 10, 'completed' => 7,  'cancelled' => 2, 'pending' => 1],
            ['total' => 10, 'completed' => 7,  'cancelled' => 2, 'pending' => 1],
            ['total' => 9,  'completed' => 6,  'cancelled' => 2, 'pending' => 1],
            ['total' => 9,  'completed' => 7,  'cancelled' => 1, 'pending' => 1],
            ['total' => 8,  'completed' => 5,  'cancelled' => 2, 'pending' => 1],
            ['total' => 9,  'completed' => 6,  'cancelled' => 2, 'pending' => 1],
            ['total' => 8,  'completed' => 5,  'cancelled' => 3, 'pending' => 0],
        ];

        $serviceQueue = [];
        foreach ($services as $index => $service) {
            $plan = $servicePlans[$index];
            $serviceQueue = array_merge($serviceQueue, array_fill(0, $plan['completed'], ['service' => $service, 'status' => 'completed']));
            $serviceQueue = array_merge($serviceQueue, array_fill(0, $plan['cancelled'], ['service' => $service, 'status' => 'cancelled']));
            $serviceQueue = array_merge($serviceQueue, array_fill(0, $plan['pending'], ['service' => $service, 'status' => 'pending']));
        }

        $visits = collect();
        $ownerAndStaff = [null, $staff[0]->id, $staff[1]->id, $staff[2]->id];

        $date = Carbon::create(2026, 3, 1, 9, 0, 0, $tz);
        $slotOffsets = [0, 180];
        $queueIndex = 0;

        for ($dayIndex = 0; $dayIndex < 51; $dayIndex++) {
            $slotsToday = $dayIndex < 49 ? 2 : 1;

            for ($slot = 0; $slot < $slotsToday; $slot++) {
                $entry = $serviceQueue[$queueIndex];
                $service = $entry['service'];
                $status = $entry['status'];
                $client = $clients[$queueIndex % $clients->count()];
                $staffId = $ownerAndStaff[$queueIndex % count($ownerAndStaff)];
                $duration = (int) ($service->duration_from_min ?: 60);

                $startsAtLocal = $date->copy()->addDays($dayIndex)->addMinutes($slotOffsets[$slot] + (($queueIndex % 3) * 10));
                $endsAtLocal = $startsAtLocal->copy()->addMinutes($duration);

                $visits->push(Visit::query()->create([
                    'user_id' => self::ORG_ID,
                    'service_id' => $service->id,
                    'staff_id' => $staffId,
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'client_phone' => $client->phone,
                    'starts_at' => $startsAtLocal->copy()->utc(),
                    'ends_at' => $endsAtLocal->copy()->utc(),
                    'duration_min' => $duration,
                    'price' => $service->price_fixed,
                    'promo_discount' => 0,
                    'status' => $status,
                    'comment' => self::MARKER . ' Visit #' . str_pad((string) ($queueIndex + 1), 3, '0', STR_PAD_LEFT),
                ]));

                $queueIndex++;
            }
        }

        return $visits;
    }

    private function printSummary(Collection $staff, Collection $services, Collection $clients, Collection $visits): void
    {
        $completed = $visits->where('status', 'completed');
        $cancelled = $visits->where('status', 'cancelled');
        $pending = $visits->where('status', 'pending');
        $revenue = round((float) $completed->sum(fn (Visit $visit) => (float) $visit->price), 2);
        $avgCheck = round($revenue / max($completed->count(), 1), 2);

        $staffBuckets = [
            'owner_salon' => $visits->whereNull('staff_id')->count(),
        ];

        foreach ($staff as $member) {
            $staffBuckets['staff_' . $member->id . '_' . $member->name] = $visits->where('staff_id', $member->id)->count();
        }

        $topServices = $services->map(function (Service $service) use ($visits): array {
            $serviceVisits = $visits->where('service_id', $service->id);

            return [
                'name' => $service->name,
                'count' => $serviceVisits->count(),
                'revenue' => round((float) $serviceVisits->where('status', 'completed')->sum(fn (Visit $visit) => (float) $visit->price), 2),
            ];
        })->sortByDesc('count')->values()->all();

        $summary = [
            'organization_id' => self::ORG_ID,
            'marker' => self::MARKER,
            'services_created' => $services->count(),
            'clients_created' => $clients->count(),
            'visits_created' => $visits->count(),
            'completed' => $completed->count(),
            'cancelled' => $cancelled->count(),
            'pending' => $pending->count(),
            'revenue' => $revenue,
            'avg_check' => $avgCheck,
            'services_unique' => $services->count(),
            'staff_distribution' => $staffBuckets,
            'top_services' => array_slice($topServices, 0, 5),
        ];

        $this->command?->info('Org2AnalyticsDemoSeeder summary:');
        $this->command?->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
