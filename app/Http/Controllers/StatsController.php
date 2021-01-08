<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class StatsController extends Controller
{
    public function updateStats(Request $req){
        //$name=$req->input('name');
        //->update_stats($name);
        return ['success'=>true];
    }

    public function getStats(Request $req){
        //$context=$req->input('context');
        //$type=$req->input('type');
        //$data=$this->get_status($context,$type);
        return ['list'=>$data,'success'=>true];
    }

    public function update_stats($context,$type,$value,$timeout=5){
        $destination='stats:'.$context.':'.$type;
        $start_key=$destination.':start';
        $end=time()+$timeout;
        while(time()<$end){
            try{
                Redis::watch($start_key);
                $hour_start=date('Y-m-d H');
                $existing=Redis::get($start_key);
                $result=Redis::transaction(function($redis)use($existing,$hour_start,$destination,$start_key,$value){
                            if($existing && strcmp($hour_start,$existing)){
                                Redis::rename($destination,$destination.':last');
                                Redis::rename($start_key,$destination.':pstart');
                                Redis::set($start_key,$hour_start);
                            }
                            $tkey1=strval(uuid());
                            $tkey2=strval(uuid());
                            Redis::zadd($tkey1,$value,'min');
                            Redis::zadd($tkey2,$value,'max');
                            Redis::zunionstore($destination,[$destination,$tkey1],['aggregate'=>'min']);
                            Redis::zunionstore($destination,[$destination,$tkey2],['aggregate'=>'max']);
                            Redis::del($tkey1,$tkey2);
                            Redis::zincrby($destination,'count');
                            Redis::zincrby($destination,$value,'sum');
                            Redis::zincrby($destination,$value*$value,'sumsq');
                });
                return array_slice($result,-3);
            }catch(Exception $e){
                continue;
            }
        }
    }

    public function get_status($context,$type){
        $key='stats:'.$context.':'.$type;
        $data=Redis::zrange($key,0,-1,'withscores');
        $data['average']=$data['sum']/$data['count'];
        $numerator=$data['sumsq']-$data['sum']**2-$data['count'];
        $data['stddev']=($numerator/($data['count']-1 ?: 1)) ** 0.5;
        return $data;
    }

    //检测页面访问时间
    public function access_time($context){
        $start=time();
        yield;
        $delta=time()-$start;
        $stats=update_stats($context,'AccessTime',$delta);
        $average=$stats[1]/$stats[0];
        Redis::pipeline(function($pipe)use($context,$average){
            $pipe->zadd('slowest:AccessTime',$context,$average);
            $pipe->zremrangebyrank('slowest:AccessTime',0,-101);
        });
    }

    public function uuid(){  
        $chars = md5(uniqid(mt_rand(), true));  
        $uuid = substr ( $chars, 0, 8 ) . '-'
                . substr ( $chars, 8, 4 ) . '-' 
                . substr ( $chars, 12, 4 ) . '-'
                . substr ( $chars, 16, 4 ) . '-'
                . substr ( $chars, 20, 12 );  
        return $uuid;  
    } 
}
