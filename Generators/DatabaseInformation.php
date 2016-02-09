<?php

namespace Modules\Asgardgenerators\Generators;

use User11001\EloquentModelGenerator\Console\SchemaGenerator;

class DatabaseInformation
{

    protected $tableInformation;

    /**
     * @var SchemaGenerator
     */
    protected $schemaGenerator;

    /**
     * DatabaseInformation constructor.
     * @param SchemaGenerator $schemaGenerator
     * @param array           $tables
     */
    public function __construct(
      SchemaGenerator $schemaGenerator,
      $tables = []
    ) {
        $this->schemaGenerator = $schemaGenerator;

        if (empty($tables)) {
            $tables = $this->schemaGenerator->getTables();
        }

        foreach ($tables as $table) {
            $this->tableInformation[$table] = $this->getTableInformation($table);
        }
    }

    /**
     * Get all table information from the requested table
     *
     * @param string $table
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

            $columns[$col['name']] = (string)$col['type'];
        }

        return [
          'foreign' => $foreignKeys,
          'primary' => $primaryKeys,
          'columns' => $columns,
        ];
    }

    /**
     * Get a flat list of tables
     *
     * @return array
     */
    public function getTables()
    {
        return array_keys($this->tableInformation);
    }

    /**
     * Get all information for the tables
     *
     * @return array
     */
    public function getInfo()
    {
        return $this->tableInformation;
    }

}