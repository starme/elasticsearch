<?php
namespace Starme\Elasticsearch\Query\Grammars;

use Starme\Elasticsearch\Query\Builder;

trait AggregationGrammar
{

    public function compileAggregate(Builder $query, array $aggregate): array
    {
        if( ! $aggregate) {
            return [];
        }
        $method = 'compile'.ucfirst($aggregate['function']);
        return $this->$method($aggregate['columns'], $aggregate['queries']);
    }

    protected function compileTerms($columns, $queries): array
    {
        return $this->compileSimpleAgg('terms', $columns);
    }

    protected function compileMax($columns, $queries): array
    {
        return $this->compileSimpleAgg('max', $columns);
    }

    protected function compileMin($columns, $queries): array
    {
        return $this->compileSimpleAgg('min', $columns);
    }

    protected function compileSum($columns, $queries): array
    {
        return $this->compileSimpleAgg('sum', $columns);
    }

    protected function compileAvg($columns, $queries): array
    {
        return $this->compileSimpleAgg('avg', $columns);
    }

    protected function compileQueries($columns, $queries): array
    {
        $aggs = [];
        foreach ($columns as $column) {
            if ($queries[$column] instanceof Builder) {
                $aggs[$column] = $this->compileAggFilters($queries[$column]);
                continue;
            }
            $aggs = array_merge_recursive($aggs, $this->compileTerms([$column], null));
        }
        return $aggs;
    }

    protected function compileSimpleAgg($type, $columns): array
    {
        foreach ($columns as $column) {
            [$column, $alias] = $this->wrap($column, $this->defaultAggAlias($type, $column));
            $aggs[$alias][$type]['field'] = $column;
        }
        return $aggs;
    }

    protected function defaultAggAlias($prefix, $name): string
    {
        return $prefix . '_' . $name;
    }

    protected function compileAggFilters(Builder $query): array
    {
        $wheres = $this->compileRaw($this->compileWheres($query));
        $filter = [];
        foreach ($wheres['bool'] as $type => $where) {
            if($type == 'filter') {
                $filter = head($where);
                continue;
            }
            $filter = array_merge($filter, $where);
        }
        $aggs = $this->compileAggregate($query, $query->aggregate);
        return compact('filter', 'aggs');
    }

}