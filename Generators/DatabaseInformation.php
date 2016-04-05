<?php

namespace Modules\Asgardgenerators\Generators;

use Modules\Asgardgenerators\Exceptions\DatabaseInformationException;
use User11001\EloquentModelGenerator\Console\SchemaGenerator;

class DatabaseInformation
{
    /**
     * @var array
     */
    protected $tableInformation;

    /**
     * @var array
     */
    protected $relationships;

    /**
     * @var SchemaGenerator
     */
    protected $schemaGenerator;

    /**
     * DatabaseInformation constructor.
     *
     * @param SchemaGenerator $schemaGenerator
     * @param array           $tables
     */
    public function __construct(
      SchemaGenerator $schemaGenerator,
      $tables = []
    ) {
        $this->schemaGenerator = $schemaGenerator;

        // retrieve the information for all tables available with the given
        // db connection
        if (empty($tables)) {
            $tables = $this->schemaGenerator->getTables();
        }

        // create the information structure
        foreach ($tables as $table) {
            $this->tableInformation[$table] = $this->getTableInformation($table);
        }

        // init the relationship information for the tables
        $this->relationships();
    }

    /**
     * Get all table information from the requested table.
     *
     * @param string $table
     *
     * @return array
     */
    private function getTableInformation($table)
    {
        //get foreign keys
        $foreignKeys = $this->schemaGenerator->getForeignKeyConstraints($table);

        //get primary keys
        $primaryKeys = $this->schemaGenerator->getPrimaryKeys($table);

        // get columns lists
        $__columns = $this->schemaGenerator->getSchema()
          ->listTableColumns($table);
        $columns = [];
        foreach ($__columns as $col) {
            $col = $col->toArray();

            $columns[$col['name']] = (string) $col['type'];
        }

        return [
          'foreign' => $foreignKeys,
          'primary' => $primaryKeys,
          'columns' => $columns,
        ];
    }

    /**
     * Get a flat list of tables.
     *
     * @return array
     */
    public function getTables()
    {
        return array_keys($this->tableInformation);
    }

    /**
     * Get all information for the tables.
     *
     * @return array
     */
    public function getInfo($table = null)
    {
        if (!is_null($table) && isset($this->tableInformation[$table])) {
            return $this->tableInformation[$table];
        }

        return $this->tableInformation;
    }

    /**
     * Determine the primary key for a given table.
     *
     * @param string $table
     *
     * @return array|string
     *
     * @throws \Modules\Asgardgenerators\Exceptions\DatabaseInformationException
     */
    public function primaryKey($table)
    {
        $info = $this->getInfo($table);

        if (!isset($info['primary'])) {
            throw new DatabaseInformationException("Primary key for table: {$table} could not be detected.");
        }

        // single key, we don't need the array returned
        if (count($info['primary']) === 1) {
            return reset($info['primary']);
        }

        return $info['primary'];
    }

    /**
     * Create the relationship information if it not exists allready.
     *
     * @return array
     */
    public function relationships()
    {
        if (!is_null($this->relationships)) {
            return $this->relationships;
        }

        // get a list of all tables in the database
        $tables = $this->schemaGenerator->getTables();
        $db_info = [];
        $rules = [];

        // init the table information
        foreach ($tables as $tableName) {
            $rules[$tableName] = [
              'hasMany' => [],
              'hasOne' => [],
              'belongsTo' => [],
              'belongsToMany' => [],
            ];

            $db_info[$tableName] = $this->getTableInformation($tableName);
        }

        foreach ($db_info as $table => $properties) {
            $foreign = $properties['foreign'];
            $primary = $properties['primary'];

            $isManyToMany = $this->detectManyToMany($db_info, $table);

            if ($isManyToMany === true) {
                $this->addManyToManyRules($tables, $table, $db_info, $rules);
            }

            /*
             * the below used to be in an ELSE clause but we should be as verbose as possible
             * when we detect a many-to-many table, we still want to set relations on it
             * else
             */
            foreach ($foreign as $fk) {
                $isOneToOne = $this->detectOneToOne($fk, $primary);

                if ($isOneToOne) {
                    $this->addOneToOneRules($tables, $table, $rules, $fk);
                } else {
                    $this->addOneToManyRules($tables, $table, $rules, $fk);
                }
            }
        }

        // filter out the required tables
        $requested_tables = array_flip($this->getTables());

        $this->relationships = array_intersect_key($rules, $requested_tables);
    }

    /**
     * Retrieve the translation table for a given table if it exists
     *
     * @param string $table
     * @return bool|string
     */
    public function getTranslationTable($table)
    {
        // the check should not be executed for a translation table
        if (preg_match('/_translations$/', $table)) {
            return false;
        }

        // list all the tables
        $tables = $this->schemaGenerator->getTables();

        $singular = str_singular($table).'_translations';
        $plural = str_plural($table).'_translations';

        // check if the translation tables exists
        foreach ($tables as $table_name) {
            if ($table_name == $singular || $table_name == $plural) {
                return $table_name;
            }
        }

        return false;
    }

    /**
     * Retrieve relationship information for a given table or all tables if none
     * defined.
     *
     * @param null|string $table
     *
     * @return array
     */
    public function getRelationships($table = null)
    {
        if (is_null($table)) {
            return $this->relationships;
        }

        return isset($this->relationships[$table]) ? $this->relationships[$table] : [];
    }

    /**
     * Detect if the requested table has many to many relationships defined.
     *
     * @param array  $info
     * @param string $table
     *
     * @return bool
     */
    private function detectManyToMany($info, $table)
    {
        $properties = $info[$table];
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
            foreach ($info as $compareTable => $properties) {
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
     * Detect if a provided foreign key has is a member of a one to one
     * relationship.
     *
     * @param array $foreign_key
     * @param array $primary_key
     *
     * @return bool
     */
    private function detectOneToOne($foreign_key, $primary_key)
    {
        if (count($primary_key) === 1) {
            foreach ($primary_key as $key) {
                if ($key === $foreign_key['field']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add a one to many relationship.
     *
     * @param array  $tables
     * @param string $table
     * @param array  $rules
     * @param array  $foreign_key
     */
    private function addOneToManyRules($tables, $table, &$rules, $foreign_key)
    {
        //$table belongs to $FK
        //FK hasMany $table

        $fkTable = $foreign_key['on'];
        $field = $foreign_key['field'];
        $references = $foreign_key['references'];
        if (in_array($fkTable, $tables)) {
            $rules[$fkTable]['hasMany'][] = [$table, $field, $references];
        }
        if (in_array($table, $tables)) {
            $rules[$table]['belongsTo'][] = [$fkTable, $field, $references];
        }
    }

    /**
     * Add a one to one relationship.
     *
     * @param array  $tables
     * @param string $table
     * @param array  $rules
     * @param array  $foreign_key
     */
    private function addOneToOneRules($tables, $table, &$rules, $foreign_key)
    {
        $fkTable = $foreign_key['on'];
        $field = $foreign_key['field'];
        $references = $foreign_key['references'];
        if (in_array($fkTable, $tables)) {
            $rules[$fkTable]['hasOne'][] = [$table, $field, $references];
        }
        if (in_array($table, $tables)) {
            $rules[$table]['belongsTo'][] = [$fkTable, $field, $references];
        }
    }

    /**
     * Add a many to many relationship to the table.
     *
     * @param array  $tables
     * @param string $table
     * @param array  $info
     * @param array  $rules
     */
    private function addManyToManyRules($tables, $table, $info, &$rules)
    {
        $foreign = $info[$table]['foreign'];

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
              $fk2Field,
            ];
        }
        if (in_array($fk2Table, $tables)) {
            $rules[$fk2Table]['belongsToMany'][] = [
              $fk1Table,
              $table,
              $fk2Field,
              $fk1Field,
            ];
        }
    }
}
