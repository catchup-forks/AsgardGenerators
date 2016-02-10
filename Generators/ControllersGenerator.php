<?php

namespace Modules\Asgardgenerators\Generators;

use Modules\Asgardgenerators\Contracts\Generators\BaseGenerator;
use Modules\Asgardgenerators\Contracts\Generators\GeneratorInterface;

class ControllersGenerator extends BaseGenerator implements GeneratorInterface
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
            $this->generate($entity);
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
            $path = config('asgard.asgardgenerators.config.controllers.template',
              "");
        } else {
            $path .= "admin-controller.txt";
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
        ];
    }

    /**
     * Full path to the output file
     *
     * @return string
     */
    public function getFileGenerationPath()
    {
        $path = $this->module->getPath() . DIRECTORY_SEPARATOR;

        $path .= implode(DIRECTORY_SEPARATOR, [
          "Http",
          "Controllers"
        ]);

        return $path;
    }

    /**
     * Generate the actual controller file
     *
     * @param string $entity
     */
    private function generate($entity)
    {
        $file = $this->getFileGenerationPath() . DIRECTORY_SEPARATOR . "Admin" . DIRECTORY_SEPARATOR . "{$entity}Controller.php";

        $dir = dirname($file);

        if (!file_exists($dir)) {
            mkdir($dir);
        }

        if ($this->canGenerate(
          $file,
          $this->getOption('overwrite', false),
          'view'
        )
        ) {
            $this->generator->make(
              $this->getTemplatePath(),
              $this->createData($entity),
              $file
            );

            echo "File {$file} generated.\n";
        }
    }

    /**
     * Create the data for controller generation
     *
     * @param string $entity
     * @return array
     */
    private function createData($entity)
    {
        // @todo: update config to retrieve entities, repos namespace
        return [
          'NAMESPACE'                   => $this->getNamespace() . "\\Http\\Controllers\\Admin",
          'CLASS_NAME'                  => $entity,
          'ENTITIES_NAMESPACE'          => $this->getNamespace() . "\\Entities",
          'REPOSITORIES_NAMESPACE'      => $this->getNamespace() . "\\Repositories",
          'LOWERCASE_CLASS_NAME'        => camel_case($entity),
          'LOWERCASE_MODULE_NAME'       => "module",
          'PLURAL_LOWERCASE_CLASS_NAME' => camel_case(str_plural($entity))
        ];
    }
}