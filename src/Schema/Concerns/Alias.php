<?php
namespace Starme\Elasticsearch\Schema\Concerns;

trait Alias
{
    /**
     *
     * @param string $table
     * @param string $alias
     * @return void
     */
    public function alias(string $table, string $alias)
    {
        $body = $this->build(tap($this->createBlueprint($table), function ($blueprint) use ($alias) {
            $blueprint->alias($alias);
        }));

        $this->connection->alias('put', $body);
    }

    /**
     *
     * @param string $table
     * @param string $alias
     * @return void
     */
    public function existsAlias(string $table, string $alias)
    {
        $this->build(tap($this->createBlueprint($table), function ($blueprint) use ($alias) {
            $blueprint->existsAlias($alias);
        }));
    }

    /**
     *
     * @param string $table
     * @param string $alias
     * @return void
     */
    public function updateAlias(string $table, string $alias)
    {
        $this->build(tap($this->createBlueprint($table), function ($blueprint) use ($alias) {
            $blueprint->updateAlias($alias);
        }));
    }

    /**
     *
     * @param string $table
     * @param string $alias
     * @return void
     */
    public function DropAlias(string $table, string $alias)
    {
        $this->build(tap($this->createBlueprint($table), function ($blueprint) use ($alias) {
            $blueprint->DropAlias($alias);
        }));
    }

    /**
     * @param string $alias
     * @param string $table
     * @param string $new
     * @return void
     */
    public function toggleAlias(string $alias, string $table, string $new)
    {
        $this->build(tap($this->createBlueprint($table), function ($blueprint) use ($alias, $new) {
            $blueprint->toggleAlias($alias, $new);
        }));
    }




}