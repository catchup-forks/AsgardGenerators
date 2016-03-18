<?php

namespace Modules\AsgardGenerators\Generators;

use Modules\AsgardGenerators\Contracts\Generators\BaseGenerator;
use Modules\AsgardGenerators\Contracts\Generators\GeneratorInterface;
use PhpSpec\Locator\ResourceInterface;

class TranslationsGenerator extends BaseGenerator implements GeneratorInterface
{

    /**
     * Execute the generator.
     */
    public function execute()
    {
        echo "\nGenerating Default Translations\n";
        foreach ($this->tables->getInfo() as $table => $columns) {
            $entity = $this->entityNameFromTable($table);
            if (!$this->isTranslationEntity($entity)) {
                $this->generate($entity, $table);
            }
        }
    }

    private function generate($entity, $table)
    {
        $lowercase_plural_entity = strtolower(str_plural($entity));

        $file = $this->getFileGenerationPath() . DIRECTORY_SEPARATOR . "{$lowercase_plural_entity}.php";

        if ($this->canGenerate(
            $file,
            $this->getOption('overwrite', false),
            'translations'
        )
        ) {
            $this->generator->make(
                $this->getTemplatePath(),
                [
                    "ENTITY" => $entity,
                    "ENTITIES" => str_plural($entity),
                    "LOWERCASE_SINGLE_ENTITY" => strtolower($entity),
                    "LOWERCASE_PLURAL_ENTITY" => $lowercase_plural_entity,
                ],
                $file
            );
            
            echo "File {$file} generated.\n";
        }
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
            $path = config('asgard.asgardgenerators.config.translations.template',
                '');
        } else {
            $path .= 'translation.txt';
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
        return [];
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
            'Resources',
            'lang',
            'en' // default fallback language
        ]);

        if (!file_exists($path)) {
            mkdir($path);
        }

        return $path;
    }
}
