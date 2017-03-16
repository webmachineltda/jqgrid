<?php
namespace Webmachine\Jqgrid;

use Illuminate\Support\ServiceProvider;

class JqgridServiceProvider extends ServiceProvider {
    
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot() {        
        $this->publishes([
            __DIR__.'/config/config.php' => config_path('jqgrid.php'),
        ], 'config');
        
        $this->mergeConfigFrom(
            __DIR__.'/config/config.php', 'jqgrid'
        );        
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register() {
        return \App::bind('jqgrid', function(){
            return new Jqgrid();
        });
    }
}