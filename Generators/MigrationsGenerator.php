<?php

namespace Modules\AsgardGenerators\Generators;

use Modules\AsgardGenerators\Contracts\Generators\BaseGenerator;
use Modules\AsgardGenerators\Contracts\Generators\GeneratorInterface;
use Pingpong\Modules\Module;
use Way\Generators\Compilers\TemplateCompiler;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Generator;
use Way\Generators\Syntax\DroppedTable;
use Xethron\MigrationsGenerator\Generators\SchemaGenerator;
use Xethron\MigrationsGenerator\MethodNotFoundException;
use Xethron\MigrationsGenerator\Syntax\AddForeignKeysToTable;
use Xethron\MigrationsGenerator\Syntax\AddToTable;
use Xethron\MigrationsGenerator\Syntax\RemoveForeignKeysFromTable;

class MigrationsGenerator extends BaseGenerator implements GeneratorInterface
{
    /**
     * @var \Xethron\MigrationsGenerator\Generators\SchemaGenerator
     */
    protected $schemaGenerator;

    /**
     * @param \Way\Generators\Generator             $generator
     * @param \Way\Generators\Filesystem\Filesystem $filesystem
     * @param \Illuminate\Config\Repository         $config
     * @param DatabaseInformation                   $tables
     * @param array                                 $options
     */
    public function __construct(
      Module $module,
      Generator $generator,
      Filesystem $filesystem,
      TemplateCompiler $compiler,
      $config,
      $tables,
      $options
    ) {
        parent::__construct(
          $module,
          $generator,
          $filesystem,
          $compiler,
          $config,
          $tables,
          $options
        );

        $this->schemaGenerator = new SchemaGenerator(
          $this->options['connection'],
          $this->options['defaultIndexNames'],
          $this->options['defaultFKNames']
        );
    }

    /**
     * @throws \Xethron\MigrationsGenerator\MethodNotFoundException
     */
    public function execute()
    {
        echo "\nGenerating Migrations\n";
        $this->datePrefix = date('Y_m_d_His');
        $this->generate('create', $this->tables->getTables());

        $this->datePrefix = date('Y_m_d_His', strtotime('+1 second'));
        $this->generate('foreign_keys', $this->tables->getTables());

        $this->addToPublishList();
    }

    /**
     * Generate the migration for the given tables.
     *
     * @param string $method
     * @param array  $tables
     *
     * @throws \Xethron\MigrationsGenerator\MethodNotFoundException
     */
    protected function generate($method, $tables)
    {
        if ($method == 'create') {
            $function = 'getFields';
            $prefix = 'create';
        } elseif ($method = 'foreign_keys') {
            $function = 'getForeignKeyConstraints';
            $prefix = 'add_foreign_keys_to';
            $method = 'table';
        } else {
            throw new MethodNotFoundException($method);
        }

        foreach ($tables as $table) {
            $this->migrationName = $prefix.'_'.$table.'_table';
            $this->method = $method;
            $this->table = $table;
            $this->fields = $this->schemaGenerator->{$function}($table);

            if ($this->fields) {
                $filePathToGenerate = $this->getFileGenerationPath();

                $this->generator->make(
                  $this->getTemplatePath(),
                  $this->getTemplateData(),
                  $filePathToGenerate
                );

                echo "File {$filePathToGenerate} generated.\n";
            }
        }
    }

    /**
     * Create a full path + filename for the to generate.
     *
     * @return string
     */
    public function getFileGenerationPath()
    {
        // retrieve the generation path from the path option if exists
        $path = $this->module->getPath().DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR,
            [
              'Database',
              'Migrations',
            ]);

        $fileName = $this->getDatePrefix().'_'.$this->migrationName.'.php';

        return "{$path}/{$fileName}";
    }

    /**
     * Create the needed data.
     *
     * @return array
     */
    public function getTemplateData()
    {
        if ($this->method == 'create') {
            $up = (new AddToTable($this->filesystem,
              $this->compiler))->run($this->fields, $this->table, 'create');
            $down = (new DroppedTable())->drop($this->table);
        } else {
            $up = (new AddForeignKeysToTable($this->filesystem,
              $this->compiler))->run($this->fields, $this->table);
            $down = (new RemoveForeignKeysFromTable($this->filesystem,
              $this->compiler))->run($this->fields, $this->table);
        }

        return [
          'CLASS' => ucwords(camel_case($this->migrationName)),
          'UP' => $up,
          'DOWN' => $down,
        ];
    }

    /**
     * Get path to template for generator.
     *
     * @return string
     */
    public function getTemplatePath()
    {
        if (!empty($this->options['templatePath'])) {
            return $this->options['templatePath'];
        }

        return config('asgard.asgardgenerators.config.migration.template', '');
    }

    /**
     * Get the date prefix for the migration.
     *
     * @return string
     */
    protected function getDatePrefix()
    {
        return $this->datePrefix;
    }

    /**
     * Ensure the database migrations are being publishable.
     *
     * @throws \Way\Generators\Filesystem\FileAlreadyExists
     * @throws \Way\Generators\Filesystem\FileNotFound
     */
    private function addToPublishList()
    {
        $replace = '// add bindings';

        $data = "\$migrations = realpath(__DIR__.'/../Database/Migrations');\n\n"
          ."\$this->publishes([
          \$migrations => \$this->app->databasePath().'/migrations',
        ], 'migrations');\n\n";

        // add a replacement pointer to the end of the file to ensure further changes
        $data .= "\n$replace\n";

        // write the file
        $file = $this->module->getPath().DIRECTORY_SEPARATOR.'Providers'.DIRECTORY_SEPARATOR.$this->module->getName().'ServiceProvider.php';

        $content = $this->filesystem->get($file);
        $content = str_replace("$replace", $data, $content);

        if ($this->filesystem->exists($file)) {
            unlink($file);
        }

        $this->filesystem->make($file, $content);
    }
}
