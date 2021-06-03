<?php
namespace Starme\Elasticsearch\Schema\Concerns;

use Closure;

trait Template
{
    /**
     *
     * @param string $table
     * @param \Closure $callback
     * @return void
     */
    public function createTemplate(string $table, Closure $callback)
    {
        $body = $this->build(tap($this->createBlueprint($table), function ($blueprint) use ($callback) {
            $blueprint->createTemplate();

            $callback($blueprint);
        }));

        $this->connection->template('create', $body);
    }

    /**
     *
     * @param string $table
     * @param string $alias
     * @return void
     */
    public function existsTemplate(string $table, string $alias)
    {
        $body = $this->build(tap($this->createBlueprint($table), function ($blueprint) use ($alias) {
            $blueprint->existsTemplate($alias);
        }));

        $this->connection->template('exists', $body);
    }

    /**
     *
     * @param string $table
     * @param \Closure $callback
     * @return void
     */
    public function alterTemplate(string $table, Closure $callback)
    {
        $body = $this->build(tap($this->createBlueprint($table), function ($blueprint) use ($callback) {
            $blueprint->updateTemplate();

            $callback($blueprint);
        }));

        $this->connection->template('update', $body);
    }

    /**
     *
     * @param string $table
     * @param string $alias
     * @return void
     */
    public function DropTemplate(string $table, string $alias)
    {
        $body = $this->build(tap($this->createBlueprint($table), function ($blueprint) use ($alias) {
            $blueprint->DropTemplate($alias);
        }));

        $this->connection->template('delete', $body);
    }
}