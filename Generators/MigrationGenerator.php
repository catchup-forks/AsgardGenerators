<?php

namespace Modules\Asgardgenerators\Generators;

use Modules\Asgardgenerators\Contracts\Generators\GeneratorInterface;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Generator;

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
     * @param \Way\Generators\Generator             $generator
     * @param \Way\Generators\Filesystem\Filesystem $filesystem
     * @param \Illuminate\Config\Repository         $config
     * @param array                                 $tables
     * @param array                                 $options
     */
    public function __construct(
      Generator $generator,
      Filesystem $filesystem,
      $config,
      $tables,
      $options
    ) {
        $this->generator = $generator;
        $this->filesystem = $filesystem;
        $this->config = $config;
        $this->tables = $tables ?: [];
        $this->options = $options;
    }


    public function execute()
    {


    }

}