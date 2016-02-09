<?php namespace Modules\Asgardgenerators\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Modules\Asgardgenerators\Generators\MigrationGenerator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Config\Repository as Config;
use Way\Generators\Compilers\TemplateCompiler;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Generator;
use Xethron\MigrationsGenerator\Generators\SchemaGenerator;

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
     * Tables the generators should work with
     *
     * @var null|array
     */
    protected $tables = null;

    /**
     * List of excluded tables
     *
     * @var array
     */
    protected $excludes = [
      'migrations'
    ];

    /**
     * List of options provided by the user
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
     * Initialize a list of given command options with their default values
     *
     * @return $this
     */
    protected function initOptions()
    {
        $this->options = [
          'connection'        => $this->getOption('connection', null),
          'ignore'            => $this->getOption('ignore', []),
          'path'              => $this->getOption('path', ''),
          'templatePath'      => $this->getOption('templatePath', ''),
          'defaultIndexNames' => $this->getOption('defaultIndexNames', false),
          'defaultFKNames'    => $this->getOption('defaultFKNames', false)
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
        $this->initOptions();

        // generate the migrations
        $migrationGenerator = new MigrationGenerator(
          $this->generator,
          $this->filesystem,
          $this->config,
          $this->getTables(),
          $this->options
        );

        $migrationGenerator->execute();
    }

    /**
     * Create a list of tables the structure should be generated for
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
        $this->schemaGenerator = new SchemaGenerator(
          $this->option('connection'),
          $this->option('defaultIndexNames'),
          $this->option('defaultFKNames')
        );

        if ($this->argument('tables')) {
            $tables = explode(',', $this->argument('tables'));
        } else {
            $tables = $this->schemaGenerator->getTables();
        }

        // return array of creatable tables
        return $this->removeExcludedTables($tables);
    }

    /**
     * Remove all the tables to exclude from the array of tables
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
     * Get a list of tables to exclude
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
            'tables',
            InputArgument::OPTIONAL,
            'A list of Tables you wish to Generate Migrations for separated by a comma: users,posts,comments'
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
            'connection',
            'c',
            InputOption::VALUE_OPTIONAL,
            'The database connection to use.',
            $this->config->get('database.default')
          ],
          [
            'ignore',
            'i',
            InputOption::VALUE_OPTIONAL,
            'A list of Tables you wish to ignore, separated by a comma: users,posts,comments'
          ],
          [
            'path',
            'p',
            InputOption::VALUE_OPTIONAL,
            'Where should the file be created?'
          ],
          [
            'templatePath',
            'tp',
            InputOption::VALUE_OPTIONAL,
            'The location of the template for this generator'
          ],
          [
            'defaultIndexNames',
            null,
            InputOption::VALUE_NONE,
            'Don\'t use db index names for migrations'
          ],
          [
            'defaultFKNames',
            null,
            InputOption::VALUE_NONE,
            'Don\'t use db foreign key names for migrations'
          ],
        ];
    }

    /**
     * Retrieve the value of an option or default value for the key
     *
     * @param string $key
     * @param null   $default
     * @return null
     */
    private function getOption($key, $default = null)
    {
        return $this->option($key) ?: $default;
    }
}
