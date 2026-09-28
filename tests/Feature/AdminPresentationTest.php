<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminLocale;
use App\Support\AdminLabels;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminPresentationTest extends TestCase
{
    public function test_login_has_russian_labels_and_esticly_branding(): void
    {
        $this->get('/admin/login')->assertOk()
            ->assertSee('lang="ru"', false)
            ->assertSee('Esticly')->assertSee('Пароль')->assertSee('Войти')
            ->assertSee('esticly-admin.css')->assertSee('esticly-admin-mark.png');
    }

    public function test_admin_locale_does_not_leak_to_following_requests(): void
    {
        app()->setLocale('pl');
        Carbon::setLocale('pl');
        (new AdminLocale)->handle(Request::create('/admin'), function () {
            $this->assertSame('ru', app()->getLocale());
            $this->assertSame('ru', Carbon::getLocale());
            $this->assertSame('Подтверждена', AdminLabels::state('confirmed'));

            return response('ok');
        });
        $this->assertSame('pl', app()->getLocale());
        $this->assertSame('pl', Carbon::getLocale());
    }

    public function test_locale_is_restored_after_an_exception(): void
    {
        $previous = app()->getLocale();
        try {
            (new AdminLocale)->handle(Request::create('/admin'), fn () => throw new \RuntimeException('test'));
        } catch (\RuntimeException $e) {
            $this->assertSame('test', $e->getMessage());
        }
        $this->assertSame($previous, app()->getLocale());
    }

    public function test_resources_have_russian_names_without_changing_access(): void
    {
        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            $this->assertMatchesRegularExpression('/[А-Яа-я]/u', $resource::getNavigationLabel());
            $this->assertMatchesRegularExpression('/[А-Яа-я]/u', $resource::getPluralModelLabel());
            $this->assertFalse($resource::canAccess());
        }
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_status_formatting_preserves_unknown_values(): void
    {
        app()->setLocale('ru');
        $this->assertSame('В очереди', AdminLabels::state('queued'));
        $this->assertSame('Экспорт клиента', AdminLabels::state('export_client'));
        $this->assertSame('new_provider_status', AdminLabels::state('new_provider_status'));
        $this->assertSame('—', AdminLabels::state(null));
    }
}
