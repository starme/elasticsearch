<?php

namespace Tests\Elasticsearch;

use Tests\TestCase;

class ElasticTest extends TestCase
{

    public function test_elastic_search()
    {
        $wheres = app('es.connection')->table('test_a')
//            ->whereNull('id')
            ->where(['code', 'name'], 'tmp')
//            ->where('superior_code', '000001')
//            ->whereIn('code', ["000003", "000020"])
//            ->whereBetween('age', [20, 30])
//            ->whereNotBetween('age', [20, 30])
//            ->orWhere(function ($query){
//                foreach (['1'=>'1', '2'=>'2'] as $key=>$value) {
//                    $query->where(function ($query) use ($value, $key) {
//                        return $query->where(['cycle_id'=>$key, 'year'=>$value]);
//                    });
//                }
//                return $query;
//                return $query->where('cycle_id', 73)->where('cycle_id', 4);
//            })
//            ->paginate(10, ['code']);
            ->limit(1)
            ->orderBy(['age'=>'asc', 'code'=>'desc'])
            ->scroll('1m', 'FGluY2x1ZGVfY29udGV4dF91dWlkDXF1ZXJ5QW5kRmV0Y2gBFmczbE1Yb18tUkZHYWRxYUhvOVFPZkEAAAAAAAAAGBZNSS1Ub2tNNlJqaXdHczFKWVYtY2Fn')
            ->get(['code', 'age']);
//        $wheres = app('es.connection')->table('kpi_users')->find(5639485, ["high_code", "code", "name", "cycle_id"]);
        dd($wheres);
    }

    public function test_elastic_find()
    {
        $ret = app('es.connection')->table('test_a')->find(1000);
        dd($ret);
    }

    public function test_elastic_create()
    {
        $ret = app('es.connection')->table('test_a')->insert([
//            'id' => 2,
            'name' => 'php',
            'age' => 10,
            'code' => 'php',
            'created_at' => time(),
            'updated_at' => time()
        ]);
        dd($ret);
    }

    public function test_elastic_update()
    {
        dd(app('es.connection')->table('test_a')->where('age', 23)
            ->update([
                'age' => 13,
            ]));
    }

    public function test_elastic_delete()
    {
        dd(app('es.connection')->table('test_a')->where()->delete());
//        dd(app('es.connection')->table('test_a')->delete(11));
    }

    public function test_elastic_aggs()
    {
//        min max avg sum
//        dd(app('es.connection')->table('test_a')->where('id', '<', 10)->min('age'));

//        dd(app('es.connection')->table('test_a')->groupBy('age')->get());

        dd(app('es.connection')->table('test_a')->groupByRaw(['test_age'=>function($query) {
            return $query->where('age', '<', 100)->groupByRaw(['test_age_2'=>function($query) {
                return $query->where('code', 'like', 'sql')->groupBy('age as test_age_3');
            }]);
        }])->get());
    }

}
