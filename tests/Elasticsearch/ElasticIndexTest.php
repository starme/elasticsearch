<?php

namespace Tests\Elasticsearch;

use Starme\Elasticsearch\Eloquent\Model;
use Tests\TestCase;


class ElasticIndexTest extends TestCase
{


    public function test_create_template()
    {
        app('es.connection')->getSchemaBuilder()->createTemplate('test', function ($index) {
            $index->string('a');
            $index->string('b');
            $index->string('c');

            //setting
            $index->shards(5);
            $index->replicas(2);
            $index->results(50000);
        });
    }

    public function test_update_template()
    {
        app('es.connection')->getSchemaBuilder()->alterTemplate('test_template', function ($index) {
            $index->string('a');
            $index->string('b');
            $index->string('c');

            //setting
            $index->shards(5);
            $index->replicas(2);
            $index->results(50000);
        });
    }

    public function test_alias()
    {
        app('es.connection')->schema()->alias('_cycle_dept', 'kpi_dept');
    }
}