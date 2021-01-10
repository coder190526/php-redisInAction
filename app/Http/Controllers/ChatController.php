<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\LockController;

class ChatController extends Controller
{
    public function getAllChats(){
        $data=[];
        if(Redis::exists('all_chat_ids')){
            foreach(Redis::smembers('all_chat_ids') as $v){
                $data[]=['id'=>$v,'recipients'=>Redis::zrange('chat:'.$v,0,-1)];
            }
            return ['list'=>$data,'success'=>true];
        }else{
            return ['msg'=>'暂未创建任何群组','success'=>false];
        }
    } 

    public function createChat(Request $req){
        $sender=$req->input('sender');
        $recipients=$req->input('recipients');
        foreach($recipients as $r){
            $r=trim($r);
        }
        $message=$req->input('message');
        $chat_id=$req->input('chat_id') ?? null;
        $id=self::create_chat($sender,$recipients,$message,$chat_id);
        if($id){
            return ['id'=>$id,'success'=>true];
        }else{
            return ['msg'=>'未知错误','success'=>false];
        }
    }

    public function fetchPendingMessages(Request $req){
        $recipient=$req->input('recipient');
        $data=self::fetch_pending_messages($recipient);
        if($data){
            return ['list'=>$data,'success'=>true];
        }else{
            return ['success'=>false];
        }
    }

    public function joinChat(Request $req){
        $chat_id=$req->input('chat_id');
        $user=$req->input('user');
        self::join_chat($chat_id,$user);
        return ['msg'=>'加入群组成功','success'=>true];
    }

    public function leaveChat(Request $req){
        $chat_id=$req->input('chat_id');
        $user=$req->input('user');
        self::leave_chat($chat_id,$user);
        return ['msg'=>'离开群组成功','success'=>true];
    }

    public static function create_chat($sender,$recipients,$message,$chat_id=null){
        $chat_id=$chat_id ?: strval(Redis::incr('ids:chat:'));
        Redis::sadd('all_chat_ids',$chat_id);//存储所有群组ID
        array_push($recipients,$sender);
        foreach($recipients as $r){
            $recipientsd[$r]=0;
        }
        foreach(array_keys($recipientsd) as $re){
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
            $packed=json_encode([
                'id'=>$mid,
                'ts'=>$ts,
                'sender'=>$sender,
                'message'=>$message
            ]);
            Redis::zadd('msgs:'.$chat_id,$mid,$packed);
        }finally{
            LockController::release_lock('chat:'.$chat_id,$identifier);
        }
        return $chat_id;
    }

    public static function fetch_pending_messages($recipient){
        $seen=Redis::zrange('seen:'.$recipient,0,-1,'withscores');
        $result=Redis::pipeline(function($pipe)use($seen){
            foreach($seen as $chat_id=>$seen_id){
                $pipe->zrangebyscore('msgs:'.$chat_id,$seen_id+1,'+inf');
            }
        });
        foreach($seen as $k=>$v){
            $chat_info[]=[
                ['chat_id'=>$k,'seen_id'=>$v],
                $result[array_search($k,array_keys($seen))]
            ];
        }
        foreach($chat_info as $i=>$val){
            $chat_id=$val[0]['chat_id'];
            $seen_id=$val[0]['seen_id'];
            $messages=$val[1];
            if(empty($messages)){
                continue;
            }
            $messages=array_map(function($m){
                return json_decode($m);
            },$messages);
            $seen_id=get_object_vars(array_slice($messages,-1)[0])['id'];
            Redis::zadd('chat:'.$chat_id,$seen_id,$recipient);
            $min_id=Redis::zrange('chat:'.$chat_id,0,0,'withscores');
            Redis::zadd('seen:'.$recipient,$seen_id,$chat_id);
            if(!empty($min_id)){
                Redis::zremrangebyscore('msgs:'.$chat_id,0,$min_id[array_keys($min_id)[0]]);
            }
            $chat_info[$i]=['id'=>$chat_id,'msg'=>$messages];
        }
        return $chat_info;
    }

    public static function join_chat($chat_id,$user){
        $message_id=(int)Redis::get('ids:'.$chat_id);
        Redis::zadd('chat:'.$chat_id,$message_id,$user);
        Redis::zadd('seen:'.$user,$message_id,$chat_id);
    }

    public static function leave_chat($chat_id,$user){
        $result=Redis::pipeline(function($pipe)use($chat_id,$user){
            $pipe->zrem('chat:'.$chat_id,$user);
            $pipe->zrem('seen:'.$user,$chat_id);
            $pipe->zcard('chat:'.$chat_id);
        });
        if(!array_pop($result)){
            Redis::del('msgs:'.$chat_id);
            Redis::del('ids:'.$chat_id);
        }else{
            $oldest=Redis::zrange('chat:'.$chat_id,0,0,'withscores');
            Redis::zremrangebyscore('chat:'.$chat_id,0,$oldest[array_keys($oldest)[0]]);
        }
    }
}
