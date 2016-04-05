<?php

namespace Modules\Asgardgenerators\Generators;

use Illuminate\Support\Facades\App;
use Modules\Asgardgenerators\Contracts\Generators\BaseGenerator;
use Modules\Asgardgenerators\Contracts\Generators\GeneratorInterface;
use Modules\Asgardgenerators\Exceptions\DatabaseInformationException;

class ViewsGenerator extends BaseGenerator implements GeneratorInterface
{
    /**
     * List of columns taht should not be included in the views.
     *
     * @var array
     */
    protected $excluded_columns = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'password',
    ];

    /**
     * Execute the generator.
     */
    public function execute()
    {
        echo "\nGenerating Views:\n";
        // create the index view per table
        foreach ($this->tables->getInfo() as $table => $columns) {
            $viewsToGenerate = $this->getViewsToGenerate($table);

            foreach ($viewsToGenerate as $item) {
                $this->generate($table, $columns, $item);
            }
        }
    }

    private function getViewsToGenerate($table)
    {
        $entity = $this->entityNameFromTable($table);
        $isTranslation = $this->isTranslationEntity($entity);

        if ($isTranslation) {
            return [
                'fields',
            ];
        }

        return [
            'index',
            'show',
            'create',
            'edit',
            'fields',
            'fields-translatable',
        ];
    }

    /**
     * Full path to the required template file.
     *
     * @return string
     */
    public function getTemplatePath()
    {
        $path = $this->getOption('templatePath', null);

        if (is_null($path)) {
            $path = config('asgard.asgardgenerators.config.views.template',
                '');
        } else {
            $path .= 'views';
        }

        return $path;
    }

    /**
     * Create the data used in the template file.
     *
     * @return array
     */
    public function getTemplateData()
    {
        return [
        ];
    }

    /**
     * Full path to the output file.
     *
     * @return string
     */
    public function getFileGenerationPath()
    {
        $path = $this->module->getPath() . DIRECTORY_SEPARATOR;

        $path .= implode(DIRECTORY_SEPARATOR, [
            'Resources',
            'views',
            'admin',
        ]);

        // ensure the basedir exists
        if (!file_exists($path)) {
            mkdir($path);
        }

        return $path;
    }

    /**
     * Generate the requested view.
     *
     * @param string $table
     * @param array $columns
     * @param string $name
     */
    private function generate($table, $columns = [], $name = 'index')
    {
        // create the base dir for the views
        $entity = $this->entityNameFromTable($table);
        $entity = str_plural($entity);

        $base_dir = $this->getFileGenerationPath() . DIRECTORY_SEPARATOR . snake_case($entity);

        if (!file_exists($base_dir)) {
            mkdir($base_dir);
        }

        $file_to_generate = $base_dir . DIRECTORY_SEPARATOR . "$name.blade.php";

        if ($this->canGenerate(
            $file_to_generate,
            $this->getOption('overwrite', false),
            'view'
        )
        ) {
            $templateData = $this->createData($table, $columns, $name);
            $hasTranslationFields = !empty($this->tables->getTranslationTable($table));
            $templateData['HAS_TRANSLATION_FIELDS'] = $hasTranslationFields ? 'true' : 'false';
            //dd($templateData);

            $this->generator->make(
                $this->getTemplatePath() . DIRECTORY_SEPARATOR . "$name.blade.php",
                $templateData,
                $file_to_generate
            );

            echo "File {$file_to_generate} generated.\n";
        }
    }

    /**
     * @param string $table
     *
     * @return string
     */
    private function createTitleFromTable($table)
    {
        $table = ucwords(str_singular($table));

        return str_replace('_', ' ', $table);
    }

    /**
     * Create the default model name from a given table name.
     *
     * @param string $table
     *
     * @return string
     */
    private function createDefaultModelNameFromTable($table)
    {
        return camel_case(str_singular($table));
    }

    /**
     * @param string $table
     * @param array $columns
     * @param string $type
     *
     * @return array
     */
    private function createData($table, $columns = [], $type = 'index')
    {
        // base data
        $model = $this->createDefaultModelNameFromTable($table);

        $data = [
            'NAMESPACE' => $this->getNamespace(),
            'MODEL' => $model,
            'MODELS' => camel_case($table),
            'LOWERCASE_MODULE_NAME' => $this->module->getLowerName(),
            'PLURAL_LOWERCASE_CLASS_NAME' => str_plural(strtolower($model)),
            'LOWERCASE_CLASS_NAME' => strtolower($model),
            'SNAKE_CASE_ENTITY' => snake_case(str_plural($model))
        ];

        $columns = $this->removeExcluded($columns, $type);

        switch ($type) {
            case 'index':
                $data['TABLE_HEADERS'] = $this->createIndexTableHeaderData($table, $columns);
                $data['TABLE_CONTENT'] = $this->createIndexTableContentData($table, $columns);
                break;
            case 'show':
                $data['TITLE'] = 'id';
                break;
            case 'edit':
            case 'create':
                break;
            case 'fields':
                $fields = $this->createFieldsForForm($table, $columns);
                $data['FIELDS'] = $fields;
                break;
            case 'fields-translatable':
                $translation_table = $this->tables->getTranslationTable($table);

                if ($translation_table) {
                    $translation_columns = $this->tables->getInfo($translation_table);
                    $translation_columns = $this->translationFieldsOnly($columns, $translation_columns);
                    $translation_columns = $this->removeExcluded($translation_columns);

                    $data += [
                        'FIELDS' => $this->createFieldsForForm($table, $translation_columns, true),
                    ];
                } else {
                    $data += [
                        'FIELDS' => "",
                    ];
                }
                break;
            default:
                echo("\nUnknown type: $type \n");
        }
        return $data;
    }

    /**
     * @param array $columns
     * @param array $translationColumns
     * @return array
     */
    private function translationFieldsOnly($columns, $translationColumns)
    {
        $translationColumns['columns'] = array_except($translationColumns['columns'], array_keys($columns['columns']));

        return $translationColumns;
    }

    /**
     * Create the table header for the index view.
     *
     * @param string $table
     * @param array $columns
     *
     * @return string
     */
    private function createIndexTableHeaderData($table, $columns)
    {
        $titles = [];

        foreach (array_keys($columns['columns']) as $column) {
            $titles[] = "<th>$column</th>";
        }

        return implode("\n", $titles);
    }

    /**
     * Create the table content for the index view.
     *
     * @param string $table
     * @param array $columns
     *
     * @return string
     */
    private function createIndexTableContentData($table, $columns)
    {
        $line = [];

        // determine the model name
        $model = $this->createDefaultModelNameFromTable($table);

        foreach ($columns['columns'] as $column => $datatype) {
            $line[] = "<td>{{ \${$model}->{$column} }}</td>";
        }

        return implode("\n", $line);
    }

    /**
     * Remove excluded columns from a given list of columns.
     *
     * @param array $columns
     *
     * @return array
     */
    private function removeExcluded($columns, $type = 'index')
    {
        $excluded = $this->excluded_columns;

        // don't include the primary key in the forms
        if (preg_match('/-fields$/', $type)) {
            // @todo: this is only for testing
            if (count($columns['primary']) == 1) {
                $excluded = array_merge($excluded, $columns['primary']);
            }
        }

        foreach (array_keys($columns['columns']) as $column) {
            if (in_array($column, $excluded)) {
                unset($columns['columns'][$column]);
            }
        }
        return $columns;
    }

    /**
     * Create the field partials replacement string.
     *
     * @param string $table
     * @param array $columns
     * @param string $type_to_create
     * @param bool $is_translation
     * @return string
     */
    private function createFieldsForForm(
        $table,
        $columns,
        $is_translation = false
    )
    {
        $stub = [];

        $module = 'asgardgenerators';

        // entity name for use in the views
        $entity = camel_case($this->entityNameFromTable($table));

        // add the "normal fields"
        foreach ($columns['columns'] as $column => $column_type) {
            if ((!ends_with($column, '_id') && ($column !== 'locale'))) {
                $this->appendFieldToStub($stub, $entity, $column, $column_type, $module,
                    $is_translation);
            }
        }

        // add the relationships
        $relationships = $this->tables->getRelationships($table);

        //echo("\nTable: " . $table . "\n");

        foreach ($relationships as $relationship => $data) {
            foreach ($data as $row) {
                $relatedTable = $row[0];

                $this->appendRelationshipFieldsToStub($stub, $relationship, $entity, $relatedTable, $row, $module,
                    $is_translation);
            }
        }

        return implode("\n            ", $stub);
    }

    /**
     * @param int $id
     * @param array $columns
     * @return string
     */
    private function getListColumn($id, $columns)
    {
        $defaultGuesses = [
            'title',
            'name',
            'slug',
            'translation',
            'value',
            'status',
        ];


        foreach ($defaultGuesses as $guess) {
            if (in_array($guess, $columns)) {
                return "'$guess'";
            }
        }

        return $id;
    }

    /**
     * Create a title from a given table column name.
     *
     * @param string $column
     *
     * @return string
     */
    private function createTitleFromColumn($column)
    {
        // ensure only the first letter is upper case
        $column = ucfirst(strtolower($column));

        // replace the _ by a space
        return str_replace('_', ' ', $column);
    }

    /**
     * Append a (default) field to a given stub
     *
     * @param string $stub
     * @param string $type_to_create
     * @param string $entity
     * @param string $column
     * @param string $field_type
     * @param null|string $module
     * @param bool $is_translation
     */
    private function appendFieldToStub(
        &$stub,
        $entity,
        $column,
        $field_type,
        $module = null,
        $is_translation = false
    )
    {
        if (is_null($module)) {
            $module = 'asgardgenerators';
        }

        $value = "''";

        $value = "\${$entity}->{$column}";

        $title = $this->createTitleFromColumn($column);

        // @todo: find a way to overload booleans in views
        $is_translation = intval($is_translation);

        switch (strtolower($field_type)) {
            case 'text':
                $stub[] = "
                   @include('$module::partials.fields.textarea', [
                       'title' => '$title',
                       'name' => '$column',
                       'value' => $value,
                       'placeholder' => '',
                       'is_translation' => $is_translation
                    ])\n\n";
                break;
            case 'datetime':
                $stub[] = "
                   @include('$module::partials.fields.date', [
                       'title' => '$title',
                       'name' => '$column',
                       'value' => $value,
                       'placeholder' => '',
                       'is_translation' => $is_translation
                    ])\n\n";
                break;
            case 'string':
            default:
                $stub[] = "
                   @include('$module::partials.fields.text', [
                       'title' => '$title',
                       'name' => '$column',
                       'value' => $value,
                       'placeholder' => '',
                       'is_translation' => $is_translation
                   ])\n\n";
        }
    }

    /**
     * Append relationship field, if necessary, to a given stub
     *
     * @param string $stub
     * @param string $relationship_to_create
     * @param string $entity
     * @param string $table
     * @param array $relationship
     * @param null|string $module
     */
    private function appendRelationshipFieldsToStub(
        &$stub,
        $relationship_to_create,
        $entity,
        $table,
        $relationship,
        $module = null,
        $is_translation = false
    )
    {
        $classes = '';

        if (is_null($module)) {
            $module = "asgardgenerators";
        }

        // ensure lowercase
        $relationship_to_create = strtolower($relationship_to_create);
        $isHasMany = ($relationship_to_create === 'hasmany');
        $isBelongsToMany = ($relationship_to_create === 'belongstomany');

        // init defaults
        switch ($relationship_to_create) {
            case 'belongstomany':
            case 'hasmany':
                $view_name = 'select-multiple';
                $selected = '[]';
                break;
            case 'belongsto':
            case 'hasone':
            default:
                $view_name = 'select';
                $classes = 'select2';
                $selected = 'null';
                break;
        }

        //singular or plural model function?
        $function = camel_case($table);
        $function = ($isHasMany || $isBelongsToMany) ? str_plural($function) : str_singular($function);
        $isTranslationRelation = ends_with($function, 'Translations') || ends_with($function, 'Translation');

        $relatedModelColumns = $this->getRelatedModelColumns($table);


        //skipping translation fields
        if (!$isTranslationRelation && !$isHasMany) {

            // determine the primary key(s)
            try {
                $primary_key = $this->tables->primaryKey($relationship[0]);
            } catch (DatabaseInformationException $e) {
                // fallback to the default id primary key name
                $primary_key = 'id';
            }

            $list_keys = '';
            if (is_array($primary_key) && !empty($primary_key)) {
                $list_keys = "'" . implode("','", $primary_key) . "'";
            } elseif (!is_array($primary_key)) {
                $list_keys = "'$primary_key'";
            }

            $options = 'null';
            if (!empty($list_keys)) {
                $keysCount = count(explode(',', $list_keys));

                if ($keysCount === 1) {
                    $textColumn = $this->getListColumn($list_keys, $relatedModelColumns);
                    $lists = "$textColumn,$list_keys";

                    $options = "$" . str_plural($function) . '->lists(' . $lists . ')->toArray()';
                    $selected = "$" . $entity . '->' . $function . '()->lists("id","id")->toArray()';
                }
            }


            $primary_key = $this->arrayToString($primary_key);

            $single = $this->entityNameFromTable($relationship[0]);

            $name = $isBelongsToMany ? $function : $relationship[1];

            $stub[] = "
                   @include('$module::partials.fields.{$view_name}', [
                       'title' => '{$single}',
                       'name' => '{$name}', //$function,//'{$relationship[0]}',
                       'options' => $options,
                       'primary_key' => {$primary_key},
                       'selected' => $selected,
                       'classes' => '$classes',
                   ])\n\n";
        } else {
            //skipping translation multiple-select field, or hasMany field
        }
    }

    private function getRelatedModelColumns($table) {
        $cols = \Schema::getColumnListing($table);

        $translationTable = $this->tables->getTranslationTable($table);
        if($translationTable) {
            $translationCols = \Schema::getColumnListing($translationTable);

            $cols = array_merge($translationCols, $cols);
        }

        return $cols;
    }
}
