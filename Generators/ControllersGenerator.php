<?php

namespace Modules\Asgardgenerators\Generators;

use Modules\Asgardgenerators\Contracts\Generators\BaseGenerator;
use Modules\Asgardgenerators\Contracts\Generators\GeneratorInterface;
use Modules\Asgardgenerators\Exceptions\DatabaseInformationException;
use Way\Generators\Filesystem\FileNotFound;

class ControllersGenerator extends BaseGenerator implements GeneratorInterface
{

    /**
     * List of entities which where created.
     *
     * @var array
     */
    protected $generated = [];

    /**
     * Maximum length of the resource route pattern.
     *
     * @var int
     */
    protected $maxResourceRouteLength = 32;

    /**
     * Execute the generator.
     */
    public function execute()
    {
        echo "\nGenerating Admin Controllers\n";
        foreach ($this->tables->getInfo() as $table => $columns) {
            $entity = $this->entityNameFromTable($table);
            $this->generate($entity, $table);
        }

        $this->createRoutes();
        $this->createPermissions();
        $this->createSidebar();
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
            $path = config('asgard.asgardgenerators.config.controllers.template',
                '');
        } else {
            $path .= 'admin-controller.txt';
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
        $path = $this->module->getPath() . DIRECTORY_SEPARATOR;

        $path .= implode(DIRECTORY_SEPARATOR, [
            'Http',
            'Controllers',
        ]);

        return $path;
    }

    /**
     * Generate the actual controller file.
     *
     * @param string $entity
     */
    private function generate($entity, $table)
    {
        $file = $this->getFileGenerationPath() . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . "{$entity}Controller.php";

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
                $this->createData($entity, $table),
                $file
            );

            $this->generated[] = $entity;

            echo "File {$file} generated.\n";
        }
    }

    /**
     * Create the data for controller generation.
     *
     * @param string $entity
     *
     * @return array
     */
    private function createData($entity, $table)
    {
        list($relationships, $variables) = $this->createRelationshipsData($table);

        // @todo: update config to retrieve entities, repos namespace
        return [
            'NAMESPACE' => $this->getNamespace() . '\\Http\\Controllers\\Admin',
            'CLASS_NAME' => $entity,
            'LOWERCASE_CLASS_NAME' => camel_case($entity),
            'PLURAL_LOWERCASE_CLASS_NAME' => camel_case(str_plural($entity)),
            'MODULE_NAME' => $this->module->getStudlyName(),
            'LOWERCASE_MODULE_NAME' => $this->module->getLowerName(),
            'RELATIONSHIPS' => $relationships,
            'VARIABLES' => $variables,
        ];
    }

    private function createRelationshipsData($table)
    {
        // init the required values
        $relationship_data = '';
        $variables = [];

        $module = $this->module->getStudlyName();

        // get a list of all relationships for the currently given table
        $relationships = $this->tables->getRelationships($table);

        foreach ($relationships as $relationship => $data) {
            // ensure lowercase
            $relationship = strtolower($relationship);

            switch ($relationship) {
                case 'belongstomany':
                case 'belongsto':
                case 'hasone':
                case 'hasmany':
                    foreach ($data as $row) {
                        $single = $this->entityNameFromTable($row[0]);
                        $plurar = str_plural($single);
                        $plurar_lowercase = camel_case($plurar);

                        $relationship_data .= "\${$plurar_lowercase}_repository = app(\\Modules\\{$module}\\Repositories\\{$single}Repository::class);\n";
                        $relationship_data .= "\${$plurar_lowercase} = \${$plurar_lowercase}_repository->all();\n";

                        $variables[] = $plurar_lowercase;
                    }
                    break;
                default:
                    throw new DatabaseInformationException("Unsupported relationship. {$relationship}");
            }
        }

        // create the variables line
        $variables = array_map(function ($item) {
            return "'$item' => \${$item}";
        }, $variables);

        return [
            $relationship_data,
            implode(",\n", $variables),
        ];
    }

    /**
     * Create the resource routes.
     *
     * @throws \Way\Generators\Filesystem\FileAlreadyExists
     * @throws \Way\Generators\Filesystem\FileNotFound
     */
    private function createRoutes()
    {
        // get stub data
        $path = config('asgard.asgardgenerators.config.controllers.route_template',
            base_path('Modules/Asgardgenerators/templates') . DIRECTORY_SEPARATOR . 'route-resource.txt');

        $stub = $this->filesystem->get($path);

        $data = '';

        // replace the keyed values with their actual value
        foreach ($this->generated as $entity) {
            if ($this->shouldGenerateRoutesForEntity($entity)) {
                $data .= str_replace([
                        '$CLASS_NAME$',
                        '$PLURAL_LOWERCASE_CLASS_NAME$',
                        '$MODULE_NAME$',
                        '$LOWERCASE_MODULE_NAME$',
                        '$LOWERCASE_CLASS_NAME$',
                    ], [
                        $entity,
                        str_plural(strtolower($entity)),
                        $this->module->getStudlyName(),
                        $this->module->getLowerName(),
                        strtolower($entity),
                    ], $stub) . "\n";
            } else {
                $data .= "\n// @todo: create routes for {$entity} manually\n";
            }
        }

        // add a replacement pointer to the end of the file to ensure further changes
        $data .= "\n// append\n";

        // write the file
        $file = $this->module->getPath() . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'backendRoutes.php';

        $content = $this->filesystem->get($file);
        $content = str_replace('// append', $data, $content);

        if ($this->filesystem->exists($file)) {
            unlink($file);
        }

        $this->filesystem->make($file, $content);
    }

    /**
     * Create the permissions for the generated controller classes.
     */
    private function createPermissions()
    {
        // get stub data
        $path = config('asgard.asgardgenerators.config.controllers.permissions_template',
            base_path('Modules/Asgardgenerators/templates') . DIRECTORY_SEPARATOR . 'permissions-append.txt');

        $stub = $this->filesystem->get($path);

        $data = '';

        // replace the keyed values with their actual value
        foreach ($this->generated as $entity) {
            $data .= str_replace([
                    '$LOWERCASE_MODULE_NAME$',
                    '$PLURAL_LOWERCASE_CLASS_NAME$',
                ], [
                    $this->module->getLowerName(),
                    str_plural(strtolower($entity)),
                ], $stub) . "\n";
        }

        // add a replacement pointer to the end of the file to ensure further changes
        $data .= "\n// append\n";

        // write the file
        $file = $this->module->getPath() . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'permissions.php';

        $content = $this->filesystem->get($file);
        $content = str_replace('// append', $data, $content);

        if ($this->filesystem->exists($file)) {
            unlink($file);
        }

        $this->filesystem->make($file, $content);

        // publish the newly created permissions file
        $publish_location = config_path() . DIRECTORY_SEPARATOR . implode('.', [
                'asgard',
                $this->module->getLowerName(),
                'permissions',
                'php',
            ]);

        if ($this->filesystem->exists($publish_location)) {
            unlink($publish_location);
        }

        $this->filesystem->make($publish_location, $content);
    }

    /**
     * Check if the resource routes should be generated for a given entity name.
     *
     * @param $entityName
     * @return bool
     * @internal param string $entity
     *
     */
    private function shouldGenerateRoutesForEntity($entityName)
    {
        $LOWERCASE_MODULE_NAME = $this->module->getLowerName();
        $LOWERCASE_CLASS_NAME = strtolower($entityName);

        $pattern = "admin.$LOWERCASE_MODULE_NAME.$LOWERCASE_CLASS_NAME.destroy";

        if (strlen($pattern) > $this->maxResourceRouteLength) {
            return false;
        }

        return true;
    }

    private function createSidebar()
    {
        $path = config('asgard.asgardgenerators.config.controllers.sidebar_item_template',
            base_path('Modules/Asgardgenerators/templates') . DIRECTORY_SEPARATOR . 'sidebar-item.txt');

        $stub = $this->filesystem->get($path);

        $module = $this->module->getLowerName();

        $data = "\$menu->group('{$module}', function (Group \$group) {";

        // replace the keyed values with their actual value
        foreach ($this->generated as $entity) {
            $data .= str_replace([
                    '$LOWERCASE_MODULE_NAME$',
                    '$LOWERCASE_SINGLE_ENTITY$',
                    '$LOWERCASE_PLURAL_ENTITY$'
                ], [
                    $module,
                    str_singular(strtolower($entity)),
                    str_plural(strtolower($entity)),
                ], $stub) . "\n";
        }

        // add a replacement pointer to the end of the file to ensure further changes
        $data .= "\n});\n\nreturn \$menu; \n";

        // write the file
        $file = $this->module->getPath() . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR,
                [
                    "Sidebar",
                    "SidebarExtender.php"
                ]);

        $content = $this->filesystem->get($file);
        $content = str_replace('return $menu;', $data, $content);

        if ($this->filesystem->exists($file)) {
            unlink($file);
        }

        $this->filesystem->make($file, $content);
    }
}
