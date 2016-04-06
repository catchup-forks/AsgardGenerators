<?php

namespace Modules\Asgardgenerators\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Modules\Asgardgenerators\Exceptions\NothingToGenerateException;
use Modules\Asgardgenerators\Generators\ControllersGenerator;
use Modules\Asgardgenerators\Generators\DatabaseInformation;
use Modules\Asgardgenerators\Generators\MigrationsGenerator;
use Modules\Asgardgenerators\Generators\EloquentModelsGenerator;
use Modules\Asgardgenerators\Generators\RepositoryGenerator;
use Modules\Asgardgenerators\Generators\TranslationsGenerator;
use Modules\Asgardgenerators\Generators\ViewsGenerator;
use Pingpong\Modules\Module;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Config\Repository as Config;
use Way\Generators\Compilers\TemplateCompiler;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Generator;
use User11001\EloquentModelGenerator\Console\SchemaGenerator;

class GenerateStructureCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'asgard:generate:structure';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate migration, entities and resources for a list of given comma seperated tables eg: users,comments.';

    /**
     * @var Module
     */
    protected $module;

    /**
     * @var \Way\Generators\Generator
     */
    protected $generator;

    /**
     * @var \Way\Generators\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Way\Generators\Compilers\TemplateCompiler
     */
    protected $compiler;

    /**
     * @var \Illuminate\Database\Migrations\MigrationRepositoryInterface
     */
    protected $repository;

    /**
     * @var \Illuminate\Config\Repository
     */
    protected $config;

    /**
     * @var SchemaGenerator
     */
    protected $schemaGenerator;

    /**
     * @var DatabaseInformation
     */
    protected $databaseInformation;

    /**
     * Tables the generators should work with.
     *
     * @var null|array
     */
    protected $tables = null;

    /**
     * List of excluded tables.
     *
     * @var array
     */
    protected $excludes = [
      'migrations',
    ];

    /**
     * List of options provided by the user.
     *
     * @var array
     */
    protected $options = [];

    /**
     * Create a new command instance.
     *
     * @param \Way\Generators\Generator                                    $generator
     * @param \Way\Generators\Filesystem\Filesystem                        $filesystem
     * @param \Way\Generators\Compilers\TemplateCompiler                   $compiler
     * @param \Illuminate\Database\Migrations\MigrationRepositoryInterface $repository
     * @param \Illuminate\Config\Repository                                $config
     */
    public function __construct(
      Generator $generator,
      Filesystem $filesystem,
      TemplateCompiler $compiler,
      MigrationRepositoryInterface $repository,
      Config $config
    ) {
        $this->generator = $generator;
        $this->filesystem = $filesystem;
        $this->compiler = $compiler;
        $this->repository = $repository;
        $this->config = $config;

        parent::__construct();
    }

    /**
     * Initialize a list of given command options with their default values.
     *
     * @return $this
     */
    protected function initOptions()
    {
        $module = $this->module->getStudlyName();
        $ns = "Modules\\{$module}";

        $this->options = [
          'connection' => $this->getOption('connection', null),
          'ignore' => $this->getOption('ignore', []),
          'path' => $this->getOption('path', ''),
          'templatePath' => $this->getOption('templatePath', ''),
          'namespace' => $this->getOption('namespace',
            $ns),
          'defaultIndexNames' => $this->getOption('defaultIndexNames', false),
          'defaultFKNames' => $this->getOption('defaultFKNames', false),
          'overwrite' => $this->getOption('overwrite', false),
        ];

        return $this;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        // create a module object
        // or fail (stop execution)
        $this->module = \Module::findOrFail($this->argument('module'));

        $this->capitalizeServiceProviderFilenames();

        // initialize the options with their default values
        $this->initOptions();

        // create a schema generator
        $this->initSchemaGenerator();

        // create a new database information instance
        $this->databaseInformation = new DatabaseInformation(
          $this->schemaGenerator,
          $this->getTables()
        );

        // the available generators
        $generators = [
          'migrations' => MigrationsGenerator::class,
          'models' => EloquentModelsGenerator::class,
          'repositories' => RepositoryGenerator::class,
          'views' => ViewsGenerator::class,
          'controllers' => ControllersGenerator::class,
          'translations' => TranslationsGenerator::class,
        ];

        foreach ($generators as $name => $class) {
            if ($this->shouldGenerate($name)) {
                $generator = new $class(
                  $this->module,
                  $this->generator,
                  $this->filesystem,
                  $this->compiler,
                  $this->config,
                  $this->databaseInformation,
                  $this->options
                );

                $generator->execute();
            }
        }


    }

    /**
     * Ensures all Service Provider filenames start with a capital letter
     */
    private function capitalizeServiceProviderFilenames() {
        $modulePath = $this->module->getPath();
        $serviceProviderPath = $modulePath . '/Providers';

        $fileNames = scandir($serviceProviderPath);

        foreach($fileNames as $fileName) {
            if(ends_with($fileName, '.php')) {
                $capitalized = ucfirst($fileName);

                $oldPath = $serviceProviderPath . '/' . $fileName;
                $capitalizedPath = $serviceProviderPath . '/' . $capitalized;
                rename($oldPath, $capitalizedPath);
            }
        }

        die($modulePath);
    }

    /**
     * Create a list of tables the structure should be generated for.
     *
     * @return array
     */
    protected function getTables()
    {
        // check if the tables are set
        // if so return the tables
        if ($this->tables) {
            return $this->tables;
        }

        // read the table argument
        // if table argument empty get list of all tables in db
        $this->initSchemaGenerator();

        $tables_in_db = $this->schemaGenerator->getTables();

        if ($this->option('tables')) {
            $tables = explode(',', $this->option('tables'));

            foreach ($tables as $index => $table) {
                if (!in_array($table, $tables_in_db)) {
                    unset($tables[$index]);
                }
            }
        } else {
            $tables = $tables_in_db;
        }

        // return array of creatable tables
        $tables = $this->removeExcludedTables($tables);

        if (empty($tables)) {
            throw new NothingToGenerateException('There is nothing to generate.');
        }

        return $tables;
    }

    /**
     * Remove all the tables to exclude from the array of tables.
     *
     * @param $tables
     *
     * @return array
     */
    protected function removeExcludedTables($tables)
    {
        $excludes = $this->getExcludedTables();
        $tables = array_diff($tables, $excludes);

        return $tables;
    }

    /**
     * Get a list of tables to exclude.
     *
     * @return array
     */
    protected function getExcludedTables()
    {
        $ignore = $this->option('ignore');
        if (!empty($ignore)) {
            return array_merge($this->excludes, explode(',', $ignore));
        }

        return $this->excludes;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
          [
            'module',
            InputArgument::REQUIRED,
            'The module you would like to generate the resources for.',
          ],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
          [
            'only',
            'only',
            InputOption::VALUE_OPTIONAL,
            'A comma separated list of generators to run: migrations,models,views,controllers,repositories',
          ],
          [
            'tables',
            't',
            InputOption::VALUE_OPTIONAL,
            'A list of Tables you wish to Generate Migrations for separated by a comma: users,posts,comments',
          ],
          [
            'ignore',
            'i',
            InputOption::VALUE_OPTIONAL,
            'A list of Tables you wish to ignore, separated by a comma: users,posts,comments',
          ],
          [
            'overwrite',
            'o',
            InputOption::VALUE_NONE,
              // @todo: ensure migrations are deleted
            'Overwrite existing generated files',
          ],
          [
            'path',
            'p',
            InputOption::VALUE_OPTIONAL,
            'Where should the file be created?',
          ],
          [
            'templatePath',
            'tp',
            InputOption::VALUE_OPTIONAL,
            'The location of the template for this generator',
          ],
          [
            'namespace',
            'ns',
            InputOption::VALUE_OPTIONAL,
            'The base namespace the files should adhere to',
          ],
          [
            'connection',
            'c',
            InputOption::VALUE_OPTIONAL,
            'The database connection to use.',
            $this->config->get('database.default'),
          ],
          [
            'defaultIndexNames',
            null,
            InputOption::VALUE_NONE,
            'Don\'t use db index names for migrations',
          ],
          [
            'defaultFKNames',
            null,
            InputOption::VALUE_NONE,
            'Don\'t use db foreign key names for migrations',
          ],
        ];
    }

    /**
     * Retrieve the value of an option or default value for the key.
     *
     * @param string $key
     * @param null   $default
     */
    private function getOption($key, $default = null)
    {
        return $this->option($key) ?: $default;
    }

    /**
     * Initialize a schemaGenerator object.
     *
     * @return \User11001\EloquentModelGenerator\Console\SchemaGenerator
     */
    private function initSchemaGenerator()
    {
        if (!$this->schemaGenerator) {
            $this->schemaGenerator = new SchemaGenerator(
              $this->option('connection'),
              $this->option('defaultIndexNames'),
              $this->option('defaultFKNames')
            );
        }

        return $this->schemaGenerator;
    }

    /**
     * Check if the requested migration should be run or skipped.
     *
     * @param string $string
     *
     * @return bool
     */
    private function shouldGenerate($string)
    {
        $only_generate = $this->getOption('only', null);

        // no restriction was provided
        // generate everything
        if (is_null($only_generate)) {
            return true;
        }

        // because the input is a comma separated string force to an array
        $only_generate = explode(',', $only_generate);

        // check if the string is in the array
        return in_array($string, $only_generate);
    }
}
