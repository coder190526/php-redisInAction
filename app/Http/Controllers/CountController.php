<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class CountController extends Controller
{
    const PRECISION=[1,5,60,300,3600,18000,86400];
    const SAMPLE_COUNT=120;

    public function updateCounter(Request $req){
        $name=$req->input('name');
        $this->update_counter($name);
        return ['success'=>true];
    }

    public function getCounter(Request $req){
        $name=$req->input('name');
        $precision=$req->input('precision');
        $data=$this->get_counter($name,$precision);
        return ['list'=>$data,'success'=>true];
    }

    public function update_counter($name,$count=1,$now=null){
        $now=$now ?: time();
        Redis::pipeline(function($pipe)use($now,$name,$count){
            foreach(self::PRECISION as $prec){
                $pnow=(int)($now/$prec)*$prec;
                $hash=$prec.':'.$name;
                $pipe->zadd('known:',0,$hash);
                $pipe->hincrby('count:'.$hash,$pnow,$count);
            }
        });
    }

    public function get_counter($name,$precision){
        $hash=$precision.':'.$name;
        $data=Redis::hgetall('count:'.$hash);
        $to_return=[];
        foreach($data as $key=>$val){
            $to_return[$key]=(int)$val;
        }
        asort($to_return);
        return $to_return;
    }

    //清理计数器要用到守护进程
    public function clean_counter(){
        $passes=0;
        $QUIT=false;
        while(!$QUIT){
            $start=time();
            $index=0;
            while($index<Redis::zcard('known:')){
                $hash=Redis::zrange('known:',$index,$index);
                $index+=1;
                if(!$hash){
                    break;
                }
                $hash=$hash[0];
                $prec=(int)(explode(':',$hash)[0]);
                $bprec=(int)($prec/60) ?:1;
                if($passes % $bprec){
                    continue;
                }
                $hkey='count:'.$hash;
                $cutoff=time()-self::SAMPLE_COUNT*$prec;
                $samples=array_map(function($k){return (int)($k);},Redis::hkeys($hkey));
                sort($samples);
                if(array_search($cutoff,$samples)){
                    $remove = array_search($cutoff,$samples);
                }else{
                    foreach($samples as $k=>$v){
                        if($cutoff < $v){
                            $remove=$k;
                            break;
                        }
                    }
                }
                if($remove){
                    Redis::hdel($hkey,array_slice($samples,0,$remove));
                    if($remove == count($samples)){
                        try{
                            Redis::watch($hkey);
                            if(!Redis::hlen($hkey)){
                                Redis::transaction(function($redis)use($hash){
                                    $redis->zrem('known:',$hash);
                                });
                                $index -= 1;
                            }else{
                                Redis::unwatch();
                            }
                        }catch(Exception $e){

                        }
                    }
                }
            }
            $passes+=1;
            $duration=min((int)time()-$start+1,60);
            sleep(max(60-$duration,1));
        }
    }
}
