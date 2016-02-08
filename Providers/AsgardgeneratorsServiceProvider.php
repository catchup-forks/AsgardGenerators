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

//        new GenerateStructureCommand()

        $this->registerGenerateStructureCommand();

        $this->commands([
            'command.bitsoflove.structure'
        ]);

    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array();
    }

    private function registerBindings()
    {
// add bindings
    }

    private function registerGenerateStructureCommand()
    {

    $this->app->bindShared('command.bitsoflove.structure', function($app){
        return new GenerateStructureCommand();
    });


//        $this->app->bindShared('command.asgard.module.update', function ($app) {
//            return new UpdateModuleCommand(new Composer($app['files'], base_path()));
//        });

    }
}
