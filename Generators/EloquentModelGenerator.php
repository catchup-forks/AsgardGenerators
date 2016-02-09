<?php

namespace Modules\Asgardgenerators\Generators;

use Modules\Asgardgenerators\Contracts\Generators\BaseGenerator;
use Modules\Asgardgenerators\Contracts\Generators\GeneratorInterface;
use Way\Generators\Compilers\TemplateCompiler;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Generator;
use User11001\EloquentModelGenerator\Console\SchemaGenerator;

class EloquentModelGenerator extends BaseGenerator implements GeneratorInterface
{

    /**
     * @var \Xethron\MigrationsGenerator\Generators\SchemaGenerator
     */
    protected $schemaGenerator;

    public static $namespace;

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
      TemplateCompiler $compiler,
      $config,
      $tables,
      $options
    ) {
        parent::__construct(
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

        $tables = $this->tables;

        //2. for each table, fetch primary and foreign keys
        echo 'Fetching table columns, primary keys, foreign keys\n';
        $prep = $this->getColumnsPrimaryAndForeignKeysPerTable($tables);


        //3. create an array of rules, holding the info for our Eloquent models to be
        echo 'Generating Eloquent rules\n';
        $eloquentRules = $this->getEloquentRules($tables, $prep);

        //4. Generate our Eloquent Models
        echo "\nGenerating Eloquent models\n";
        $this->generateEloquentModels($destinationFolder, $eloquentRules);

        echo "\nAll done!";
    }

    /**
     * Determine the namespace for Model generation
     *
     * @return string
     */
    private function getNamespace()
    {
        $ns = isset($this->options['namespace']) ?: "";
        if (empty($ns)) {
            $ns = env('APP_NAME', 'App\Models');
        }

        //convert forward slashes in the namespace to backslashes
        $ns = str_replace('/', '\\', $ns);
        return $ns;

    }

    /**
     * Retrieve the table keys
     *
     * @param array $tables
     * @return array
     */
    private function getColumnsPrimaryAndForeignKeysPerTable($tables)
    {
        $prep = [];
        foreach ($tables as $table) {
            //get foreign keys
            $foreignKeys = $this->schemaGenerator->getForeignKeyConstraints($table);

            //get primary keys
            $primaryKeys = $this->schemaGenerator->getPrimaryKeys($table);

            // get columns lists
            $__columns = $this->schemaGenerator->getSchema()
              ->listTableColumns($table);
            $columns = [];
            foreach ($__columns as $col) {
                $columns[] = $col->toArray()['name'];
            }

            $prep[$table] = [
              'foreign' => $foreignKeys,
              'primary' => $primaryKeys,
              'columns' => $columns,
            ];
        }

        return $prep;
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
            $foreign = $properties['foreign'];
            $primary = $properties['primary'];
            $columns = $properties['columns'];

            $this->setFillableProperties($table, $rules, $columns);

            $isManyToMany = $this->detectManyToMany($prep, $table);

            if ($isManyToMany === true) {
                $this->addManyToManyRules($tables, $table, $prep, $rules);
            }

            //the below used to be in an ELSE clause but we should be as verbose as possible
            //when we detect a many-to-many table, we still want to set relations on it
            //else
            {
                foreach ($foreign as $fk) {
                    $isOneToOne = $this->detectOneToOne($fk, $primary);

                    if ($isOneToOne) {
                        $this->addOneToOneRules($tables, $table, $rules, $fk);
                    } else {
                        $this->addOneToManyRules($tables, $table, $rules, $fk);
                    }
                }
            }
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
     * Determine if the given table has many to many relationships
     *
     * @param array  $prep
     * @param string $table
     * @return bool
     */
    private function detectManyToMany($prep, $table)
    {
        $properties = $prep[$table];
        $foreignKeys = $properties['foreign'];
        $primaryKeys = $properties['primary'];

        //ensure we only have two foreign keys
        if (count($foreignKeys) === 2) {

            //ensure our foreign keys are not also defined as primary keys
            $primaryKeyCountThatAreAlsoForeignKeys = 0;
            foreach ($foreignKeys as $foreign) {
                foreach ($primaryKeys as $primary) {
                    if ($primary === $foreign['name']) {
                        ++$primaryKeyCountThatAreAlsoForeignKeys;
                    }
                }
            }

            if ($primaryKeyCountThatAreAlsoForeignKeys === 1) {
                //one of the keys foreign keys was also a primary key
                //this is not a many to many. (many to many is only possible when both or none of the foreign keys are also primary)
                return false;
            }

            //ensure no other tables refer to this one
            foreach ($prep as $compareTable => $properties) {
                if ($table !== $compareTable) {
                    foreach ($properties['foreign'] as $prop) {
                        if ($prop['on'] === $table) {
                            return false;
                        }
                    }
                }
            }
            //this is a many to many table!
            return true;
        }

        return false;
    }

    /**
     * Create required many to many relationships
     *
     * @param array  $tables
     * @param string $table
     * @param array  $prep
     * @param array  $rules
     * @return void
     */
    private function addManyToManyRules($tables, $table, $prep, &$rules)
    {

        //$FK1 belongsToMany $FK2
        //$FK2 belongsToMany $FK1

        $foreign = $prep[$table]['foreign'];

        $fk1 = $foreign[0];
        $fk1Table = $fk1['on'];
        $fk1Field = $fk1['field'];
        //$fk1References = $fk1['references'];

        $fk2 = $foreign[1];
        $fk2Table = $fk2['on'];
        $fk2Field = $fk2['field'];
        //$fk2References = $fk2['references'];

        //User belongstomany groups user_group, user_id, group_id
        if (in_array($fk1Table, $tables)) {
            $rules[$fk1Table]['belongsToMany'][] = [
              $fk2Table,
              $table,
              $fk1Field,
              $fk2Field
            ];
        }
        if (in_array($fk2Table, $tables)) {
            $rules[$fk2Table]['belongsToMany'][] = [
              $fk1Table,
              $table,
              $fk2Field,
              $fk1Field
            ];
        }
    }

    /**
     * Determine if the given foreign key is used in a one to one relationship
     *
     * @param array $fk
     * @param array $primary
     * @return bool
     */
    private function detectOneToOne($fk, $primary)
    {
        if (count($primary) === 1) {
            foreach ($primary as $prim) {
                if ($prim === $fk['field']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Create the required one to many relationship
     *
     * @param array  $tables
     * @param string $table
     * @param array  $rules
     * @param array  $fk
     * @return void
     */
    private function addOneToManyRules($tables, $table, &$rules, $fk)
    {
        //$table belongs to $FK
        //FK hasMany $table

        $fkTable = $fk['on'];
        $field = $fk['field'];
        $references = $fk['references'];
        if (in_array($fkTable, $tables)) {
            $rules[$fkTable]['hasMany'][] = [$table, $field, $references];
        }
        if (in_array($table, $tables)) {
            $rules[$table]['belongsTo'][] = [$fkTable, $field, $references];
        }
    }

    /**
     * Create the required one to one relationship
     *
     * @param array $tables
     * @param array $table
     * @param array $rules
     * @param array $fk
     * @return void
     */
    private function addOneToOneRules($tables, $table, &$rules, $fk)
    {
        $fkTable = $fk['on'];
        $field = $fk['field'];
        $references = $fk['references'];
        if (in_array($fkTable, $tables)) {
            $rules[$fkTable]['hasOne'][] = [$table, $field, $references];
        }
        if (in_array($table, $tables)) {
            $rules[$table]['belongsTo'][] = [$fkTable, $field, $references];
        }
    }

    /**
     * Generate the required models
     *
     * @param string $destinationFolder
     * @param array $eloquentRules
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
     * @param array $rules
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
          'NAME'      => ucwords($this->argument('modelName')),
          'NAMESPACE' => env('APP_NAME', 'App\Models'),
        ];
    }

    /**
     * Full path to the output file
     *
     * @return string
     */
    public function getFileGenerationPath()
    {
        return $this->getOption('path',
          config('asgard.asgardgenerators.config.models.output_path',
            ""));
    }
}