<?php

namespace Starme\Elasticsearch\Schema;

use Closure;
use Illuminate\Support\Fluent;


class Blueprint
{

    /**
     * The table the blueprint describes.
     *
     * @var string
     */
    protected $table;

    /**
     * The prefix of the table.
     *
     * @var string
     */
    protected $prefix;

    /**
     * The columns that should be added to the table.
     *
     * @var \Illuminate\Database\Schema\ColumnDefinition[]
     */
    protected $columns = [];

    /**
     * The commands that should be run for the table.
     *
     * @var \Illuminate\Support\Fluent[]
     */
    protected $commands = [];

    /**
     * The collation that should be used for the table.
     *
     * @var string
     */
    public $collation;

    /**
     * Create a new schema blueprint.
     *
     * @param string $table
     * @param  \Closure|null  $callback
     * @param  string  $prefix
     * @return void
     */
    public function __construct(string $table, Closure $callback = null, $prefix = '')
    {
        $this->prefix = $prefix;
        $this->table = $table;

        if (! is_null($callback)) {
            $callback($this);
        }
    }

    public function build($connection, $grammar): array
    {
        $statements = [];

        foreach ($this->commands as $command) {
            $method = 'compile' . ucfirst($command->name);

            if (method_exists($grammar, $method)) {
                if (! is_null($sql = $grammar->$method($this, $command, $connection))) {
                    $statements = array_merge($statements, (array) $sql);
                }
            }
        }
        return $statements;
    }

    /**
     * @return \Illuminate\Support\Fluent
     */
    public function createTemplate(): Fluent
    {
        return $this->addCommand('CreateTemplate');
    }

    /**
     * @return \Illuminate\Support\Fluent
     */
    public function updateTemplate(): Fluent
    {
        return $this->addCommand('UpdateTemplate');
    }

    /**
     * @param $name
     * @return \Illuminate\Support\Fluent
     */
    public function alias($name): Fluent
    {
        return $this->addCommand('Alias', ['alias'=>$name]);
    }

    /**
     * Specify shards number for the index.
     *
     * @param int $number
     * @return \Illuminate\Support\Fluent
     */
    public function shards(int $number): Fluent
    {
        return $this->settingCommand('number_of_shards', $number);
    }

    /**
     * Specify shards number for the index.
     *
     * @param int $number
     * @return \Illuminate\Support\Fluent
     */
    public function replicas(int $number): Fluent
    {
        return $this->settingCommand('number_of_replicas', $number);
    }

    /**
     * Specify max result window for the index.
     *
     * @param int $number
     * @return \Illuminate\Support\Fluent
     */
    public function results(int $number): Fluent
    {
        return $this->settingCommand('max_result_window', $number);
    }

    /**
     * Specify refresh interval for the index.
     *
     * @param int $number
     * @return \Illuminate\Support\Fluent
     */
    public function refreshInterval(int $number): Fluent
    {
        return $this->settingCommand('refresh_interval', $number);
    }

    /**
     * Create a new string column on the table.
     *
     * @param string $column
     * @return \Starme\Elasticsearch\Schema\ColumnDefinition
     */
    public function string(string $column): ColumnDefinition
    {
        return $this->addColumn('keyword', $column);
    }

    /**
     * Create a new text column on the table.
     *
     * @param string $column
     * @return \Starme\Elasticsearch\Schema\ColumnDefinition
     */
    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn('text', $column);
    }

    /**
     * Create a new tiny integer (1-byte) column on the table.
     *
     * @param string $column
     * @return \Starme\Elasticsearch\Schema\ColumnDefinition
     */
    public function tinyInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('byte', $column);
    }

    /**
     * Create a new small integer (2-byte) column on the table.
     *
     * @param string $column
     * @return \Starme\Elasticsearch\Schema\ColumnDefinition
     */
    public function smallInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('short', $column);
    }

    /**
     * Create a new integer (4-byte) column on the table.
     *
     * @param string $column
     * @return \Starme\Elasticsearch\Schema\ColumnDefinition
     */
    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn('integer', $column);
    }

    /**
     * Create a new big integer (8-byte) column on the table.
     *
     * @param string $column
     * @return \Starme\Elasticsearch\Schema\ColumnDefinition
     */
    public function bigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('long', $column);
    }

    /**
     * Create a new float column on the table.
     *
     * @param string $column
     * @return \Starme\Elasticsearch\Schema\ColumnDefinition
     */
    public function float(string $column): ColumnDefinition
    {
        return $this->addColumn('float', $column);
    }

    /**
     * Create a new double column on the table.
     *
     * @param string $column
     * @return \Starme\Elasticsearch\Schema\ColumnDefinition
     */
    public function double(string $column): ColumnDefinition
    {
        return $this->addColumn('double', $column);
    }

    /**
     * Create a new boolean column on the table.
     *
     * @param string $column
     * @return \Starme\Elasticsearch\Schema\ColumnDefinition
     */
    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * Create a new date column on the table.
     *
     * @param string $column
     * @return \Starme\Elasticsearch\Schema\ColumnDefinition
     */
    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn('date', $column);
    }

    /**
     * Create a new binary column on the table.
     *
     * @param string $column
     * @return \Starme\Elasticsearch\Schema\ColumnDefinition
     */
    public function binary(string $column): ColumnDefinition
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Create a new array column on the table.
     *
     * @param string $column
     * @return \Starme\Elasticsearch\Schema\ColumnDefinition
     */
    public function array(string $column): ColumnDefinition
    {
        return $this->addColumn('array', $column);
    }

    /**
     * Create a new object column on the table.
     *
     * @param string $column
     * @return \Starme\Elasticsearch\Schema\ColumnDefinition
     */
    public function object(string $column): ColumnDefinition
    {
        return $this->addColumn('object', $column);
    }

    /**
     * Add a new column to the blueprint.
     *
     * @param string $type
     * @param string $name
     * @param array $parameters
     * @return \Starme\Elasticsearch\Schema\ColumnDefinition
     */
    public function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        $this->columns[] = $column = new ColumnDefinition(
            array_merge(compact('type', 'name'), $parameters)
        );

        return $column;
    }

    /**
     * Add a new setting command to the blueprint.
     *
     * @param string $type
     * @param  string|array  $value
     * @return \Illuminate\Support\Fluent
     */
    protected function settingCommand(string $type, $value): Fluent
    {
        return $this->addCommand(
            'setting', compact('type', 'value')
        );
    }

    /**
     * Add a new command to the blueprint.
     *
     * @param string $name
     * @param  array  $parameters
     * @return \Illuminate\Support\Fluent
     */
    protected function addCommand(string $name, array $parameters = []): Fluent
    {
        $this->commands[] = $command = $this->createCommand($name, $parameters);

        return $command;
    }

    /**
     * Create a new Fluent command.
     *
     * @param string $name
     * @param array $parameters
     * @return \Illuminate\Support\Fluent
     */
    protected function createCommand(string $name, array $parameters = []): Fluent
    {
        return new Fluent(array_merge(compact('name'), $parameters));
    }

    /**
     * Get the table the blueprint describes.
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->prefix . $this->table;
    }

    /**
     * Get the columns on the blueprint.
     *
     * @return \Starme\Elasticsearch\Schema\ColumnDefinition[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get the commands on the blueprint.
     *
     * @return \Illuminate\Support\Fluent[]
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

}
