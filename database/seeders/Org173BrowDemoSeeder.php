<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Org173BrowDemoSeeder extends Seeder
{
    private const ORG_ID = 173;
    private const MARKER = '[ORG 173 BROW DEMO 2026-07]';
    private const CLIENT_INSTAGRAM_PREFIX = 'demo_brows_173_';

    public function run(): void
    {
        $org = User::query()->findOrFail(self::ORG_ID);
        $tz = $org->timezone ?: 'Europe/Kyiv';

        DB::transaction(function () use ($org, $tz): void {
            $this->cleanupOldDemoData();

            $org->forceFill([
                'language_code' => 'uk',
                'currency_code' => $org->currency_code ?: 'UAH',
                'timezone' => $tz,
            ])->save();

            $staff = $this->resolveStaff($tz);
            $services = $this->seedServices($staff);
            $clients = $this->seedClients($staff);
            $visits = $this->seedVisits($services, $clients, $staff, $tz);

            $this->printSummary($services, $clients, $visits);
        });
    }

    private function cleanupOldDemoData(): void
    {
        $demoServiceIds = Service::query()
            ->where('user_id', self::ORG_ID)
            ->where('description', self::MARKER)
            ->pluck('id');

        if ($demoServiceIds->isNotEmpty()) {
            DB::table('service_staff')->whereIn('service_id', $demoServiceIds)->delete();
        }

        Visit::query()
            ->where('user_id', self::ORG_ID)
            ->where('comment', 'like', self::MARKER . '%')
            ->delete();

        Client::query()
            ->withTrashed()
            ->where('user_id', self::ORG_ID)
            ->where('instagram', 'like', self::CLIENT_INSTAGRAM_PREFIX . '%')
            ->forceDelete();

        Service::query()
            ->where('user_id', self::ORG_ID)
            ->where('description', self::MARKER)
            ->delete();
    }

    private function resolveStaff(string $tz): Staff
    {
        $staff = Staff::query()
            ->where('user_id', self::ORG_ID)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($staff) {
            return $staff;
        }

        return Staff::query()->create([
            'user_id' => self::ORG_ID,
            'name' => 'Майстриня брів',
            'phone' => null,
            'is_active' => true,
            'timezone' => $tz,
            'schedule' => $this->defaultSchedule(),
            'permissions' => [
                'clients' => true,
                'services' => true,
                'visits' => true,
                'analytics' => true,
            ],
        ]);
    }

    private function defaultSchedule(): array
    {
        return [
            'monday' => ['enabled' => true, 'from' => '09:00', 'to' => '19:00'],
            'tuesday' => ['enabled' => true, 'from' => '09:00', 'to' => '19:00'],
            'wednesday' => ['enabled' => true, 'from' => '09:00', 'to' => '19:00'],
            'thursday' => ['enabled' => true, 'from' => '09:00', 'to' => '19:00'],
            'friday' => ['enabled' => true, 'from' => '09:00', 'to' => '19:00'],
            'saturday' => ['enabled' => true, 'from' => '10:00', 'to' => '16:00'],
            'sunday' => ['enabled' => false, 'from' => '09:00', 'to' => '18:00'],
        ];
    }

    private function seedServices(Staff $staff): Collection
    {
        $definitions = [
            ['Корекція брів', 'Брови', 350.00, 30],
            ['Фарбування брів', 'Брови', 300.00, 30],
            ['Корекція та фарбування брів', 'Брови', 550.00, 60],
            ['Ламінування брів', 'Брови', 800.00, 75],
            ['Довготривала укладка брів', 'Брови', 700.00, 60],
            ['Комплекс: ламінування + фарбування', 'Брови', 950.00, 90],
        ];

        return collect($definitions)->map(function (array $definition) use ($staff): Service {
            [$name, $category, $price, $duration] = $definition;

            $service = Service::query()->create([
                'user_id' => self::ORG_ID,
                'name' => $name,
                'description' => self::MARKER,
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

            $service->staff()->syncWithoutDetaching([$staff->id]);

            return $service;
        });
    }

    private function seedClients(Staff $staff): Collection
    {
        $names = [
            'Анастасія Коваленко',
            'Марія Шевченко',
            'Ольга Бондар',
            'Ірина Мельник',
            'Катерина Савчук',
            'Вікторія Поліщук',
            'Юлія Кравченко',
            'Наталія Романюк',
            'Софія Ткаченко',
            'Дарина Лисенко',
            'Аліна Мороз',
            'Христина Петренко',
            'Тетяна Гончар',
            'Валерія Кушнір',
            'Оксана Климчук',
            'Вероніка Павлюк',
            'Лілія Сидоренко',
            'Анна Захарченко',
            'Діана Білик',
            'Євгенія Остапенко',
            'Марта Гуменюк',
            'Соломія Коваль',
            'Ніка Черненко',
            'Аделіна Руденко',
        ];

        return collect($names)->values()->map(function (string $name, int $index) use ($staff): Client {
            $number = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

            return Client::query()->create([
                'user_id' => self::ORG_ID,
                'created_by_staff_id' => $staff->id,
                'name' => $name,
                'phone' => '+38067173' . $number,
                'instagram' => self::CLIENT_INSTAGRAM_PREFIX . $number,
            ]);
        });
    }

    private function seedVisits(Collection $services, Collection $clients, Staff $staff, string $tz): Collection
    {
        $visits = collect();
        $monthStart = CarbonImmutable::create(2026, 7, 1, 0, 0, 0, $tz);
        $today = CarbonImmutable::create(2026, 7, 18, 0, 0, 0, $tz);
        $slots = ['09:00', '14:00'];
        $visitIndex = 0;

        for ($day = 1; $day <= 31 && $visits->count() < 50; $day++) {
            $date = $monthStart->addDays($day - 1);

            if ($date->isSunday()) {
                continue;
            }

            foreach ($slots as $slot) {
                if ($visits->count() >= 50) {
                    break;
                }

                $service = $services[$visitIndex % $services->count()];
                $client = $clients[$visitIndex % $clients->count()];
                [$hour, $minute] = array_map('intval', explode(':', $slot));
                $startsAtLocal = $date->setTime($hour, $minute);
                $duration = (int) ($service->duration_from_min ?: 60);
                $endsAtLocal = $startsAtLocal->addMinutes($duration);
                $status = $startsAtLocal->lessThan($today)
                    ? ($visitIndex % 9 === 0 ? 'cancelled' : 'completed')
                    : 'pending';

                $visits->push(Visit::query()->create([
                    'user_id' => self::ORG_ID,
                    'service_id' => $service->id,
                    'staff_id' => $staff->id,
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'client_phone' => $client->phone,
                    'starts_at' => $startsAtLocal->utc(),
                    'ends_at' => $endsAtLocal->utc(),
                    'duration_min' => $duration,
                    'price' => $service->price_fixed,
                    'payment_method' => $visitIndex % 4 === 0 ? 'card' : 'cash',
                    'promo_discount' => 0,
                    'status' => $status,
                    'comment' => self::MARKER . ' Візит #' . str_pad((string) ($visitIndex + 1), 2, '0', STR_PAD_LEFT),
                ]));

                $visitIndex++;
            }
        }

        return $visits;
    }

    private function printSummary(Collection $services, Collection $clients, Collection $visits): void
    {
        $completed = $visits->where('status', 'completed');
        $cancelled = $visits->where('status', 'cancelled');
        $pending = $visits->where('status', 'pending');

        $firstVisit = $visits->sortBy('starts_at')->first();
        $lastVisit = $visits->sortByDesc('starts_at')->first();

        $summary = [
            'organization_id' => self::ORG_ID,
            'marker' => self::MARKER,
            'services_created' => $services->count(),
            'clients_created' => $clients->count(),
            'visits_created' => $visits->count(),
            'completed' => $completed->count(),
            'cancelled' => $cancelled->count(),
            'pending' => $pending->count(),
            'revenue_completed' => round((float) $completed->sum(fn (Visit $visit) => (float) $visit->price), 2),
            'date_range' => [
                'from' => $firstVisit?->starts_at?->toDateTimeString(),
                'to' => $lastVisit?->starts_at?->toDateTimeString(),
            ],
        ];

        $this->command?->info('Org173BrowDemoSeeder summary:');
        $this->command?->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
