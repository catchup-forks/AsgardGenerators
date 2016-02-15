<?php

namespace Modules\Asgardgenerators\Generators;

use Modules\Asgardgenerators\Contracts\Generators\BaseGenerator;
use Modules\Asgardgenerators\Contracts\Generators\GeneratorInterface;
use Pingpong\Modules\Module;
use Way\Generators\Compilers\TemplateCompiler;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Generator;
use User11001\EloquentModelGenerator\Console\SchemaGenerator;
use \Illuminate\Config\Repository as Config;

class EloquentModelsGenerator extends BaseGenerator implements GeneratorInterface
{

    /**
     * @var \Xethron\MigrationsGenerator\Generators\SchemaGenerator
     */
    protected $schemaGenerator;

    public static $namespace;

    /**
     * @param \Pingpong\Modules\Module                   $module
     * @param \Way\Generators\Generator                  $generator
     * @param \Way\Generators\Filesystem\Filesystem      $filesystem
     * @param \Way\Generators\Compilers\TemplateCompiler $compiler
     * @param Config                                     $config
     * @param DatabaseInformation                        $tables
     * @param array                                      $options
     */
    public function __construct(
      Module $module,
      Generator $generator,
      Filesystem $filesystem,
      TemplateCompiler $compiler,
      Config $config,
      DatabaseInformation $tables,
      $options
    ) {
        parent::__construct(
          $module,
          $generator,
          $filesystem,
          $compiler,
          $config,
          $tables,
          $options
        );

        $this->schemaGenerator = new SchemaGenerator(
          $this->options['connection'],
          $this->options['defaultIndexNames'],
          $this->options['defaultFKNames']
        );
    }

    /**
     * Execute the generator
     *
     * @return void
     */
    public function execute()
    {
        //0. determine destination folder
        $destinationFolder = $this->getFileGenerationPath();

        //1. fetch all tables
        echo "\nFetching tables...\n";

        $tables = $this->tables->getTables();

        //2. for each table, fetch primary and foreign keys
        echo 'Fetching table columns, primary keys, foreign keys\n';
        $prep = $this->tables->getInfo();

        //3. create an array of rules, holding the info for our Eloquent models to be
        echo 'Generating Eloquent rules\n';
        $eloquentRules = $this->getEloquentRules($tables, $prep);

        //4. Generate our Eloquent Models
        echo "\nGenerating Eloquent models\n";
        $this->generateEloquentModels($destinationFolder, $eloquentRules);

        echo "\nAll done!";
    }

    /**
     * @param array $tables
     * @param array $prep
     * @return array
     */
    private function getEloquentRules($tables, $prep)
    {
        $rules = [];

        //first create empty ruleset for each table
        foreach ($prep as $table => $properties) {
            $rules[$table] = [
              'hasMany'       => [],
              'hasOne'        => [],
              'belongsTo'     => [],
              'belongsToMany' => [],
              'fillable'      => [],
            ];
        }

        foreach ($prep as $table => $properties) {
            $columns = array_keys($properties['columns']);

            $this->setFillableProperties($table, $rules, $columns);

            // add relationships below
            $rules[$table] = array_merge($rules[$table], $this->tables->getRelationships($table));
        }

        return $rules;
    }

    /**
     * Get plural function name
     *
     * @param string $modelName
     * @return string
     */
    private function getPluralFunctionName($modelName)
    {
        $modelName = lcfirst($modelName);
        return str_plural($modelName);
    }

    /**
     * Get single function name
     *
     * @param string $modelName
     * @return string
     */
    private function getSingularFunctionName($modelName)
    {
        $modelName = lcfirst($modelName);
        return str_singular($modelName);
    }

    /**
     * Determine the model name from a given table name
     *
     * @param string $table
     * @return string
     */
    private function generateModelNameFromTableName($table)
    {
        return ucfirst(camel_case(str_singular($table)));
    }

    /**
     * Create fillable property for the model
     *
     * @param string $table
     * @param array  $rules
     * @param array  $columns
     */
    private function setFillableProperties($table, &$rules, $columns)
    {
        $fillable = [];
        foreach ($columns as $column_name) {
            if ($column_name !== 'created_at' && $column_name !== 'updated_at') {
                $fillable[] = "'$column_name'";
            }
        }
        $rules[$table]['fillable'] = $fillable;
    }

    /**
     * Generate the required models
     *
     * @param string $destinationFolder
     * @param array  $eloquentRules
     * @return void
     */
    private function generateEloquentModels($destinationFolder, $eloquentRules)
    {
        //0. set namespace
        self::$namespace = $this->getNamespace();

        foreach ($eloquentRules as $table => $rules) {
            try {
                $this->generateEloquentModel($destinationFolder, $table,
                  $rules);
            } catch (\Exception $e) {
                echo "\nFailed to generate model for table $table\n";
                return;
            }
        }
    }

    /**
     * Create required belongs to relationship
     *
     * @param array $rulesContainer
     * @return string
     */
    private function generateBelongsToFunctions($rulesContainer)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $belongsToModel = $this->generateModelNameFromTableName($rules[0]);
            $key1 = $rules[1];
            $key2 = $rules[2];

            $belongsToFunctionName = $this->getSingularFunctionName($belongsToModel);

            $function = "
    public function $belongsToFunctionName() {" . '
        return $this->belongsTo' . "(\\" . self::$namespace . "\\$belongsToModel::class, '$key1', '$key2');
    }
";
            $functions .= $function;
        }

        return $functions;
    }

    /**
     * Create required has many relationship
     *
     * @param array $rulesContainer
     * @return string
     */
    private function generateHasManyFunctions($rulesContainer)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $hasManyModel = $this->generateModelNameFromTableName($rules[0]);
            $key1 = $rules[1];
            $key2 = $rules[2];

            $hasManyFunctionName = $this->getPluralFunctionName($hasManyModel);

            $function = "
    public function $hasManyFunctionName() {" . '
        return $this->hasMany' . "(\\" . self::$namespace . "\\$hasManyModel::class, '$key1', '$key2');
    }
";
            $functions .= $function;
        }

        return $functions;
    }

    /**
     * Create required has one relationship
     *
     * @param array $rulesContainer
     * @return string
     */
    private function generateHasOneFunctions($rulesContainer)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $hasOneModel = $this->generateModelNameFromTableName($rules[0]);
            $key1 = $rules[1];
            $key2 = $rules[2];

            $hasOneFunctionName = $this->getSingularFunctionName($hasOneModel);

            $function = "
    public function $hasOneFunctionName() {" . '
        return $this->hasOne' . "(\\" . self::$namespace . "\\$hasOneModel::class, '$key1', '$key2');
    }
";
            $functions .= $function;
        }

        return $functions;
    }

    /**
     * Create required belongs to many relationship function
     *
     * @param array $rulesContainer
     * @return string
     */
    private function generateBelongsToManyFunctions($rulesContainer)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $belongsToManyModel = $this->generateModelNameFromTableName($rules[0]);
            $through = $rules[1];
            $key1 = $rules[2];
            $key2 = $rules[3];

            $belongsToManyFunctionName = $this->getPluralFunctionName($belongsToManyModel);

            $function = "
    public function $belongsToManyFunctionName() {" . '
        return $this->belongsToMany' . "(\\" . self::$namespace . "\\$belongsToManyModel::class, '$through', '$key1', '$key2');
    }
";
            $functions .= $function;
        }

        return $functions;
    }

    /**
     * Create a function from a given array of chunks
     *
     * @param array $functionsContainer
     * @return string
     */
    private function generateFunctions($functionsContainer)
    {
        $f = '';
        foreach ($functionsContainer as $functions) {
            $f .= $functions;
        }

        return $f;
    }

    /**
     * Generate the Model for a table
     *
     * @param string $destinationFolder
     * @param string $table
     * @param array  $rules
     * @return void
     */
    private function generateEloquentModel($destinationFolder, $table, $rules)
    {

        //1. Determine path where the file should be generated
        $modelName = $this->generateModelNameFromTableName($table);
        $filePathToGenerate = $destinationFolder . '/' . $modelName . '.php';

        $canContinue = $this->canGenerateEloquentModel($filePathToGenerate,
          $table);
        if (!$canContinue) {
            return;
        }

        //2.  generate relationship functions and fillable array
        $hasMany = $rules['hasMany'];
        $hasOne = $rules['hasOne'];
        $belongsTo = $rules['belongsTo'];
        $belongsToMany = $rules['belongsToMany'];


        $fillable = implode(', ', $rules['fillable']);

        $belongsToFunctions = $this->generateBelongsToFunctions($belongsTo);
        $belongsToManyFunctions = $this->generateBelongsToManyFunctions($belongsToMany);
        $hasManyFunctions = $this->generateHasManyFunctions($hasMany);
        $hasOneFunctions = $this->generateHasOneFunctions($hasOne);

        $functions = $this->generateFunctions([
          $belongsToFunctions,
          $belongsToManyFunctions,
          $hasManyFunctions,
          $hasOneFunctions,
        ]);

        //3. prepare template data
        $templateData = array(
          'NAMESPACE' => self::$namespace,
          'NAME'      => $modelName,
          'TABLENAME' => $table,
          'FILLABLE'  => $fillable,
          'FUNCTIONS' => $functions
        );

        $templatePath = $this->getTemplatePath();

        //run Jeffrey's generator
        $this->generator->make(
          $templatePath,
          $templateData,
          $filePathToGenerate
        );
        echo "Generated model for table $table\n";
    }

    /**
     * Determine if the model should be generated
     *
     * @param string $filePathToGenerate
     * @param string $table
     * @return bool
     */
    private function canGenerateEloquentModel($filePathToGenerate, $table)
    {
        $canOverWrite = $this->getOption('overwrite', false);
        if (file_exists($filePathToGenerate)) {
            if ($canOverWrite) {
                $deleted = unlink($filePathToGenerate);
                if (!$deleted) {
                    echo "\nFailed to delete existing model $filePathToGenerate\n";
                    return false;
                }
            } else {
                echo "\nSkipped model generation, file already exists. (force using --overwrite) $table -> $filePathToGenerate\n";
                return false;
            }
        }

        return true;
    }

    /**
     * Full path to the required template file
     *
     * @return string
     */
    public function getTemplatePath()
    {
        return $this->getOption("templatePath",
          config('asgard.asgardgenerators.config.models.template'));

    }

    /**
     * Create the data used in the template file
     *
     * @return array
     */
    public function getTemplateData()
    {
        return [
          'NAMESPACE' => $this->getNamespace(),
        ];
    }

    /**
     * Full path to the output file
     *
     * @return string
     */
    public function getFileGenerationPath()
    {
        return $this->module->getPath() . DIRECTORY_SEPARATOR . "Entities";
    }

    /**
     * Create the namespace for Model generation
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

        return $ns . "\\Entities";
    }

}