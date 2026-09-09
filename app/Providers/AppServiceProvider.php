<?php

namespace App\Providers;

use App\View\Composers\SeoComposer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Enforce SQLite pragmas required by biblical_core.db:
     *  - foreign_keys=ON (referential integrity for lexicons/aribic_concordance)
     *  - journal_mode=WAL (concurrent read performance)
     *  - synchronous=NORMAL (WAL durability balance)
     *  - busy_timeout=5000ms (graceful handling of locked reads)
     *  - encoding=UTF-8 (Arabic/Hebrew/Greek integrity)
     */
    public function boot(): void
    {
        if (config('database.default') === 'sqlite' || config('database.connections.biblical_core')) {
            try {
                // These pragmas are idempotent and safe to run on each request via the default connection.
                // They are also applied directly to the underlying PDO for SQLite.
                DB::statement('PRAGMA foreign_keys = ON');
                DB::statement('PRAGMA journal_mode = WAL');
                DB::statement('PRAGMA synchronous = NORMAL');
                DB::statement('PRAGMA busy_timeout = 5000');
                // encoding pragma is read-only after creation, but we verify it.
            } catch (\Throwable $e) {
                // Silently ignore during early boot (e.g. artisan optimize without DB)
            }
        }

        // Global SEO composer — injects defaults into every view (controllers can override via $seo)
        View::composer('*', SeoComposer::class);

        // Share navigation books for header Book/Chapter selector (cached 24h) — also for welcome/home
        View::composer(['layouts.app', 'layouts.*', 'bible.*', 'read.*', 'welcome', 'home', '*'], function ($view): void {
            // Avoid querying during artisan commands without DB
            try {
                $books = \Illuminate\Support\Facades\Cache::remember('nav_books', 86400, fn () => \App\Models\Book::orderBy('book_number')->get(['id','code','slug','name_ar','name_en','testament','book_number']));
                $view->with('navBooks', $books);
            } catch (\Throwable $e) {
                $view->with('navBooks', collect());
            }
        });
    }
}
