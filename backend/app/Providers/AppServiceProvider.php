<?php

namespace App\Providers;

use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Customer\Policies\CustomerAddressPolicy;
use App\Domain\Identity\Listeners\AuthAuditSubscriber;
use App\Domain\Identity\Services\EmailVerificationCodeService;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Policies\OrderPolicy;
use App\Domain\Payments\Adapters\PayMongoPaymentGateway;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, PayMongoPaymentGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureDevCommands();
    }

    protected function configureAuthorization(): void
    {
        Gate::policy(CustomerAddress::class, CustomerAddressPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);

        Gate::define('access-backoffice', fn (User $user): bool => $user->isStaffOrAdmin());
        Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());

        Event::subscribe(AuthAuditSubscriber::class);

        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            /** @var User $notifiable */
            return app(EmailVerificationCodeService::class)->toMailMessage($notifiable, $url);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        URL::forceHttps(app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Register the Vite dev server pointing to the separated /frontend workspace.
     */
    protected function configureDevCommands(): void
    {
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            DevCommands::register('npm --prefix ../frontend run dev', 'vite');
        }
    }
}
