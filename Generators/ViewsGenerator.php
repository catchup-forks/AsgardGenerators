<?php

namespace Modules\Asgardgenerators\Generators;


use Modules\Asgardgenerators\Contracts\Generators\BaseGenerator;
use Modules\Asgardgenerators\Contracts\Generators\GeneratorInterface;

class ViewsGenerator extends BaseGenerator implements GeneratorInterface
{

    /**
     * Execute the generator
     *
     * @return void
     */
    public function execute()
    {


        echo "boo\n\n\n\n\n\n";

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
            $path = config('asgard.asgardgenerators.config.migration.template',
              "");
        } else {
            $path .= "views";
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
            $path = config('asgard.asgardgenerators.config.views.output_path',
              "");
        } else {
            $path .= "/Views";
        }

        return $path;
    }
}