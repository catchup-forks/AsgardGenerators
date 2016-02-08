<?php namespace Modules\Asgardgenerators\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Modules\Asgardgenerators\Generators\MigrationGenerator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Config\Repository as Config;
use Way\Generators\Commands\GeneratorCommand;
use Way\Generators\Compilers\TemplateCompiler;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Generator;
use Xethron\MigrationsGenerator\Generators\SchemaGenerator;

class GenerateStructureCommand extends GeneratorCommand
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'bitsoflove:structure';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';

    protected $config;

    protected $filesystem;

    protected $compiler;

    protected $repository;

    protected $schemaGenerator;

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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(
      Generator $generator,
      Filesystem $filesystem,
      TemplateCompiler $compiler,
      MigrationRepositoryInterface $repository,
      Config $config
    ) {
        $this->compiler = $compiler;

        $this->repository = $repository;

        $this->config = $config;

        $this->filesystem = $filesystem;

        parent::__construct($generator);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        // generate the migrations
        $migrationGenerator = new MigrationGenerator(
          $this->generator,
          $this->filesystem,
          $this->config,
          $this->getTables()
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
     * Fetch the template data.
     *
     * @return array
     */
    protected function getTemplateData()
    {
        // TODO: Implement getTemplateData() method.
    }

    /**
     * The path to where the file will be created.
     *
     * @return mixed
     */
    protected function getFileGenerationPath()
    {
        // TODO: Implement getFileGenerationPath() method.
    }

    /**
     * Get the path to the generator template.
     *
     * @return mixed
     */
    protected function getTemplatePath()
    {
        // TODO: Implement getTemplatePath() method.
    }
}
