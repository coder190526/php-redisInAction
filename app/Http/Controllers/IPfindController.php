<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class IPfindController extends Controller
{
    // public function ipsToRedis(Request $req){
    //     $filename=$req->input('filename');
    //     $this->import_ips_to_redis($filename);
    //     return ['msg'=>'导入成功','success'=>true];
    // }

    public function citiesToRedis(Request $req){
        $filename=$req->input('filename');
        if (!is_file($filename) && !file_exists($filename)) {
            return ['msg'=>'文件错误','success'=>false];
        }
        $this->import_cities_to_redis($filename);
        return ['success'=>true];
    }

    public function findCity(Request $req){
        $id=$req->input('id');
        $data=$this->find_city_by_id($id);
        if($data){
            return ['city'=>$data,'success'=>true];
        }else{
            return ['msg'=>'没有找到对应城市','success'=>false];
        }
    }

    public function delFile(){
        if(__DIR__){
            $path=array_slice(explode('\\',__DIR__),0,-3);
            $path=implode('\\',$path);
            $path.='\public\China-City-Locations.csv';
        }
        if(Redis::exists('cityid2city:')){
            Redis::del('cityid2city:');
            return ['msg'=>'文件删除成功','path'=>$path,'success'=>true];
        }else{
            return  ['msg'=>'文件已被删除,请重新导入','path'=>$path,'success'=>false];
        }
    }

    public function getCityList(){
        if(__DIR__){
            $path=array_slice(explode('\\',__DIR__),0,-3);
            $path=implode('\\',$path);
            $path.='\public\China-City-Locations.csv';
        }
        if(!Redis::exists('cityid2city:')){
            return ['msg'=>'数据还未导入','path'=>$path,'success'=>false];
        }
        $list=Redis::hgetall('cityid2city:');
        $list=array_slice($list,0,20);
        if($list){
            foreach($list as $v){
                $v=json_decode($v);
                $data[]=['info'=>$v];
            }
            return ['list'=>$data,'success'=>true];
        }else{
            return ['msg'=>'获取列表失败','success'=>false];
        }
    }

    // public function import_ips_to_redis($filename){
    //     $csv_file=[];
    //     if(($handle=fopen($filename,'rb'))!==false){
    //         while(!feof($handle) && $data=fgetcsv($handle)!==false){
    //             array_push($csv_file,$data);
    //         }
    //         fclose($handle);
    //     }
    //     foreach($csv_file as $count=>$row){
    //         $start_ip=$row[0] ?? '';
    //         if(strpos(strtolower($start_ip),'i')!==false){
    //             continue;
    //         }
    //         if(strpos(($start_ip),'.')!==false){
    //             $start_ip=$this->ip_to_score($start_ip);
    //         }elseif(is_numeric($start_ip)){
    //             $start_ip=(int)$start_ip;
    //         }else{
    //             continue;
    //         }
    //         $city_id=$row[2].'_'.strval($count);
    //         Redis::zadd('ip2cityid:',$start_ip,$city_id);
    //     }
    // }

    public function import_cities_to_redis($filename){
        $csv_file=[];
        if(($handle=fopen($filename,'rb'))!==false){
            while(!feof($handle) && ($data=fgetcsv($handle))!==false){
                array_push($csv_file,$data);
            }
            fclose($handle);
        }
        foreach($csv_file as $row){
            $row = eval('return '.iconv('gbk','utf-8',var_export($row,true)).';');
            if(!is_numeric($row[0])){
                continue;
            }
            $city_id=$row[0];
            $province_code=$row[6];
            $province_name=$row[7];
            $city_name=$row[10];
            Redis::hset('cityid2city:',$city_id,json_encode(['id'=>$city_id,'city_name'=>$city_name,'province_code'=>$province_code,'province_name'=>$province_name],JSON_UNESCAPED_UNICODE));
        }
    }

    public function find_city_by_id($city_id){
        $data=Redis::hget('cityid2city:',$city_id);
        if(!$data){
            return null;
        }
        return json_decode(Redis::hget('cityid2city:',$city_id));
    }

    public function ip_to_score($ip_address){   
        $score=0;
        foreach(explode('.',$ip_address) as $v){
            $score=$score*256+(int)$v;
        }
        return $score;
    }

    //系统配置
    public function is_under_maintenance(){
        $LAST_CHECKED=null;
        $IS_UNDER_MAINTENACE=false;
        if($LAST_CHECKED<time()-1){
            $LAST_CHECKED=time();
            $IS_UNDER_MAINTENACE=(bool)Redis::get('is-under-maintenance');
        }
        return $IS_UNDER_MAINTENACE;
    }

    public function set_config($type,$component,$config){
        Redis::set('config:'.$type.':'.$component,json.encode($config));
    }

    public function get_config($type,$component,$wait=1){
        $CONFIGS=[];
        $CHECKED=[];
        $key='config:'.$type.':'.$component;
        if($CHECKED[$key] < time()-$wait){
            $CHECKED[$key]=time();
            $config=json.decode(Redis::get($key)?:'{}');
            $config=array_map(function($k){
                return [strval($k)=>$config[$k]];
            },$config);
            $old_config=$CONFIGS[$key];
            if($config != $old_config){
                $CONFIGS[$key]=$config;
            }
        }
        return $CONFIGS[$key];
    }
}
