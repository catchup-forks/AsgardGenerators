<?php

namespace Modules\Asgardgenerators\Generators;

use Modules\Asgardgenerators\Contracts\Generators\BaseGenerator;
use Modules\Asgardgenerators\Contracts\Generators\GeneratorInterface;
use Modules\Asgardgenerators\Exceptions\DatabaseInformationException;
use Pingpong\Modules\Module;
use Way\Generators\Compilers\TemplateCompiler;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Generator;
use User11001\EloquentModelGenerator\Console\SchemaGenerator;
use Illuminate\Config\Repository as Config;

class EloquentModelsGenerator extends BaseGenerator implements GeneratorInterface
{
    /**
     * List of columns that should not be included in the fillable or
     * translatable fields list.
     *
     * @var array
     */
    protected $excluded_columns = [
      'created_at',
      'updated_at',
      'deleted_at',
      'locale',
    ];

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
     * Execute the generator.
     */
    public function execute()
    {
        //0. determine destination folder
        $destinationFolder = $this->getFileGenerationPath();

        $tables = $this->tables->getTables();

        //2. for each table, fetch primary and foreign keys
        $prep = $this->tables->getInfo();

        //3. create an array of rules, holding the info for our Eloquent models to be
        $eloquentRules = $this->getEloquentRules($tables, $prep);

        //4. Generate our Eloquent Models
        echo "\nGenerating Eloquent models\n";
        $this->generateEloquentModels($destinationFolder, $eloquentRules);
    }

    /**
     * @param array $tables
     * @param array $prep
     *
     * @return array
     */
    private function getEloquentRules($tables, $prep)
    {
        $rules = [];

        //first create empty ruleset for each table
        foreach ($prep as $table => $properties) {
            $rules[$table] = [
              'hasMany' => [],
              'hasOne' => [],
              'belongsTo' => [],
              'belongsToMany' => [],
              'fillable' => [],
            ];
        }

        foreach ($prep as $table => $properties) {
            $columns = array_keys($properties['columns']);

            $this->setFillableProperties($table, $rules, $columns);

            // add relationships below
            $rules[$table] = array_merge($rules[$table],
              $this->tables->getRelationships($table));
        }

        return $rules;
    }

    /**
     * Get plural function name.
     *
     * @param string $modelName
     *
     * @return string
     */
    private function getPluralFunctionName($modelName, &$existingFunctions)
    {
        $modelName = lcfirst($modelName);

        $result = str_plural($modelName);

        return $this->getUniqueFunctionName($result, $existingFunctions);
    }

    /**
     * Get single function name.
     *
     * @param string $modelName
     *
     * @return string
     */
    private function getSingularFunctionName($modelName, &$existingFunctions)
    {
        $modelName = lcfirst($modelName);

        $result = str_singular($modelName);

        return $this->getUniqueFunctionName($result, $existingFunctions);
    }

    private function getUniqueFunctionName($functionName, &$existingFunctions, $suffix='')
    {
        $name = $functionName . $suffix;
        if (!isset($existingFunctions[$name])) {
            $existingFunctions[$name] = $name;
            return $name;
        }

        $suffix = ($suffix === '') ? 2 : $suffix+1;
        return $this->getUniqueFunctionName($functionName, $existingFunctions, $suffix);
    }

    /**
     * Determine the model name from a given table name.
     *
     * @param string $table
     *
     * @return string
     */
    private function generateModelNameFromTableName($table)
    {
        return ucfirst(camel_case(str_singular($table)));
    }

    /**
     * Create fillable property for the model.
     *
     * @param string $table
     * @param array  $rules
     * @param array  $columns
     */
    private function setFillableProperties($table, &$rules, $columns)
    {
        // if the table has translation add the required information to the
        // fillable fields
        if ($this->tableHasTranslation($table)) {
            $rules[$table]['translatedAttributes'] = $this->setTranslatable($table);
            $columns = array_merge($columns,
              $rules[$table]['translatedAttributes']);
        }
        $fillable = [];

        // exclude the globally excluded field + primary key(s)
        $excluded = $this->excludedFieldsForTable($table);

        foreach ($columns as $column_name) {
            if (!in_array($column_name, $excluded)) {
                $fillable[] = "'$column_name'";
            }
        }
        $rules[$table]['fillable'] = array_unique($fillable);
    }

    /**
     * Generate the required models.
     *
     * @param string $destinationFolder
     * @param array  $eloquentRules
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

                echo($e->getFile());
                dd($e->getLine());

                return;
            }
        }
    }

    

    /**
     * Create required belongs to relationship.
     *
     * @param array $rulesContainer
     *
     * @return string
     */
    private function generateBelongsToFunctions($rulesContainer, &$generatedFunctions)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $belongsToModel = $this->generateModelNameFromTableName($rules[0]);
            $key1 = $rules[1];
            $key2 = $rules[2];

            $belongsToFunctionName = $this->getSingularFunctionName($belongsToModel, $generatedFunctions);

            $namespace = $this->getModelNamespace($rules[0]);

            $function = "
    public function $belongsToFunctionName() {".'
        return $this->belongsTo'.'(\\'.$namespace."\\$belongsToModel::class, '$key1', '$key2');
    }
";
            $functions .= $function;
        }

        return $functions;
    }

    /**
     * Create required has many relationship.
     *
     * @param array $rulesContainer
     *
     * @return string
     */
    private function generateHasManyFunctions($rulesContainer, &$generatedFunctions)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $hasManyModel = $this->generateModelNameFromTableName($rules[0]);
            $key1 = $rules[1];
            $key2 = $rules[2];

            $hasManyFunctionName = $this->getPluralFunctionName($hasManyModel, $generatedFunctions);

            $namespace = $this->getModelNamespace($rules[0]);

            $function = "
    public function $hasManyFunctionName() {".'
        return $this->hasMany'.'(\\'.$namespace."\\$hasManyModel::class, '$key1', '$key2');
    }
";
            $functions .= $function;
        }

        return $functions;
    }

    /**
     * Create required has one relationship.
     *
     * @param array $rulesContainer
     *
     * @return string
     */
    private function generateHasOneFunctions($rulesContainer, &$generatedFunctions)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $hasOneModel = $this->generateModelNameFromTableName($rules[0]);
            $key1 = $rules[1];
            $key2 = $rules[2];

            $hasOneFunctionName = $this->getSingularFunctionName($hasOneModel, $generatedFunctions);

            $namespace = $this->getModelNamespace($rules[0]);

            $function = "
    public function $hasOneFunctionName() {".'
        return $this->hasOne'.'(\\'.$namespace."\\$hasOneModel::class, '$key1', '$key2');
    }
";
            $functions .= $function;
        }

        return $functions;
    }

    /**
     * Create required belongs to many relationship function.
     *
     * @param array $rulesContainer
     *
     * @return string
     */
    private function generateBelongsToManyFunctions($rulesContainer, &$generatedFunctions)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $belongsToManyModel = $this->generateModelNameFromTableName($rules[0]);
            $through = $rules[1];
            $key1 = $rules[2];
            $key2 = $rules[3];

            $belongsToManyFunctionName = $this->getPluralFunctionName($belongsToManyModel, $generatedFunctions);

            $namespace = $this->getModelNamespace($rules[0]);

            $function = "
    public function $belongsToManyFunctionName() {".'
        return $this->belongsToMany'.'(\\'.$namespace."\\$belongsToManyModel::class, '$through', '$key1', '$key2');
    }
";
            $functions .= $function;
        }

        return $functions;
    }

    private function generateSyncStatements($belongsToManyRulesContainer)
    {
        $statements = '';

        foreach ($belongsToManyRulesContainer as $rules) {
            $belongsToManyModel = $this->generateModelNameFromTableName($rules[0]);
            $through = $rules[1];
            $key1 = $rules[2];
            $key2 = $rules[3];

            $belongsToManyFunctionName = $this->getPluralFunctionName($belongsToManyModel, $generatedFunctions);

            $statement = '

        if(isset($attributes[' . "'$belongsToManyFunctionName'" . '])) {
            $model->' . $belongsToManyFunctionName . '()->sync($attributes[' . "'$belongsToManyFunctionName'" . ']);
        }';

            $statements .= $statement;
        }

        return $statements;
    }

    /**
     * Create a function from a given array of chunks.
     *
     * @param array $functionsContainer
     *
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
     * Generate the Model for a table.
     *
     * @param string $destinationFolder
     * @param string $table
     * @param array  $rules
     */
    private function generateEloquentModel($destinationFolder, $table, $rules)
    {

        //1. Determine path where the file should be generated
        $modelName = $this->generateModelNameFromTableName($table);
        $filePathToGenerate = $destinationFolder.'/'.$modelName.'.php';

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

        $generatedFunctions = [];





        $belongsToFunctions = $this->generateBelongsToFunctions($belongsTo, $generatedFunctions);
        $belongsToManyFunctions = $this->generateBelongsToManyFunctions($belongsToMany, $generatedFunctions);
        $hasManyFunctions = $this->generateHasManyFunctions($hasMany, $generatedFunctions);
        $hasOneFunctions = $this->generateHasOneFunctions($hasOne, $generatedFunctions);

        $functions = $this->generateFunctions([
          $belongsToFunctions,
          $belongsToManyFunctions,
          $hasManyFunctions,
          $hasOneFunctions,
        ]);

        $syncFunctions = $this->generateSyncStatements($belongsToMany);

        // init the traits replacement so empty traits will be replaced by an
        // empty string
        $traits = '';

        if($this->tables->hasColumn($table, 'deleted_at')) {
            $this->addDeletedAdTrait($traits, $table);
        }

        if (isset($rules['translatedAttributes'])) {
            $this->addTranslationTrait($traits, $table,
              $rules['translatedAttributes']);
        }
        


        //3. prepare template data
        $templateData = array(
          'NAMESPACE' => self::$namespace,
          'NAME' => $modelName,
          'TABLENAME' => $table,
          'TRAITS' => $traits,
          'FILLABLE' => $fillable,
          'FUNCTIONS' => $functions,
          'SYNC' => $syncFunctions,
        );

        $templatePath = $this->getTemplatePath();

        //run Jeffrey's generator
        $this->generator->make(
          $templatePath,
          $templateData,
          $filePathToGenerate
        );

        echo "File {$filePathToGenerate} generated.\n";
    }

    /**
     * Determine if the model should be generated.
     *
     * @param string $filePathToGenerate
     * @param string $table
     *
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
     * Full path to the required template file.
     *
     * @return string
     */
    public function getTemplatePath()
    {
        return $this->getOption('templatePath',
          config('asgard.asgardgenerators.config.models.template'));
    }

    /**
     * Create the data used in the template file.
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
     * Full path to the output file.
     *
     * @return string
     */
    public function getFileGenerationPath()
    {
        return $this->module->getPath().DIRECTORY_SEPARATOR.'Entities';
    }

    /**
     * Create the namespace for Model generation.
     *
     * @return string
     */
    protected function getNamespace()
    {
        $ns = parent::getNamespace();
        return $ns.'\\Entities';
    }

    /**
     * Check if a given table has a translation table available.
     *
     * @param string $table
     *
     * @return bool
     */
    private function tableHasTranslation($table)
    {
        return (bool) $this->tables->getTranslationTable($table);
    }


    /**
     * Create a list of columns available on the given table's translation table.
     *
     * @param string $table
     *
     * @return array
     */
    private function setTranslatable($table)
    {
        $translate_table = $this->tables->getTranslationTable($table);

        $translatable = [];

        // get a list of columns
        $columns = $this->schemaGenerator->getSchema()
          ->listTableColumns($translate_table);

        // exclude the globally excluded field + primary key(s)
        $excluded = $this->excludedFieldsForTable($translate_table);

        foreach ($columns as $column) {
            if (!in_array($column->getName(), $excluded)) {
                $translatable[] = $column->getName();
            }
        }

        return $translatable;
    }

    private function addDeletedAdTrait(&$traits, $table) {
        $traits .= "use \\Illuminate\\Database\\Eloquent\\SoftDeletes;\n";
    }

    /**
     * Add the translation trait and property needed for translation.
     *
     * @param string $traits
     * @param string $table
     * @param array  $translatable
     */
    private function addTranslationTrait(&$traits, $table, $translatable = [])
    {
        $excluded = [
            'created_at',
            'updated_at',
            'deleted_at',
            'id'
        ];

        $translatable = array_flip($translatable);
        $translatable = array_except($translatable, $excluded);
        $translatable = array_flip($translatable);

        $module = $this->module->getStudlyName();

        $translationTable = $this->tables->getTranslationTable($table);

        $translationEntity = $this->entityNameFromTable($translationTable);

        $translationEntity = "\\Modules\\{$module}\\Entities\\{$translationEntity}::class";

        $traits .= "\n"
          ."    use \\Dimsav\\Translatable\\Translatable;\n"
          ."    public \$translatedAttributes = ".$this->arrayToString($translatable).";\n"
          ."    public \$translationModel = {$translationEntity};\n";
    }

    /**
     * @param $table
     *
     * @return array
     *
     * @throws \Modules\Asgardgenerators\Exceptions\DatabaseInformationException
     */
    private function excludedFieldsForTable($table)
    {
        return $this->excluded_columns;
        /*
        try {
            $primary_key = $this->tables->primaryKey($table);
        } catch (DatabaseInformationException $e) {
            // fallback to the default id primary key name
            $primary_key = 'id';
        }

        if (!is_array($primary_key)) {
            $primary_key = [$primary_key];
        }

        $excluded = array_merge($this->excluded_columns, $primary_key);

        return $excluded;
        */
    }
}
