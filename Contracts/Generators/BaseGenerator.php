<?php

namespace Modules\Asgardgenerators\Contracts\Generators;

use Modules\Asgardgenerators\Generators\DatabaseInformation;
use Illuminate\Config\Repository as Config;
use Pingpong\Modules\Module;
use Way\Generators\Compilers\TemplateCompiler;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Generator;

abstract class BaseGenerator
{
    protected $module;

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
     * @param \Pingpong\Modules\Module $module
     * @param Generator                $generator
     * @param Filesystem               $filesystem
     * @param TemplateCompiler         $compiler
     * @param Config                   $config
     * @param DatabaseInformation      $tables
     * @param array                    $options
     */
    public function __construct(
      Module $module,
      Generator $generator,
      Filesystem $filesystem,
      TemplateCompiler $compiler,
      Config $config,
      DatabaseInformation $tables,
      array $options
    ) {
        $this->module = $module;
        $this->generator = $generator;
        $this->filesystem = $filesystem;
        $this->compiler = $compiler;
        $this->config = $config;
        $this->tables = $tables;
        $this->options = $options;
    }

    /**
     * Get an option if it's defined in the options bag.
     *
     * @param string     $key
     * @param null|mixed $default
     *
     * @return null|mixed
     */
    public function getOption($key, $default = null)
    {
        if (isset($this->options[$key]) && !empty($this->options[$key])) {
            return $this->options[$key];
        }

        return $default;
    }

    /**
     * Determine the namespace for generation.
     *
     * @return string
     */
    protected function getNamespace()
    {
        $ns = $this->options['namespace'];

        if (empty($ns)) {
            $ns = env('APP_NAME', 'App');
        }

        //convert forward slashes in the namespace to backslashes
        $ns = str_replace('/', '\\', $ns);

        return $ns;
    }

    /**
     * Create an entity name for the given table.
     *
     * @param string $table
     *
     * @return string
     */
    protected function entityNameFromTable($table)
    {
        $table = camel_case(str_singular($table));

        return ucwords($table);
    }

    /**
     * Determines whether this entity (model) is a translation model
     */
    protected function isTranslationEntity($entityName)
    {
        return ends_with($entityName, 'Translation');
    }

    /**
     * @param string $file
     * @param bool   $overwrite
     *
     * @return bool
     */
    protected function canGenerate($file, $overwrite = false, $type)
    {
        if (file_exists($file)) {
            if ($overwrite) {
                $deleted = unlink($file);
                if (!$deleted) {
                    echo "\nFailed to delete existing model $file\n";

                    return false;
                }
            } else {
                echo "\nSkipped {$type} generation, file already exists. (force using --overwrite) {$file}\n";

                return false;
            }
        }

        return true;
    }

    /**
     * Create a printable string from a given array.
     *
     * @param array $array
     *
     * @return string
     */
    protected function arrayToString($array = [])
    {
        // should never happen but just to be sure
        if (!is_array($array)) {
            $array = [$array];
        }

        // add quotes
        $array = array_map(function ($item) {
            return "\"$item\"";
        }, $array);

        return '['.implode(',', $array).']';
    }
}
