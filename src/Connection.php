<?php
namespace Starme\Elasticsearch;

use Closure;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Exception;
use LogicException;
use Psr\Log\LoggerInterface;
use Starme\Elasticsearch\Query\Builder as QueryBuilder;
use Starme\Elasticsearch\Query\Grammar as QueryGrammar;
use Starme\Elasticsearch\Schema\Builder as SchemaBuilder;
use Starme\Elasticsearch\Schema\Grammar as SchemaGrammar;

class Connection implements ConnectionInterface
{
    /**
     * @var \Elasticsearch\Client
     */
    protected $connection;

    /**
     * The event dispatcher instance.
     *
     * @var object
     */
    protected $events;

    /**
     * The elasticsearch connection configuration options.
     *
     * @var array
     */
    protected $config = [];

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Indicates whether queries are being logged.
     *
     * @var bool
     */
    protected $loggingQueries = false;

    /**
     * All of the queries run against the connection.
     *
     * @var array
     */
    protected $queryLog = [];

    /**
     * The index prefix for the connection.
     *
     * @var string
     */
    protected $tablePrefix = '';

    /**
     * The reconnector instance for the connection.
     *
     * @var callable
     */
    protected $reconnector;

    /**
     * @var \Starme\Elasticsearch\Query\Grammar
     */
    protected $queryGrammar;

    /**
     * @var \Starme\Elasticsearch\Schema\Grammar
     */
    protected $schemaGrammar;


    public function __construct($config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->useDefaultQueryGrammar();
        $this->useDefaultSchemaGrammar();
        $this->useDefaultClient();
        $this->setTablePrefix($this->config['prefix']);
    }

    /**
     * Set the es client to the default implementation.
     *
     * @return void
     */
    public function useDefaultClient()
    {
        $this->connection = $this->getDefaultClient();
    }

    /**
     * Get the default es client instance.
     *
     * @return \Elasticsearch\Client
     */
    protected function getDefaultClient(): Client
    {
        $builder = ClientBuilder::create();
        $builder->setHosts($this->config["servers"]);

        if ( ! empty($this->config['handler'])) {
            $builder->setHandler($this->config['handler']);
        }

        $builder->setLogger($this->logger);
        return $builder->build();
    }

    /**
     * Set the query grammar to the default implementation.
     *
     * @return void
     */
    public function useDefaultQueryGrammar()
    {
        $this->queryGrammar = $this->getDefaultQueryGrammar();
    }


    /**
     * Get the default query grammar instance.
     *
     * @return \Starme\Elasticsearch\Query\Grammar
     */
    protected function getDefaultQueryGrammar(): QueryGrammar
    {
        return new QueryGrammar;
    }

    /**
     * Set the query grammar to the default implementation.
     *
     * @return void
     */
    public function useDefaultSchemaGrammar()
    {
        $this->schemaGrammar = $this->getDefaultSchemaGrammar();
    }


    /**
     * Get the default query grammar instance.
     *
     * @return \Starme\Elasticsearch\Schema\Grammar
     */
    protected function getDefaultSchemaGrammar(): SchemaGrammar
    {
        return new SchemaGrammar;
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return \Starme\ElasticSearch\Schema\Builder
     */
    public function schema(): SchemaBuilder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new SchemaBuilder($this);
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @param $table
     * @return \Starme\Elasticsearch\Query\Builder
     */
    public function table($table): QueryBuilder
    {
        return $this->query()->from($table);
    }

    /**
     * Get a new query builder instance.
     *
     * @return \Starme\Elasticsearch\Query\Builder
     */
    public function query(): QueryBuilder
    {
        return new QueryBuilder($this, $this->getQueryGrammar());
    }

    /**
     * Run a select statement against the elasticsearch.
     *
     * @params array $body
     * @throws \Starme\Elasticsearch\Exceptions\QueryException
     */
    public function select(array $params)
    {
        if ( ! isset($params['scroll_id'])) {
            return $this->run('search', $params);
        }
        return $this->run('scroll', array_intersect_key($params, ['scroll_id'=>1, 'scroll'=>1]));
    }

    /**
     * Run an insert statement against the elasticsearch.
     *
     * @params array $params
     * @throws \Starme\Elasticsearch\Exceptions\QueryException
     */
    public function insert($params)
    {
        return $this->run('index', $params);
    }

    /**
     * Run an update statement against the elasticsearch.
     *
     * @params array $params
     * @throws \Starme\Elasticsearch\Exceptions\QueryException
     */
    public function update($params, $by_query=false)
    {
        if(isset($this->config['update_retry'])) {
            $params['retry_on_conflict'] = intval($this->config['update_retry']);
        }

        return $this->run(
            $by_query ? 'updateByQuery' : 'update',
            $params
        );
    }

    /**
     * Run an delete statement against the elasticsearch.
     *
     * @params array $params
     * @throws \Starme\Elasticsearch\Exceptions\QueryException
     */
    public function delete($params)
    {
        return $this->run('deleteByQuery', $params);
    }

    /**
     * Run an template statement against the elasticsearch.
     *
     * @params string $type
     * @params array $params
     * @throws \Starme\Elasticsearch\Exceptions\QueryException
     */
    public function template(string $type, array $params)
    {
        return  $this->run($type . 'Template', $params, function ($method, $params) {
            return $this->connection->indices()->$method($params);
        });
    }

    /**
     * Run an template statement against the elasticsearch.
     *
     * @params string $type
     * @params array $params
     * @throws \Starme\Elasticsearch\Exceptions\QueryException
     */
    public function alias(string $type, array $params)
    {
        return  $this->run($type . 'Alias', $params, function ($method, $params) {
            return $this->connection->indices()->$method($params);
        });
    }

    /**
     * Run a SQL statement and log its execution context.
     *
     * @param string $method
     * @param array $queries
     * @param \Closure|null $callback
     * @return mixed
     *
     * @throws \Starme\Elasticsearch\Exceptions\QueryException
     */
    protected function run(string $method, array $queries, Closure $callback = null)
    {
        $this->reconnectIfMissingConnection();

        if (is_null($callback)) {
            $callback = function ($method, $queries) {
                return $this->connection->$method($queries);
            };
        }

        $start = microtime(true);

        // Here we will run this query. If an exception occurs we'll determine if it was
        // caused by a connection that has been lost. If that is the cause, we'll try
        // to re-establish connection and re-run the query with a fresh connection.
        try {
            $result = $this->runQueryCallback($method, $queries, $callback);
        } catch (Exceptions\QueryException $e) {
            throw $e;
        }

        // Once we have run the query we will calculate the time that it took to run and
        // then log the query, bindings, and execution time so we will report them on
        // the event that the developer needs them. We'll log time in milliseconds.
        $this->logQuery(
            $method, $queries, $this->getElapsedTime($start)
        );

        return $result;
    }

    /**
     * Run a SQL statement.
     *
     * @param string $method
     * @param array $queries
     * @param \Closure $callback
     * @return mixed
     *
     * @throws \Starme\Elasticsearch\Exceptions\QueryException
     */
    protected function runQueryCallback(string $method, array $queries, Closure $callback)
    {
        // To execute the statement, we'll simply call the callback, which will actually
        // run the SQL against the PDO connection. Then we can calculate the time it
        // took to execute and log the query SQL, bindings and time in our memory.
        try {
            $result = $callback($method, $queries);
        }

            // If an exception occurs when attempting to run a query, we'll format the error
            // message to include the bindings with SQL, which will make this exception a
            // lot more helpful to the developer instead of just the database's errors.
        catch (Exception $e) {
            throw new Exceptions\QueryException(
                $method, $queries, $e
            );
        }

        return $result;
    }

    /**
     * Log a query in the connection's query log.
     *
     * @param string $method
     * @param array $queries
     * @param  float|null  $time
     * @return void
     */
    public function logQuery(string $method, array $queries, $time = null)
    {
        $this->event(new Events\QueryExecuted($method, $queries, $time, $this));

        if ($this->loggingQueries) {
            $this->queryLog[] = compact('method', 'queries', 'time');
        }
    }

    /**
     * Get the elapsed time since a given starting point.
     *
     * @param int $start
     * @return float
     */
    protected function getElapsedTime(int $start): float
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Reconnect to the database.
     *
     * @return void
     *
     * @throws \LogicException
     */
    public function reconnect()
    {
        if (is_callable($this->reconnector)) {
            return call_user_func($this->reconnector, $this);
        }

        throw new LogicException('Lost connection and no reconnector available.');
    }

    /**
     * Disconnect from the underlying PDO connection.
     *
     * @return void
     */
    public function disconnect()
    {
        $this->setClient(null);
    }

    /**
     * Reconnect to the database if a PDO connection is missing.
     *
     * @return void
     */
    protected function reconnectIfMissingConnection()
    {
        if (is_null($this->connection)) {
            $this->reconnect();
        }
    }

    /**
     * Fire the given event if possible.
     *
     * @param  mixed  $event
     * @return void
     */
    protected function event($event)
    {
        if (isset($this->events)) {
            $this->events->dispatch($event);
        }
    }

    /**
     * Register a database query listener with the connection.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function listen(Closure $callback)
    {
        if (isset($this->events)) {
            $this->events->listen(Events\QueryExecuted::class, $callback);
        }
    }

    /**
     * Get the query grammar used by the connection.
     *
     * @return \Starme\Elasticsearch\Query\Grammar
     */
    public function getQueryGrammar(): QueryGrammar
    {
        return $this->queryGrammar;
    }

    /**
     * Get the schema grammar used by the connection.
     *
     * @return \Starme\Elasticsearch\Schema\Grammar
     */
    public function getSchemaGrammar(): SchemaGrammar
    {
        return $this->schemaGrammar;
    }

    /**
     * Get an option from the configuration options.
     *
     * @param string $string
     * @return mixed
     */
    public function getConfig(string $string)
    {
        return $this->config[$string] ?? true;
    }

    /**
     * Get the table prefix for the connection.
     *
     * @return string
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * Set the table prefix in use by the connection.
     *
     * @param string $prefix
     * @return \Starme\Elasticsearch\Connection
     */
    public function setTablePrefix(string $prefix): Connection
    {
        $this->tablePrefix = $prefix;

        $this->getQueryGrammar()->setTablePrefix($prefix);

        return $this;
    }

    /**
     * Get the connection query log.
     *
     * @return array
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Clear the query log.
     *
     * @return void
     */
    public function flushQueryLog()
    {
        $this->queryLog = [];
    }

    /**
     * Enable the query log on the connection.
     *
     * @return void
     */
    public function enableQueryLog()
    {
        $this->loggingQueries = true;
    }

    /**
     * Disable the query log on the connection.
     *
     * @return void
     */
    public function disableQueryLog()
    {
        $this->loggingQueries = false;
    }

    /**
     * Determine whether we're logging queries.
     *
     * @return bool
     */
    public function logging(): bool
    {
        return $this->loggingQueries;
    }

    public function setClient($client)
    {
        $this->connection = $client;
    }

    public function getClient(): Client
    {
        return $this->connection;
    }

    public function setEvents($events)
    {
        $this->events = $events;

        return $this;
    }

    public function getEvents()
    {
        return $this->events;
    }
}