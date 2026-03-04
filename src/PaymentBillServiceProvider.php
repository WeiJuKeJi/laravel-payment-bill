<?php

namespace WeiJuKeJi\PaymentBill;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class PaymentBillServiceProvider extends ServiceProvider
{
    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->loadMigrations();
        $this->loadRoutes();
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/payment-bill.php', 'payment-bill');
    }

    /**
     * Register publishing assets.
     */
    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/payment-bill.php' => config_path('payment-bill.php'),
        ], 'payment-bill-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'payment-bill-migrations');
    }

    /**
     * Register commands.
     */
    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Console\Commands\DownloadBillsCommand::class,
            Console\Commands\ImportBillsCommand::class,
            Console\Commands\ImportLocalBillFilesCommand::class,
        ]);
    }

    /**
     * Register command schedules.
     */
    protected function registerCommandSchedules(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // 检查是否启用定时任务
        if (! config('payment-bill.schedules.enabled', true)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            // 注册账单下载定时任务
            if (config('payment-bill.schedules.download.enabled', true)) {
                $downloadTime = config('payment-bill.schedules.download.time', '02:00');
                $downloadTimezone = config('payment-bill.schedules.download.timezone')
                    ?? config('app.timezone', 'Asia/Shanghai');

                $schedule->command('payment-bill:download')
                    ->dailyAt($downloadTime)
                    ->timezone($downloadTimezone)
                    ->withoutOverlapping()
                    ->runInBackground()
                    ->monitorName('支付账单-自动下载');
            }

            // 注册账单导入定时任务
            if (config('payment-bill.schedules.import.enabled', true)) {
                $importTime = config('payment-bill.schedules.import.time', '02:30');
                $importTimezone = config('payment-bill.schedules.import.timezone')
                    ?? config('app.timezone', 'Asia/Shanghai');

                $schedule->command('payment-bill:import')
                    ->dailyAt($importTime)
                    ->timezone($importTimezone)
                    ->withoutOverlapping()
                    ->runInBackground()
                    ->monitorName('支付账单-自动导入');
            }
        });
    }

    /**
     * Load package migrations.
     */
    protected function loadMigrations(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Load package routes.
     */
    protected function loadRoutes(): void
    {
        if (! $this->app->routesAreCached()) {
            require __DIR__.'/../routes/api.php';
        }
    }
}
