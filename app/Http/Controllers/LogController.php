<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class LogController extends Controller
{
    const SEVERITY=[
        'logging.DEBUG'=>'debug',
        'logging.INFO'=>'info',
        'logging.WARNING'=>'warning',
        'logging.ERROR'=>'error',
        'logging.CRITICAL'=>'critical'
    ];

    public function getRecentLogList(Request $req){
        $name=$req->input('name');
        $severity=$req->input('severity') ?: 'logging.INFO';
        $severity=strtolower(strval(self::SEVERITY[$severity] ?: $severity));
        $list=Redis::lrange('recent:'.$name.':'.$severity,0,-1);
        $count=Redis::llen('recent:'.$name.':'.$severity);
        $data=array_map(function($v)use($name,$severity){
            return ['name'=>$name,'severity'=>$severity,'log'=>$v];
        },$list);
        return response()->json(['list'=>$data,'count'=>$count,'success'=>true])->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }

    public function getCommonLogList(Request $req){
        $name=$req->input('name');
        $severity=$req->input('severity') ?: 'logging.INFO';
        $severity=strtolower(strval(self::SEVERITY[$severity] ?: $severity));
        $list=Redis::lrange('common:'.$name.':'.$severity,0,-1);
        $count=Redis::llen('common:'.$name.':'.$severity);
        $data=array_map(function($v){
            return ['name'=>$name,'severity'=>$severity,'log'=>$v];
        },$list);
        return response()->json(['list'=>$data,'count'=>$count,'success'=>true])->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }

    public static function logRecent(Request $req){
        $name=$req->input('name');
        $message=$req->input('message');
        self::log_recent($name,$message);
    }

    public static function log_recent($name,$message,$severity='logging.INFO'){
        $severity=strtolower(strval(self::SEVERITY[$severity] ?: $severity));
        $destination = 'recent:'.$name.':'.$severity;
        date_default_timezone_set('Etc/GMT-8');
        $message=date("Y-m-d H:i:s").' '.$message;
        Redis::pipeline(function($pipe)use($destination,$message){
            $pipe->lpush($destination,$message);
            $pipe->ltrim($destination,0,99);
        });
    }

    public static function log_common($name,$message,$severity='logging.INFO',$timeout=5){
        $severity=strtolower(strval(self::SEVERITY[$severity] ?: $severity));
        $destination = 'common:'.$name.':'.$severity;
        $start_key=$destination.':start';
        $end=time()+$timeout;
        while(time()<$end){
            try{
                Redis::watch($start_key);
                $hour_start=(int)date('H');
                $existing=Redis::get($start_key);
                Redis::transaction(function($redis)use($existing,$hour_start,$start_key,$destination){
                    if($existing && $existing<$hour_start){
                        Redis::rename($destination,$destination.':last');
                        Redis::rename($start_key,$destination.':pstart');
                        Redis::set($start_key,$hour_start);
                    }
                    Redis::zincrby($destination,1,$message);
                    self::log_recent($name,$message,$severity);
                });
                return;
            }catch(Exception $e){
                continue;
            }
        }
    }
}
