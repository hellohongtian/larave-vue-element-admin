<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use DB;
class AppServiceProvider extends ServiceProvider
{


    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (in_array($_SERVER['SITE_ENV'],['production','testing'])) {
            URL::forceSchema('https');
            app('request')->server->set('HTTPS', 'on');
        }
        DB::listen(function ($query) {
             echo $query->sql;
            // $query->bindings
            // $query->time
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
       
    }
}
