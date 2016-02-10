<?php

namespace Modules\Asgardgenerators\Generators;

use Modules\Asgardgenerators\Contracts\Generators\BaseGenerator;
use Modules\Asgardgenerators\Contracts\Generators\GeneratorInterface;

class RepositoryGenerator extends BaseGenerator implements GeneratorInterface
{

    /**
     * Execute the generator
     *
     * @return void
     */
    public function execute()
    {
        foreach ($this->tables->getInfo() as $table => $columns) {
            $entity = $this->entityNameFromTable($table);

            $this->generateRepositoriesFor($entity);


        }
    }

    /**
     * Full path to the required template file
     *
     * @return string
     */
    public function getTemplatePath()
    {
        $path = $this->getOption('templatePath', null);

        if (is_null($path)) {
            $path = config('asgard.asgardgenerators.config.repositories.template',
              "");
        } else {
            $path .= "repository";
        }

        return $path;
    }

    /**
     * Create the data used in the template file
     *
     * @return array
     */
    public function getTemplateData()
    {
        return [
          'PATH' => '17'
        ];
    }

    /**
     * Full path to the output file
     *
     * @return string
     */
    public function getFileGenerationPath()
    {
        $path = $this->getOption('path', null);

        if (is_null($path)) {
            $path = config('asgard.asgardgenerators.config.repositories.output_path',
              "");
        } else {
            $path .= "/Repositories";
        }

        return $path;
    }

    /**
     *
     *
     * @param string $entity
     * @return void
     */
    public function generateRepositoriesFor($entity)
    {
        // interface
        $this->generate(
          $entity,
          'interface',
          $this->getFileGenerationPath() . DIRECTORY_SEPARATOR . $entity . "Repository.php",
          $this->getTemplatePath() . DIRECTORY_SEPARATOR . "repository-interface.txt"
        );

        // decorator
        $this->generate(
          $entity,
          'decorator',
          $this->getFileGenerationPath() . DIRECTORY_SEPARATOR . "Cache" . DIRECTORY_SEPARATOR . "Cache{$entity}Decorator.php",
          $this->getTemplatePath() . DIRECTORY_SEPARATOR . "cache-repository-decorator.txt"
        );

        // repository
        $this->generate(
          $entity,
          'eloquent',
          $this->getFileGenerationPath() . DIRECTORY_SEPARATOR . "Eloquent" . DIRECTORY_SEPARATOR . "Eloquent{$entity}Repository.php",
          $this->getTemplatePath() . DIRECTORY_SEPARATOR . "eloquent-repository.txt"
        );
    }

    /**
     * Create the actual files
     *
     * @param string $entity
     * @param string $type
     * @param string $file
     * @param string $template
     * @return void
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
     * Create the data to generate the requested file
     *
     * @param string $table
     * @param array  $columms
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
                  'NAMESPACE' => $this->getNamespace() . "\\Repositories",
                ];
                break;
            case 'eloquent':
                $data += [
                  'NAMESPACE'           => $this->getNamespace() . "\\Repositories\\Eloquent",
                  'INTERFACE_NAMESPACE' => $this->getNamespace() . "\\Repositories",
                ];
                break;
            case 'decorator':
                $data += [
                  'LOWERCASE_CLASS_NAME'        => camel_case($entity),
                  'NAMESPACE'                   => $this->getNamespace() . "\\Repositories\\Cache",
                  'REPOSITORY_NAMESPACE'        => $this->getNamespace() . "\\Repositories",
                  'PLURAL_LOWERCASE_CLASS_NAME' => str_plural(strtolower($entity)),
                ];
        }


        return $data;
    }

}