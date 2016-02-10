<?php

namespace Modules\Asgardgenerators\Generators;

use Modules\Asgardgenerators\Contracts\Generators\BaseGenerator;
use Modules\Asgardgenerators\Contracts\Generators\GeneratorInterface;
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
        echo "Setting up Tables and Index Migrations\n";
        $this->datePrefix = date('Y_m_d_His');
        $this->generate('create', $this->tables->getTables());

        echo "\nSetting up Foreign Key Migrations\n";
        $this->datePrefix = date('Y_m_d_His', strtotime('+1 second'));
        $this->generate('foreign_keys', $this->tables->getTables());
        echo "\nFinished!\n";
    }

    /**
     * Generate the migration for the given tables
     *
     * @param string $method
     * @param array  $tables
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
            $this->migrationName = $prefix . '_' . $table . '_table';
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

                echo "Generated {$filePathToGenerate} \n";
            }
        }
    }

    /**
     * Create a full path + filename for the to generate
     *
     * @return string
     */
    public function getFileGenerationPath()
    {
        // retrieve the generation path from the path option if exists
        $path = $this->module->getPath() . DIRECTORY_SEPARATOR . "Migrations";

        $fileName = $this->getDatePrefix() . '_' . $this->migrationName . '.php';

        return "{$path}/{$fileName}";
    }

    /**
     * Create the needed data
     *
     * @return array
     */
    public function getTemplateData()
    {
        if ($this->method == 'create') {
            $up = (new AddToTable($this->filesystem,
              $this->compiler))->run($this->fields, $this->table, 'create');
            $down = (new DroppedTable)->drop($this->table);
        } else {
            $up = (new AddForeignKeysToTable($this->filesystem,
              $this->compiler))->run($this->fields, $this->table);
            $down = (new RemoveForeignKeysFromTable($this->filesystem,
              $this->compiler))->run($this->fields, $this->table);
        }

        return [
          'CLASS' => ucwords(camel_case($this->migrationName)),
          'UP'    => $up,
          'DOWN'  => $down
        ];
    }

    /**
     * Get path to template for generator
     *
     * @return string
     */
    public function getTemplatePath()
    {
        if (!empty($this->options['templatePath'])) {
            return $this->options['templatePath'];
        }

        return config('asgard.asgardgenerators.config.migration.template', "");
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
}