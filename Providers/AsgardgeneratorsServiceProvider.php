<?php

namespace Modules\Asgardgenerators\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Asgardgenerators\Console\GenerateStructureCommand;

class AsgardgeneratorsServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->registerBindings();
        $this->registerViews();
        $this->registerGenerateStructureCommand();

        $this->commands([
          'asgard.generate.structure',
        ]);
    }

    public function boot()
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/config.php', 'asgard.generators.config');
        $this->publishes([__DIR__.'/../Config/config.php' => config_path('asgard.generators.config'.'.php')], 'config');
    }

    /**
     * Register the asgard:generate:structure command.
     *
     * return @void
     */
    private function registerGenerateStructureCommand()
    {
        $this->app->bindShared('asgard.generate.structure', function ($app) {
            return new GenerateStructureCommand(
              $app->make('Way\Generators\Generator'),
              $app->make('Way\Generators\Filesystem\Filesystem'),
              $app->make('Way\Generators\Compilers\TemplateCompiler'),
              $app->make('migration.repository'),
              $app->make('config')
            );
        });
    }

    /**
     * Register the views for publication.
     */
    private function registerViews()
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'asgardgenerators');

        $views = realpath(__DIR__.'/../Resources/views');

        $this->publishes([
          $views => $this->app->basePath().'/resources/views/vendor/asgardgenerators',
        ]);
    }

    /**
     * Register bindings for this module.
     */
    private function registerBindings()
    {
        // add bindings
    }
}
