<?php

namespace Modules\Asgardgenerators\Contracts\Generators;

use Modules\Asgardgenerators\Generators\DatabaseInformation;
use \Illuminate\Config\Repository as Config;
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
     * @var TemplateCompiler
     */
    protected $compiler;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var DatabaseInformation
     */
    protected $tables;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param Generator           $generator
     * @param Filesystem          $filesystem
     * @param TemplateCompiler    $compiler
     * @param Config              $config
     * @param DatabaseInformation $tables
     * @param array               $options
     */
    public function __construct(
      Generator $generator,
      Filesystem $filesystem,
      TemplateCompiler $compiler,
      Config $config,
      DatabaseInformation $tables,
      array $options
    ) {
        $this->generator = $generator;
        $this->filesystem = $filesystem;
        $this->compiler = $compiler;
        $this->config = $config;
        $this->tables = $tables;
        $this->options = $options;
    }

    /**
     * Get an option if it's defined in the options bag
     *
     * @param string     $key
     * @param null|mixed $default
     * @return null|mixed
     */
    public function getOption($key, $default = null)
    {
        if (isset($this->options[$key]) && !empty($this->options[$key])) {
            return $this->options[$key];
        }

        return $default;
    }

}