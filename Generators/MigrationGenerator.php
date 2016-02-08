<?php

namespace Modules\Asgardgenerators\Generators;

use Modules\Asgardgenerators\Contracts\Generators\GeneratorInterface;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Generator;

class MigrationGenerator implements GeneratorInterface
{

    protected $generator;

    protected $filesystem;

    protected $config;

    protected $tables;

    /**
     * MigrationGenerator constructor.
     * @param $generator
     * @param $filesystem
     * @param $config
     */
    public function __construct(
      Generator $generator,
      Filesystem $filesystem,
      $config,
      $tables
    ) {
        $this->generator = $generator;
        $this->filesystem = $filesystem;
        $this->config = $config;
        $this->tables = $tables ?: [];
    }


    public function execute()
    {

        var_dump("Do something with tables " . implode(', ', $this->tables));


    }

}