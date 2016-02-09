<?php

namespace Modules\Asgardgenerators\Contracts\Generators;

use Way\Generators\Compilers\TemplateCompiler;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Generator;

abstract class BaseGenerator
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
     * @param \Way\Generators\Generator                  $generator
     * @param \Way\Generators\Filesystem\Filesystem      $filesystem
     * @param \Way\Generators\Compilers\TemplateCompiler $compiler
     * @param \Illuminate\Config\Repository              $config
     * @param array                                      $tables
     * @param array                                      $options
     */
    public function __construct(
      Generator $generator,
      Filesystem $filesystem,
      TemplateCompiler $compiler,
      \Illuminate\Config\Repository $config,
      array $tables,
      array $options
    ) {
        $this->generator = $generator;
        $this->filesystem = $filesystem;
        $this->compiler = $compiler;
        $this->config = $config;
        $this->tables = $tables ?: [];
        $this->options = $options;
    }


}