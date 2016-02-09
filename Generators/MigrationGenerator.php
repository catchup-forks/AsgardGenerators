<?php

namespace Modules\Asgardgenerators\Generators;

use Modules\Asgardgenerators\Contracts\Generators\GeneratorInterface;
use Way\Generators\Compilers\TemplateCompiler;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Generator;
use Way\Generators\Syntax\DroppedTable;
use Xethron\MigrationsGenerator\Generators\SchemaGenerator;
use Xethron\MigrationsGenerator\MethodNotFoundException;
use Xethron\MigrationsGenerator\Syntax\AddForeignKeysToTable;
use Xethron\MigrationsGenerator\Syntax\AddToTable;
use Xethron\MigrationsGenerator\Syntax\RemoveForeignKeysFromTable;

class MigrationGenerator implements GeneratorInterface
{

    /**
     * @var \Way\Generators\Generator
     */
    protected $generator;

    /**
     * @var \Way\Generators\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Modules\Asgardgenerators\Generators\TemplateCompiler
     */
    protected $compiler;

    /**
     * @var \Illuminate\Config\Repository
     */
    protected $config;

    /**
     * @var array
     */
    protected $tables;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var \Xethron\MigrationsGenerator\Generators\SchemaGenerator
     */
    protected $schemaGenerator;

    /**
     * @param \Way\Generators\Generator             $generator
     * @param \Way\Generators\Filesystem\Filesystem $filesystem
     * @param \Illuminate\Config\Repository         $config
     * @param array                                 $tables
     * @param array                                 $options
     */
    public function __construct(
      Generator $generator,
      Filesystem $filesystem,
      TemplateCompiler $compiler,
      $config,
      $tables,
      $options
    ) {
        $this->generator = $generator;
        $this->filesystem = $filesystem;
        $this->compiler = $compiler;
        $this->config = $config;
        $this->tables = $tables ?: [];
        $this->options = $options;

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
        $this->generate('create', $this->tables);

        echo "\nSetting up Foreign Key Migrations\n";
        $this->datePrefix = date('Y_m_d_His', strtotime('+1 second'));
        $this->generate('foreign_keys', $this->tables);
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
    protected function getFileGenerationPath()
    {
        // retrieve the generation path from the path option if exists
        if (!empty($this->options['path'])) {
            $path = $this->options['path'];
        } else {
            $path = config('asgard.asgardgenerators.config.migration.output_path',
              "");
        }

        $fileName = $this->getDatePrefix() . '_' . $this->migrationName . '.php';

        return "{$path}/{$fileName}";
    }

    /**
     * Create the needed data
     *
     * @return array
     */
    protected function getTemplateData()
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
    protected function getTemplatePath()
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