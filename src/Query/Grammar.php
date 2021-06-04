<?php
namespace Starme\Elasticsearch\Query;

class Grammar
{
    use Grammars\AggregationGrammar;
    use Grammars\Warp;

    /**
     * @var array
     */
    protected $filter = [];
    /**
     * @var array
     */
    protected $must_not = [];

    /**
     * The components that make up a select clause.
     *
     * @var string[]
     */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'index',
        'type',
        'refresh',
        'wheres',
        'orders',
        'limit',
        'offset',
        'lock',
        'scroll'
    ];

    protected $range = [
        '>' => 'gt', '<' => 'lt', '>=' => 'gte', '<=' => 'lte'
    ];

    public function compileSelect(Builder $query): array
    {
        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }
        return $this->concatenate($this->compileComponents($query));
    }

    /**
     * Compile the components necessary for a select clause.
     *
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @return array
     */
    protected function compileComponents(Builder $query): array
    {
        $sql = [];

        foreach ($this->selectComponents as $component) {
            if (isset($query->$component)) {
                $method = 'compile'.ucfirst($component);

                $sql[$component] = array_filter($this->$method($query, $query->$component), function ($item) {
                    if (is_bool($item) || is_int($item)) {
                        return true;
                    }
                    return $item;
                });
            }
        }

        return array_filter($sql);
    }

    /**
     * Compile the "select *" portion of the query.
     *
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @param array $columns
     * @return array
     */
    protected function compileColumns(Builder $query, array $columns): array
    {
        return ['_source' => $columns, 'collapse' => $query->distinct ?: ''];
    }

    /**
     * Compile the "from" portion of the query.
     *
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @param string $index
     * @return array
     */
    protected function compileIndex(Builder $query, string $index): array
    {
        $index = $this->wrapTable($index);
        return compact('index');
    }

    /**
     * Compile the "from" portion of the query.
     *
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @param string $type
     * @return array
     */
    protected function compileType(Builder $query, string $type): array
    {
        $type = $this->wrapType($type);
        return compact('type');
    }

    /**
     * Compile the "select *" portion of the query.
     *
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @param array $scroll
     * @return array
     */
    protected function compileScroll(Builder $query, array $scroll): array
    {
        return $scroll;
    }

    protected function compileRefresh(Builder $query, bool $refresh): array
    {
        return compact('refresh');
    }

    /**
     * Compile the "where" portions of the query.
     *
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @return array
     */
    public function compileWheres(Builder $query): array
    {
        // Each type of where clauses has its own compiler function which is responsible
        // for actually creating the where clauses SQL. This helps keep the code nice
        // and maintainable since each clause has a very small method that it uses.
        if (is_null($query->wheres)) {
            return [];
        }

        return $this->compileWheresToArray($query);
    }

    /**
     * Get an array of all the where clauses for the query.
     *
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @return array
     */
    protected function compileWheresToArray(Builder $query): array
    {
        return collect($query->wheres)->map(function ($where) use ($query) {
            return $this->{"where{$where['type']}"}($query, $where);
        })->all();
    }

    /**
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @param array $where
     * @return array
     */
    protected function whereBasic(Builder $query, array $where): array
    {
        //根据operator判断是否是哪种搜索方式
        $type = $this->getType($where['operator']);
        //根据value判断是否是term/terms
        $meta = $this->compileMeta($where['column'], $where['value'], $where['operator']);
        return compact('type', 'meta');
    }

    /**
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @param array $where
     * @return array
     */
    protected function whereIn(Builder $query, array $where): array
    {
        $type = $this->getType('in');
        $meta = $this->compileMeta($where['column'], $where['value']);
        return compact('type', 'meta');
    }

    /**
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @param array $where
     * @return array
     */
    protected function whereNotIn(Builder $query, array $where): array
    {
        $type = $this->getType('not_in');
        $meta = $this->compileMeta($where['column'], $where['value']);
        return compact('type', 'meta');
    }

    protected function whereLike(Builder $query, array $where): array
    {
        $type = $this->getType('like');
        $meta = $this->compileMeta($where['column'], $where['value'], 'match');
        return compact('type', 'meta');
    }

    protected function whereBetween(Builder $query, array $where): array
    {
        $type = $this->getType('between');
        $meta = $this->compileBetween($where['column'], $where['value']);
        return compact('type', 'meta');
    }

    protected function whereNotBetween(Builder $query, array $where): array
    {
        $type = $this->getType('not_between');
        $meta = $this->compileBetween($where['column'], $where['value']);
        return compact('type', 'meta');
    }

    protected function whereExists(Builder $query, array $where): array
    {
        $type = $this->getType('exists');
        $meta = $this->compileExists($where['column']);
        return compact('type', 'meta');
    }

    protected function whereNotExists(Builder $query, array $where): array
    {
        $type = $this->getType('not_exists');
        $meta = $this->compileExists($where['column']);
        return compact('type', 'meta');
    }

    protected function whereNested(Builder $query, $where): array
    {
        $type = $this->getType($where['boolean']);
        $meta = $this->compileWheres($where['query']);
        return compact('type', 'meta');
    }

    protected function compileExists($column): array
    {
        return ['exists' => ['field' => $column]];
    }


    protected function compileBetween($column, $value): array
    {
        if (count($value) !== 2) {
            return [];
        }
        [$gte, $lt] = $value;
        $range[$column] = compact('gte', 'lt');
        return compact('range');
    }

    protected function compileMeta($column, $value, $op=null): array
    {
        if (is_array($column)) {
            return ['multi_match' => ['query'=>$value, 'fields'=>$column]];
        }

        if (is_array($value)) {
            return ['terms' => [$column => $value]];
        }

        if ($op && isset($this->range[$op])) {
            return ['range' => [$column => [$this->range[$op] => $value]]];
        }

        if ($op == 'match') {
            return ['match' => [$column => $value]];
        }

        return ['term' => [$column => $value]];
    }

    protected function compileRaw(array $wheres, $is_nested=false): array
    {
        $query = [];
        foreach ($wheres as $where) {
            if (count($where['meta']) > 1) {
                $where['meta'] = $this->compileRaw($where['meta'], true);
            }
            if ($where['type'] == 'should') {
                $where['type'] = 'filter';
                $should = [];
                foreach ($where['meta'] as $item) {
                    array_push($should, ['bool'=>['filter'=>$item]]);
                }
                $where['meta'] = ['bool'=>['should'=>$should]];
            }
            if ($is_nested) {
                $query[] = $where['meta'];
                continue;
            }
            $query['bool'][$where['type']][] = $where['meta'];
        }
        return $query;
    }

    /**
     * Compile the "order by" portions of the query.
     *
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @param array $sort
     * @return array
     */
    protected function compileOrders(Builder $query, array $sort): array
    {
        if (empty($sort)) {
            return [];
        }

        return compact('sort');#$this->compileOrdersToArray($query, $orders)];
    }

    /**
     * Compile the query orders to an array.
     *
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @param array $orders
     * @return array
     */
    protected function compileOrdersToArray(Builder $query, array $orders): array
    {
        return array_map(function ($order) {
            return [$order['column'] => ['order' => $order['direction']]];
        }, $orders);
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @param int $limit
     * @return array
     */
    protected function compileLimit(Builder $query, int $limit): array
    {
        return ['size' => $limit];
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @param int $offset
     * @return array
     */
    protected function compileOffset(Builder $query, int $offset): array
    {
        return ['from' => $offset];
    }

    /**
     * Compile an insert statement into SQL.
     *
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @param array $values
     * @return array
     */
    public function compileInsert(Builder $query, array $values): array
    {
        return $this->compileUpdate($query, $values);
    }

    /**
     * Compile an update statement into SQL.
     *
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @param array $values
     * @return array
     */
    public function compileUpdate(Builder $query, array $values): array
    {
        if (empty($values)) {
            return [];
        }
        return array_filter(array_merge_recursive(
            $this->concatenate($this->compileComponents($query)),
            $this->columnize($values, empty($query->wheres))
        ));
    }

    /**
     * Compile an update statement into SQL.
     *
     * @param \Starme\Elasticsearch\Query\Builder $query
     * @param array $values
     * @param array $upsert
     * @return array
     */
    public function compileUpsert(Builder $query, array $values, array $upsert=[]): array
    {
        if (empty($values)) {
            return [];
        }
        return array_merge(
            $this->concatenate($this->compileComponents($query)),
            $this->columnize($values)
        );
    }

    public function compileScript(array $params): array
    {
        $inline = "";
        foreach ($params as $key=>$value) {
            $inline .= sprintf("ctx._source.%s=params.%s;", $key, $key);
        }
        return compact('inline', 'params');
    }

    public function compileDelete(Builder $query): array
    {
        return $this->concatenate($this->compileComponents($query));
    }

    /**
     * @param string $operator
     * @return string
     */
    protected function getType(string $operator): string
    {
        if (in_array($operator, ['!=', '<>', 'not_in', 'not_exists', 'not_between'])) {
            return "must_not";
        }
        if ($operator == 'or') {
            return 'should';
        }
        return "filter";
    }

    /**
     * Concatenate an array of segments, removing empties.
     *
     * @param array $segments
     * @return array
     */
    protected function concatenate(array $segments): array
    {
        if (isset($segments['wheres'])) {
            $segments['wheres'] = ['body'=>['query'=>$this->compileRaw($segments['wheres'])]];
        }
        if (isset($segments['aggregate'])) {
            $segments['aggregate'] = ['body'=> ['aggs' => $segments['aggregate']]];
        }
        if (isset($segments['orders'])) {
            $segments['orders'] = ['body'=> $segments['orders']];
        }
        return array_merge_recursive(...array_values($segments));
    }
}
