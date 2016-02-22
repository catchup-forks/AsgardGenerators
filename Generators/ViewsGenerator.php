<?php

namespace Modules\Asgardgenerators\Generators;

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

    private function getViewsToGenerate($table) {
        $entity = $this->entityNameFromTable($table);
        $isTranslation = $this->isTranslationEntity($entity);

        if($isTranslation) {
            return [
                'edit-fields',
                'create-fields',
            ];
        }

        return [
            'index',
            'show',
            'edit',
            'create',
            'edit-fields',
            'create-fields',
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
        $path = $this->module->getPath().DIRECTORY_SEPARATOR;

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
     * @param array  $columns
     * @param string $name
     */
    private function generate($table, $columns = [], $name = 'index')
    {
        // create the base dir for the views
        $entity = $this->entityNameFromTable($table);
        $entity = str_plural($entity);

        $base_dir = $this->getFileGenerationPath().DIRECTORY_SEPARATOR."{$entity}";


        if (!file_exists($base_dir)) {
            mkdir($base_dir);
        }

        $file_to_generate = $base_dir.DIRECTORY_SEPARATOR."$name.blade.php";

        if ($this->canGenerate(
          $file_to_generate,
          $this->getOption('overwrite', false),
          'view'
        )
        ) {
            $this->generator->make(
              $this->getTemplatePath().DIRECTORY_SEPARATOR."$name.txt",
              $this->createData($table, $columns, $name),
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
     * @param array  $columns
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
        ];

        $columns = $this->removeExcluded($columns, $type);

        switch ($type) {
            case 'index':
                $data += [
                  'TABLE_HEADERS' => $this->createIndexTableHeaderData($table,
                    $columns),
                  'TABLE_CONTENT' => $this->createIndexTableContentData($table,
                    $columns),
                ];
                break;
            case 'show':
                $data += [
                  'TITLE' => 'id',
                ];
                break;
            case 'edit':
            case 'create':
                $data += [

                ];
                break;
            case 'edit-fields':
                $data += [
                  'FIELDS' => $this->createFieldsForForm($table, $columns,
                    'edit'),
                ];
                break;
            case 'create-fields':
                $data += [
                  'FIELDS' => $this->createFieldsForForm($table, $columns,
                    'create'),
                ];
                break;
        }

        return $data;
    }

    /**
     * Create the table header for the index view.
     *
     * @param string $table
     * @param array  $columns
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
     * @param array  $columns
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
     * @param array  $columns
     *
     * @return string
     */
    private function createFieldsForForm(
      $table,
      $columns,
      $type_to_create = 'create'
    ) {
        $stub = '';

        // @todo:
//        $module = $this->module->getLowerName();
        $module = 'asgardgenerators';

        // entity name for use in the views
        $entity = camel_case($this->entityNameFromTable($table));

        // add the "normal fields"
        foreach ($columns['columns'] as $column => $type) {
            // create the title from a given column
            $title = $this->createTitleFromColumn($column);

            $value = "''";

            if ($type_to_create == 'edit') {
                $value = "\${$entity}->{$column}";
            }

            switch (strtolower($type)) {
                case 'text':
                    $stub .= "@include('$module::partials.fields.textarea', [
                  'title' => '$title',
                  'name' => '$column',
                  'value' => $value,
                  'placeholder' => ''
                ])\n\n";
                    break;
                case 'datetime':
                    $stub .= "@include('$module::partials.fields.date', [
                  'title' => '$title',
                  'name' => '$column',
                  'value' => $value,
                  'placeholder' => ''
                ])\n\n";
                    break;
                case 'string':
                default:
                    $stub .= "@include('$module::partials.fields.text', [
                  'title' => '$title',
                  'name' => '$column',
                  'value' => $value,
                  'placeholder' => ''
                ])\n\n";
            }
        }

        // add the relationships
        $relationships = $this->tables->getRelationships($table);

        foreach ($relationships as $relationship => $data) {
            // simpler to work with lower case
            $relationship = strtolower($relationship);

            if (empty($data)) {
                continue;
            }

            switch ($relationship) {
                case 'belongstomany':
                case 'hasmany':
                    $view_name = 'select-multiple';
                    $selected = '[]';
                    break;
                case 'belongsto':
                case 'hasone':
                default:
                    $view_name = 'select';
                    $selected = 'null';
                    break;
            }

            foreach ($data as $row) {

                $function = camel_case($row[0]);

                $isHasMany = $relationship === 'hasmany';
                $isTranslationRelation = ends_with($function, 'Translations');


                //skipping translation fields
                if(!$isTranslationRelation && !$isHasMany) {

                    // determine the primary key(s)
                    try {
                        $primary_key = $this->tables->primaryKey($row[0]);
                    } catch (DatabaseInformationException $e) {
                        // fallback to the default id primary key name
                        $primary_key = 'id';
                    }

                    //if ($type_to_create == 'edit') {


                        $function =( ($relationship === 'hasmany') || ($relationship === 'belongstomany')) ? str_plural($function) : str_singular($function);
                        $list_keys = '';

                        if (is_array($primary_key) && !empty($primary_key)) {
                            $list_keys = "'".implode("','", $primary_key)."'";
                        } elseif (!is_array($primary_key)) {
                            $list_keys = "'$primary_key'";
                        }

                        //$selected = "\${$entity}->{$function}()->lists($list_keys)->toArray()";

                        if(!empty($list_keys)) {
                            $selected = "$" . $entity . '->' . $function . '()->lists(' . $list_keys . ')->toArray()';
                        }



                    //}

                    $primary_key = $this->arrayToString($primary_key);

                    $single = $this->entityNameFromTable($row[0]);
                    $plurar = camel_case(str_plural($single));

                    $stub .= "@include('$module::partials.fields.{$view_name}', [
                                  'title' => '{$single}',
                                  'name' => '{$row[0]}',
                                  'options' => \${$plurar},
                                  'primary_key' => {$primary_key},
                                  'selected' => $selected,
                                ])\n\n";

                } else {
                    //skipping translation multiple-select field
                }
            }
        }

        return $stub;
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
}
