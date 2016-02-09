<?php

namespace Modules\Asgardgenerators\Contracts\Generators;

interface GeneratorInterface
{

    /**
     * Execute the generator
     *
     * @return void
     */
    public function execute();

    /**
     * Full path to the required template file
     *
     * @return string
     */
    public function getTemplatePath();

    /**
     * Create the data used in the template file
     *
     * @return array
     */
    public function getTemplateData();

    /**
     * Full path to the output file
     *
     * @return string
     */
    public function getFileGenerationPath();


}