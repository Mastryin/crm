<?php

namespace Webkul\EduCRM\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Core\Acl;
use Webkul\Core\Menu;

class EduCRMServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/admin.php');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'educrm');

        $this->publishes([
            __DIR__ . '/../Config/educrm.php' => config_path('educrm.php'),
        ], 'educrm-config');

        $this->registerEventListeners();
        $this->registerAcl();
        $this->registerMenu();
        $this->registerScheduledTasks();
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/educrm.php', 'educrm');

        $this->app->singleton('lead.qualification', function ($app) {
            return new \Webkul\EduCRM\Services\LeadQualificationService();
        });

        $this->app->singleton('lead.assignment', function ($app) {
            return new \Webkul\EduCRM\Services\RoundRobinAssignmentService();
        });

        $this->app->singleton('notification.omnichannel', function ($app) {
            return new \Webkul\EduCRM\Services\OmnichannelNotificationService();
        });

        $this->app->singleton('lead.capture', function ($app) {
            return new \Webkul\EduCRM\Services\LeadCaptureService(
                $app->make('lead.qualification'),
                $app->make('lead.assignment'),
                $app->make(\Webkul\EduCRM\Services\LeadDeduplicationService::class)
            );
        });

        $this->app->singleton('webhook.integration', function ($app) {
            return new \Webkul\EduCRM\Services\WebhookIntegrationService();
        });
    }

    protected function registerEventListeners()
    {
        Event::listen('lead.create.after', [\Webkul\EduCRM\Listeners\LeadEventListener::class, 'afterCreate']);
        Event::listen('lead.update.after', [\Webkul\EduCRM\Listeners\LeadEventListener::class, 'afterUpdate']);
        Event::listen('lead.delete.before', [\Webkul\EduCRM\Listeners\LeadEventListener::class, 'beforeDelete']);
    }

    protected function registerAcl()
    {
        $acl = app(Acl::class);

        foreach (include __DIR__ . '/../Config/acl.php' as $item) {
            $acl->add($item);
        }
    }

    protected function registerMenu()
    {
        $menu = app(Menu::class);

        foreach (include __DIR__ . '/../Config/menu.php' as $item) {
            $menu->add($item);
        }
    }

    protected function registerScheduledTasks()
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            $schedule->job(new \Webkul\EduCRM\Jobs\SendPaymentReminders)
                ->dailyAt('09:00')
                ->name('educrm:send-payment-reminders')
                ->withoutOverlapping();

            $schedule->job(new \Webkul\EduCRM\Jobs\CheckCohortDeadlines)
                ->dailyAt('08:00')
                ->name('educrm:check-cohort-deadlines')
                ->withoutOverlapping();

            $schedule->job(new \Webkul\EduCRM\Jobs\CleanupTrashLeads)
                ->daily()
                ->name('educrm:cleanup-trash-leads')
                ->withoutOverlapping();

            $schedule->job(new \Webkul\EduCRM\Jobs\ResetDailyAssignmentCounters)
                ->dailyAt('00:00')
                ->name('educrm:reset-daily-counters')
                ->withoutOverlapping();

            $schedule->job(new \Webkul\EduCRM\Jobs\SendCallReminders)
                ->everyFiveMinutes()
                ->name('educrm:send-call-reminders')
                ->withoutOverlapping();
        });
    }
}
