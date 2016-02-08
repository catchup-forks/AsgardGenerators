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
        $this->registerGenerateStructureCommand();

        $this->commands([
          'command.bitsoflove.structure'
        ]);

    }

    private function registerGenerateStructureCommand()
    {

        $this->app->bindShared('command.bitsoflove.structure', function ($app) {
            return new GenerateStructureCommand(
              $app['config']
            );
        });
    }
}
