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

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            // 每天凌晨 2 点下载前一天的账单
            $schedule->command('payment-bill:download')
                ->dailyAt('02:00')
                ->timezone(config('app.timezone', 'Asia/Shanghai'))
                ->withoutOverlapping()
                ->runInBackground()
                ->monitorName('支付账单-自动下载');

            // 每天凌晨 2:30 导入下载完成的账单
            $schedule->command('payment-bill:import')
                ->dailyAt('02:30')
                ->timezone(config('app.timezone', 'Asia/Shanghai'))
                ->withoutOverlapping()
                ->runInBackground()
                ->monitorName('支付账单-自动导入');
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
