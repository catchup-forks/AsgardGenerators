<?php namespace Modules\Asgardgenerators\Providers;

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
     *
     * @return void
     */
    public function register()
    {
        $this->registerBindings();
        $this->registerGenerateStructureCommand();

        $this->commands([
          'asgard.generate.structure'
        ]);

    }

    public function boot(){
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'asgard.generators.config');
        $this->publishes([__DIR__ . '/../Config/config.php' => config_path('asgard.generators.config' . '.php'), ], 'config');
    }

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

    private function registerBindings()
    {
// add bindings
    }
}
