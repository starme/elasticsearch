<?php

namespace Tests\Elasticsearch;

use Starme\Elasticsearch\Eloquent\Model;
use Tests\TestCase;

class TestModel extends Model
{

    protected $table = 'test_a';

}

class ElasticModelTest extends TestCase
{

    protected $model;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        $this->model = new TestModel;
        parent::__construct($name, $data, $dataName);
    }

    public function test_model_connection()
    {
//        dd($this->model->getConnection());
        dd(TestModel::where('age', '>', 10)->scroll('1m'));
    }
}