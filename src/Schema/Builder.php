<?php
namespace Starme\Elasticsearch\Schema;


use Closure;
use Starme\Elasticsearch\Connection;

class Builder
{
    use Concerns\Alias,
        Concerns\Template;

    /**
     * The database connection instance.
     *
     * @var \Starme\Elasticsearch\Connection
     */
    protected $connection;

    /**
     * The schema grammar instance.
     *
     * @var \Starme\Elasticsearch\Schema\Grammar
     */
    protected $grammar;

    /**
     * The Blueprint resolver callback.
     *
     * @var \Closure
     */
    protected $resolver;

    /**
     * Create a new database Schema manager.
     *
     * @param \Starme\Elasticsearch\Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->grammar = $connection->getSchemaGrammar();
    }

    /**
     * Create a new table on the schema.
     *
     * @param string $table
     * @param \Closure $callback
     * @return void
     */
    public function create(string $table, Closure $callback)
    {
        $this->build(tap($this->createBlueprint($table), function ($blueprint) use ($callback) {
            $blueprint->createIndex();

            $callback($blueprint);
        }));
    }

    /**
     * Create a new table on the schema.
     *
     * @param string $table
     * @return void
     */
    public function exists(string $table)
    {
        $this->build(tap($this->createBlueprint($table), function ($blueprint) {
            $blueprint->existsIndex();
        }));
    }

    /**
     * Drop a table on the schema.
     *
     * @param string $table
     * @return void
     */
    public function drop(string $table)
    {
        $this->build(tap($this->createBlueprint($table), function ($blueprint) {
            $blueprint->dropIndex();
        }));
    }

    /**
     * Execute the blueprint to build / modify the table.
     *
     * @param \Starme\Elasticsearch\Schema\Blueprint $blueprint
     * @return array
     */
    protected function build(Blueprint $blueprint): array
    {
        return $blueprint->build($this->connection, $this->grammar);
    }

    /**
     * Create a new command set with a Closure.
     *
     * @param string $table
     * @param  \Closure|null  $callback
     * @return \Starme\Elasticsearch\Schema\Blueprint
     */
    protected function createBlueprint(string $table, Closure $callback = null): Blueprint
    {
        $prefix = $this->connection->getConfig('prefix_indexes')
            ? $this->connection->getConfig('prefix')
            : '';

        if (isset($this->resolver)) {
            return call_user_func($this->resolver, $table, $callback, $prefix);
        }

        return new Blueprint($table, $callback, $prefix);
    }

    /**
     * Get the database connection instance.
     *
     * @return \Starme\Elasticsearch\Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Set the database connection instance.
     *
     * @param \Starme\Elasticsearch\Connection $connection
     * @return $this
     */
    public function setConnection(Connection $connection): Builder
    {
        $this->connection = $connection;

        return $this;
    }

}