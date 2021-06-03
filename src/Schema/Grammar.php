<?php
namespace Starme\Elasticsearch\Schema;


class Grammar
{

    protected $settings = [];

    public function compileCreateTemplate($blueprint, $command, $connect): array
    {
        return ['mappings' => $this->getColumns($blueprint)];
    }

    public function compileUpdateTemplate($blueprint, $command, $connect): array
    {
        return ['mappings' => $this->getColumns($blueprint)];
    }

    public function compileAlias($blueprint, $command, $connect): array
    {
        return ['index' => $blueprint->getTable(), 'name' => $command->alias];
//        return ['mappings' => $this->getColumns($blueprint)];
    }

    public function compileSetting($blueprint, $command, $connect): array
    {
        $this->settings = array_merge($this->settings, $this->warpCommand($command));
        return ['settings' => ['index' => $this->settings]];
    }

    protected function getColumns($blueprint): array
    {
        return array_merge(...array_map([$this, 'warp'], $blueprint->getColumns()));
    }

    protected function warpCommand($command): array
    {
        return [$command->type => $command->value];
    }

    public function warp($value): array
    {
        if ($value instanceof ColumnDefinition) {
            return [$value->name => ['type' => $value->type]];
        }
        return [];
    }

}