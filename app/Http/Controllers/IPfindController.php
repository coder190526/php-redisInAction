<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class IPfindController extends Controller
{
    public function ipsToRedis(Request $req){
        $filename=$req->input('filename');
        $this->import_ips_to_redis($filename);
        return ['msg'=>'导入成功','success'=>true];
    }

    public function citiesToRedis(Request $req){
        $filename=$req->input('filename');
        $this->import_cities_to_redis($filename);
        return ['msg'=>'导入成功','success'=>true];
    }

    public function findCity(Request $req){
        $ip=$req->input('ip');
        $data=$this->find_city_by_ip($ip);
        if($data){
            ['city'=>$data,'success'=>true];
        }else{
            ['msg'=>'查询失败','success'=>false];
        }
    }

    public function import_ips_to_redis($filename){
        $csv_file=[];
        if($handle=fopen($filename,'rb')!==false){
            while(!feof($handle) && $data=fgetcsv($handle)!==false){
                array_push($csv_file,$data);
            }
            fclose($handle);
        }
        foreach($csv_file as $count=>$row){
            $start_ip=$row?$row[0]:'';
            if(strpos(strtolower($start_ip),'i')!==false){
                continue;
            }
            if(strpos(($start_ip),'.')!==false){
                $start_ip=ip_to_score($start_ip);
            }elseif(is_numeric($start_ip)){
                $start_ip=(int)$start_ip;
            }else{
                continue;
            }
            $city_id=$row[2].'_'.strval($count);
        }
        Redis::zadd('ip2cityid:',$start_ip,$city_id);
    }

    public function import_cities_to_redis($filename){
        $csv_file=[];
        if($handle=fopen($filename,'rb')!==false){
            while(!feof($handle) && $data=fgetcsv($handle)!==false){
                array_push($csv_file,$data);
            }
            fclose($handle);
        }
        foreach($csv_file as $row){
            if(count($row)<4 || !is_numeric($row[0])){
                continue;
            }
            $row=array_map(function($val){
                return $val;
            },$row);
            $city_id=$row[0];
            $country=$row[1];
            $region=$row[2];
            $city=$row[3];
            Redis::hset('cityid2city:',$city_id,json_encode([$city,$region,$country]));
        }
    }

    public function find_city_by_ip($ip_address){
        if(is_string($ip_address)){
            $ip_address=$this->ip_to_score($ip_address);
        }
        $city_id=Redis::zrevrangebyscore('ip2cityid',$ip_address,0,['limit'=>[0,1]]);
        if(!$city_id){
            return null;
        }
        $city_id=str_split($city_id[0],'_')[0];
        return json.decode(Redis::hget('cityid2city:',$city_id));
    }

    public function ip_to_score($ip_address){   
        $score=0;
        foreach(explode('.',$ip_address) as $v){
            $score=$score*256+(int)$v;
        }
        return $score;
    }

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
