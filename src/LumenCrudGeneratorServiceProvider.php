<?php

namespace Funson86\LaravelCrudGenerator;


class LumenCrudGeneratorServiceProvider extends LaravelCrudGeneratorServiceProvider
{
    /**
     * Get the config path
     *
     * @return string
     */
    protected function getConfigPath()
    {
        return base_path('config/laravel-crud-generator.php');
    }
}
