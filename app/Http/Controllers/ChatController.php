<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\LockController;

class ChatController extends Controller
{
    public static function create_chat($sender,$recipients,$message,$chat_id=null){
        $chat_id=$chat_id ?: strval(Redis::incr('ids:chat:'));
        array_push($recipients,$sender);
        foreach($recipients as $r){
            $recipientsd[$r]=0;
        }
        foreach($recipientsd as $re){
            Redis::zadd('chat:'.$chat_id,0,$re);
            Redis::zadd('seen:'.$re,0,$chat_id);
        }
        return self::send_message($chat_id,$sender,$message);
    }

    public static function send_message($chat_id,$sender,$message){
        $identifier=LockController::acquire_lock('chat:'.$chat_id);
        if(!$identifier){
            throw new Exception('Couldn`t get the lock');
        }
        try{
            $mid=Redis::incr('ids:'.$chat_id);
            $ts=time();
            $packed=json.encode([
                'id'=>$mid,
                'ts'=>$ts,
                'sender'=>$sender,
                'message'=>$message
            ]);
            Redis::zadd('msg:'.chat_id,$mid,$packed);
        }finally{
            LockController::release_lock('chat:'.$chat_id,$identifier);
        }
        return $chat_id;
    }

    public static function fetch_pending_messages($recipient){
        $seen=Redis::zrange('seen:'.$req_recipient,0,-1,'withscores');
        $result=Redis::pipeline(function($pipe){
            foreach($seen as $chat_id=>$seen_id){
                $pipe->zrangebyscore('msgs:'.$chat_id,$seen_id+1,'inf');
            }
        });
        
        $chat_info=array_combine(array_flip($seen),$result);
        foreach($chat_info as $i=>$val){
            if(!$val[1]){
                continue;
            }
            $message=json.decode($val[1]);
            $seen_id=array_slice($message,-1)['id'];
            Redis::zadd('chat:'.$chat_id,$seen_id,$recipient);
            $mid_id=Redis::zrange('chat:'.$chat_id,0,0,'withscores');
            Redis::zadd('seen:'.$recipient,$seen_id,$chat_id);
            if($mid_id){
                Redis::zremrangebyscore('msgs:'.$chat_id,0,$mid_id[0][1]);
            }
            $chat_info[i]=$val[0];
        }
        return $chat_info;
    }

    public static function join_chat($chat_id,$user){
        $message_id=(int)Redis::get('ids:'.$chat_id);
        Redis::zadd('chat:'.$chat_id,$message_id,$user);
        Redis::zadd('seen:'.$user,$message_id,$chat_id);
    }

    public static function leave_chat($chat_id,$user){
        $result=Redis::pipeline(function($pipe){
            $pipe->zrem('chat:'.$chat_id,$user);
            $pipe->zrem('seen:'.$user,$chat_id);
            $pipe->zcard('chat:'.$chat_id);
        });
        if(!array_pop($result)){
            Redis::del('msgs:'.$chat_id);
            Redis::del('ids:'.$chat_id);
        }else{
            $oldest=Redis::zrange('chat:'.$chat_id,0,0,'withscores');
            Redis::zremrangebyscore('chat:'.$chat_id,0,$oldest[0][1]);
        }
    }
}
