<?php

namespace Funson86\LaravelCrudGenerator;

use Illuminate\Support\ServiceProvider;

class LaravelCrudGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/laravel-crud-generator.php' => $this->getConfigPath('laravel-crud-generator.php'),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
        $this->commands(
            'Funson86\LaravelCrudGenerator\Commands\CrudModel',
            'Funson86\LaravelCrudGenerator\Commands\CrudController'
        );

    }

    /**
     * Get the config path
     *
     * @return string
     */
    protected function getConfigPath()
    {
        return config_path('laravel-crud-generator.php');
    }
}
