<?php

namespace Modules\Asgardgenerators\Generators;

use Modules\Asgardgenerators\Contracts\Generators\BaseGenerator;
use Modules\Asgardgenerators\Contracts\Generators\GeneratorInterface;

class RepositoryGenerator extends BaseGenerator implements GeneratorInterface
{
    protected $generated = [];

    /**
     * Execute the generator.
     */
    public function execute()
    {
        foreach ($this->tables->getInfo() as $table => $columns) {
            $entity = $this->entityNameFromTable($table);

            $this->generateRepositoriesFor($entity);

            $this->generated[] = $entity;
        }

        $this->registerWithProvider();
    }

    /**
     * Full path to the required template file.
     *
     * @return string
     */
    public function getTemplatePath()
    {
        $path = $this->getOption('templatePath', null);

        if (is_null($path)) {
            $path = config('asgard.asgardgenerators.config.repositories.template',
              '');
        } else {
            $path .= 'repository';
        }

        return $path;
    }

    /**
     * Create the data used in the template file.
     *
     * @return array
     */
    public function getTemplateData()
    {
        return [
        ];
    }

    /**
     * Full path to the output file.
     *
     * @return string
     */
    public function getFileGenerationPath()
    {
        $path = $this->module->getPath().DIRECTORY_SEPARATOR.'Repositories';

        return $path;
    }

    /**
     * @param string $entity
     */
    public function generateRepositoriesFor($entity)
    {
        // interface
        $this->generate(
          $entity,
          'interface',
          $this->getFileGenerationPath().DIRECTORY_SEPARATOR.$entity.'Repository.php',
          $this->getTemplatePath().DIRECTORY_SEPARATOR.'repository-interface.txt'
        );

        // decorator
        $this->generate(
          $entity,
          'decorator',
          $this->getFileGenerationPath().DIRECTORY_SEPARATOR.'Cache'.DIRECTORY_SEPARATOR."Cache{$entity}Decorator.php",
          $this->getTemplatePath().DIRECTORY_SEPARATOR.'cache-repository-decorator.txt'
        );

        // repository
        $this->generate(
          $entity,
          'eloquent',
          $this->getFileGenerationPath().DIRECTORY_SEPARATOR.'Eloquent'.DIRECTORY_SEPARATOR."Eloquent{$entity}Repository.php",
          $this->getTemplatePath().DIRECTORY_SEPARATOR.'eloquent-repository.txt'
        );
    }

    /**
     * Create the actual files.
     *
     * @param string $entity
     * @param string $type
     * @param string $file
     * @param string $template
     */
    private function generate($entity, $type, $file, $template)
    {
        // check if the base directory exists
        // if not found create it
        $dir = dirname($file);

        if (!file_exists($dir)) {
            mkdir($dir);
        }

        if ($this->canGenerate(
          $file,
          $this->getOption('overwrite', false),
          $type
        )
        ) {
            $this->generator->make(
              $template,
              $this->createData($entity, $type),
              $file
            );

            echo "File {$file} generated.\n";
        }
    }

    /**
     * Create the data to generate the requested file.
     *
     * @param string $table
     * @param array  $columms
     *
     * @return array
     */
    private function createData($entity, $type = 'interface')
    {
        $data = [
          'CLASS_NAME' => $entity,
        ];

        switch ($type) {
            case 'interface':
                $data += [
                  'NAMESPACE' => $this->getNamespace().'\\Repositories',
                ];
                break;
            case 'eloquent':
                $data += [
                  'NAMESPACE' => $this->getNamespace().'\\Repositories\\Eloquent',
                  'INTERFACE_NAMESPACE' => $this->getNamespace().'\\Repositories',
                ];
                break;
            case 'decorator':
                $data += [
                  'LOWERCASE_CLASS_NAME' => camel_case($entity),
                  'NAMESPACE' => $this->getNamespace().'\\Repositories\\Cache',
                  'REPOSITORY_NAMESPACE' => $this->getNamespace().'\\Repositories',
                  'PLURAL_LOWERCASE_CLASS_NAME' => str_plural(strtolower($entity)),
                ];
        }

        return $data;
    }

    /**
     * Register the created repositories with the service provider.
     *
     * @throws \Way\Generators\Filesystem\FileAlreadyExists
     * @throws \Way\Generators\Filesystem\FileNotFound
     */
    private function registerWithProvider()
    {
        // get stub data
        $path = config('asgard.asgardgenerators.config.repositories.bindings_template',
          base_path('Modules/Asgardgenerators/templates').DIRECTORY_SEPARATOR.'bindings.txt');

        $stub = $this->filesystem->get($path);

        $data = '';

        // replace the keyed values with their actual value
        foreach ($this->generated as $entity) {
            $data .= str_replace([
                '$CLASS_NAME$',
                '$MODULE_NAME$',
                '$ENTITY_TYPE$',
              ], [
                $entity,
                $this->module->getStudlyName(),
                'Eloquent',
              ], $stub)."\n";
        }

        // add a replacement pointer to the end of the file to ensure further changes
        $data .= "\n// add bindings\n";

        // write the file
        $file = $this->module->getPath().DIRECTORY_SEPARATOR.'Providers'.DIRECTORY_SEPARATOR.$this->module->getName().'ServiceProvider.php';

        $content = $this->filesystem->get($file);
        $content = str_replace('// add bindings', $data, $content);

        if ($this->filesystem->exists($file)) {
            unlink($file);
        }

        $this->filesystem->make($file, $content);
    }
}
