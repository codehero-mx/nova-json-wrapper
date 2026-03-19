<?php

namespace CodeheroMx\JsonWrapper;

use Laravel\Nova\Nova;
use Laravel\Nova\Events\ServingNova;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the compiled Vue 3 component script with Nova.
 */
class JsonWrapperServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Nova::serving(function (ServingNova $event) {
            Nova::script('json-wrapper', __DIR__.'/../dist/js/field.js');
        });
    }

    public function register(): void
    {
        //
    }
}
