<?php

namespace Starme\Elasticsearch\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array select(string $query, array $bindings = [], bool $useReadPdo = true)
 * @method static bool insert(string $query, array $bindings = [])
 * @method static int delete(string $query, array $bindings = [])
 * @method static int update(string $query, array $bindings = [])
 * @method static string getDefaultConnection()
 * @method static void commit()
 * @method static void listen(\Closure $callback)
 * @method static void setDefaultConnection(string $name)
 *
 * @see \Starme\Elasticsearch\ConnectionResolver
 * @see \Starme\Elasticsearch\Connection
 */
class ES extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'es';
    }
}
