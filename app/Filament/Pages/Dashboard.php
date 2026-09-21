<?php

namespace App\Filament\Pages;

use App\Models\SubscriptionPayment;
use App\Services\BillingDashboardReport;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

class Dashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;
    use WithPagination;

    protected static ?string $title = 'Подписки и оплаты';

    protected static ?string $navigationLabel = 'Подписки и оплаты';

    public function boot(): void
    {
        Gate::authorize('access-filament-admin');
    }

    public static function canAccess(): bool
    {
        return Gate::allows('access-filament-admin');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 3])->components([
            Select::make('period')->label('Период оплат')->options(fn () => $this->periodOptions())->default('12months')->selectablePlaceholder(false),
            Select::make('provider')->label('Магазин оплаты')->options(BillingDashboardReport::PROVIDERS)->placeholder('Все платформы'),
            Select::make('plan')->label('Тариф')->options(['basic' => 'Basic', 'pro' => 'Pro'])->placeholder('Все тарифы'),
            Select::make('customers')->label('Подписчики в таблице')->options(['all' => 'Все', 'active' => 'С действующей подпиской', 'inactive' => 'Без действующей подписки'])->default('all')->selectablePlaceholder(false),
            TextInput::make('search')->label('Поиск подписчика')->placeholder('ID, салон или email')->maxLength(100)->live(debounce: 400)->columnSpan(['md' => 2]),
        ]);
    }

    public function updatedFilters(): void
    {
        session()->put($this->getFiltersSessionKey(), $this->filters);
        $this->resetPage('customersPage');
        $this->resetPage('paymentsPage');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFiltersFormContentComponent(),
            View::make('filament.pages.billing-dashboard'),
        ]);
    }

    #[Computed]
    public function report(): array
    {
        Gate::authorize('access-filament-admin');

        return app(BillingDashboardReport::class)->build($this->filters ?? []);
    }

    public function rows(Collection $rows, string $pageName): LengthAwarePaginator
    {
        $page = min(max(1, $this->getPage($pageName)), max(1, (int) ceil($rows->count() / 15)));

        return new LengthAwarePaginator($rows->forPage($page, 15), $rows->count(), 15, $page, ['pageName' => $pageName]);
    }

    private function periodOptions(): array
    {
        $options = ['12months' => 'Последние 12 месяцев', 'all' => 'За всё время'];
        $start = now(BillingDashboardReport::TIMEZONE)->startOfMonth();
        $first = SubscriptionPayment::min('paid_at');
        $last = $first ? \Carbon\Carbon::parse($first, 'UTC')->setTimezone(BillingDashboardReport::TIMEZONE)->startOfMonth() : $start->copy()->subMonths(11);
        for ($month = $start; $month->gte($last); $month->subMonth()) {
            $options[$month->format('Y-m')] = $month->format('m.Y');
        }

        return $options;
    }
}
