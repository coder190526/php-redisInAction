<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\LockController;

class QueueController extends Controller
{
    public static function send_sold_email_via_queue($seller,$item,$price,$buyer){
        $data=[
            'seller_id'=>$seller,
            'item_id'=>$item,
            'price'=>$price,
            'buyer_id'=>$buyer,
            'time'=>time(),
        ];
        Redis::rpush('queue:email',json_encode($data));
    }

    public static function process_sold_email_queue(){
        $QUIT=false;
        while(!$QUIT){
            $packed=Redis::blpop('queue:email',30);
            if(!$packed){
                continue;
            }
            $to_send=json_decode($packed[1]);
            try{
                fetch_data_and_send_sold_email($to_send);
            }catch(Exception $e){
                error_log('Failed to send sold email');
            }
        }
    }

    public static function worker_watch_queue($queues,$callbacks){
        $QUIT=false;
        while(!$QUIT){
            $packed=Redis::blpop($queues,30);
            if(!$packed){
                continue;
            }
            list($name,$args)=json_decode($packed[1]);
            if(!in_array($name,$callbacks)){
                error_log('Unknown callback');
                continue;
            }
            $callbacks[$name](...$args);
        }
    }

    public static function execute_later($queue,$name,$args,$delay=0){
        $identifier = strval(self::uuid());
        $item=json_encode([$identifier,$queue,$name,$args]);
        if($delay>0){
            Redis::zadd('delayed:',time()+$delay,$item);
        }else{
            Redis::rpush('queue:'.$queue,$item);
        }
        return $identifier;
    }

    public static function poll_queue(){
        $QUIT=false;
        while(!$QUIT){
            $item=Redis::zrange('delayed:',0,0,'withscores');
            if(!$item || $item[0][1] > time()){
                sleep(0.01);
                continue;
            }
            $item=$item[0][0];
            list($identifier,$queue,$function,$args)=json_decode($item);

            $locked=LockController::acquire_lock($identifier);
            if(!$locked){
                continue;
            }
            if(Redis::zrem('delayed:',$item)){
                Redis::rpush('queue:'.$queue,$item);
            }
            LockController::release_lock($identifier,$locked);
        }
    }

    public static function uuid(){  
        $chars = md5(uniqid(mt_rand(), true));  
        $uuid = substr ( $chars, 0, 8 ) . '-'
                . substr ( $chars, 8, 4 ) . '-' 
                . substr ( $chars, 12, 4 ) . '-'
                . substr ( $chars, 16, 4 ) . '-'
                . substr ( $chars, 20, 12 );  
        return $uuid;  
    } 
}
