<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemStatus extends Widget
{
    protected static string $view = 'filament.widgets.system-status';
    protected static ?int $sort = -2;                   // di atas WelcomeCard
    protected int|string|array $columnSpan = ['lg' => 1]; // 1 kolom di ≥lg

    /**
     * @return array{
     *   app:string,env:string,url:?string,php:string,laravel:string,
     *   tz:string,now:string,db_ok:bool,db_error:?string,codename:?string
     * }
     */
    protected function getViewData(): array
    {
        $dbOk = false;
        $err  = null;

        try {
            DB::select('select 1');
            $dbOk = true;
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }

        return [
            'app'      => (string) config('app.name'),
            'env'      => (string) config('app.env'),
            'url'      => config('app.url'),
            'php'      => PHP_VERSION,
            'laravel'  => app()->version(),
            'tz'       => (string) config('app.timezone', 'UTC'),
            'now'      => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'db_ok'    => $dbOk,
            'db_error' => $err,
            'codename' => env('APP_CODENAME'), // <= badge “reliable-luck”
        ];
    }
}
