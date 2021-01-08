<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\LockController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\LogController;

class WebsiteController extends Controller
{
    public function getUserList(){
        $data=['userList'=>Redis::hgetall('users:'),'success'=>true];
        LogController::logRecent('refresh','demo8.website');
        return response()->json($data)->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }

    public function createUser(Request $req){
        $login=$req->input('loginName');
        $name=$req->input('name');
        if($this->create_user($login,$name)){
            return response()->json(['msg'=>'注册成功','success'=>true])->setEncodingOptions(JSON_UNESCAPED_UNICODE);
        }else{
            return ['success'=>false];
        }
    }

    public function toLogin(Request $req){
        $login=$req->input('loginName');
        $id=Redis::hget('users:',$login);
        if(!$id){
            return ['msg'=>'用户未注册','success'=>false];
        }else{
            return ['id'=>$id,'success'=>true];
        }
    }

    public function getAllData(Request $req){
        $id=$req->input('id');
        $followingArr=[
            'num'=>Redis::hget('user:'.$id,'following'),
            'list'=>array_map(function($v){
                return ['id'=>$v,'name'=>Redis::hget('user:'.$v,'login')];
            },Redis::zrange('following:'.$id,0,-1)),
            'idList'=>Redis::zrange('following:'.$id,0,-1)
        ];
        $followersArr=[
            'num'=>Redis::hget('user:'.$id,'followers'),
            'list'=>array_map(function($v){
                return ['id'=>$v,'name'=>Redis::hget('user:'.$v,'login')];
            },Redis::zrange('followers:'.$id,0,-1)),
            'idList'=>Redis::zrange('followers:'.$id,0,-1)
        ];
        $msgAllList=array_merge($this->get_status_message($id),$this->get_status_message($id,'profile:'));
        usort($msgAllList,function($f,$s){return (int)$f['posted'] < (int)$s['posted'];});
        $msgOwnList=$this->get_status_message($id,'profile:');
        usort($msgOwnList,function($f,$s){return (int)$f['posted'] < (int)$s['posted'];});
        $data=[
            'msgList'=>['all'=>$msgAllList,'own'=>$msgOwnList],
            'following'=>$followingArr,
            'followers'=>$followersArr,
            'userList'=>Redis::hgetall('users:'),
            'success'=>true
        ];
        LogController::logRecent('refresh','demo8.website');
        return response()->json(['allData'=>$data,'success'=>true])->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }

    public function postMsg(Request $req){
        $uid=$req->input('id');
        $msg=$req->input('msg');
        $id=$this->post_status($uid,$msg,[]);
        if($id){
            return ['success'=>true];
        }else{
            return ['success'=>false];
        }
    }

    public function followUser(Request $req){
        $uid=$req->input('uid');
        $other_uid=$req->input('other_uid');
        if($this->follow_user($uid,$other_uid)){
            return ['success'=>true];
        }else{
            return ['success'=>false];
        }
    }

    public function unfollowUser(Request $req){
        $uid=$req->input('uid');
        $other_uid=$req->input('other_uid');
        if($this->unfollow_user($uid,$other_uid)){
            return ['success'=>true];
        }else{
            return ['success'=>false];
        }
    }

    public function delMsg(Request $req){
        $uid=$req->input('uid');
        $status_id=$req->input('status_id');
        if($this->delete_status($uid,$status_id)){
            return ['success'=>true];
        }else{
            return ['success'=>false];
        }
    }

    public function create_user($login,$name){
        $llogin = strtolower($login);
        $lock=LockController::acquire_lock_with_timeout('user:'.$llogin,1);
        if(empty($lock)){
            return null;
        }
        if(Redis::hget('users:',$llogin)){
            LockController::release_lock('user:'.$llogin,$lock);
            return null;
        }
        $id=Redis::incr('user:id:');
        Redis::pipeline(function($pipe)use($login,$llogin,$id,$name){
            $pipe->hset('users:',$llogin,$id);
            $pipe->hmset('user:'.$id,[
                'login'=>$login,
                'id'=>$id,
                'name'=>$name,
                'followers'=>0,
                'following'=>0,
                'posts'=>0,
                'signup'=>time()
            ]);
        });
        LockController::release_lock('user:'.$llogin,$lock);
        return $id;
    }

    public function create_status($uid,$message,$data){
        list($login,$id)=Redis::pipeline(function($pipe)use($uid){
            $pipe->hget('user:'.$uid,'login');
            $pipe->incr('status:id:');
        });
        if(empty($login)){
            return null;
        }
        $data['message']=$message;
        $data['posted']=time();
        $data['id']=$id;
        $data['uid']=$uid;
        $data['login']=$login;
        Redis::pipeline(function($pipe)use($id,$uid,$data){
            foreach($data as $k=>$v){
                $pipe->hmset('status:'.$id,$k,$v);
            }
            $pipe->hincrby('user:'.$uid,'posts',1);
        });
        return $id;
    }

    public function get_status_message($uid,$timeline='home:',$page=1,$count=30){
        $statuses=Redis::zrevrange($timeline.$uid,($page-1)*$count,$page*$count-1);
        $result=Redis::pipeline(function($pipe)use($statuses){
            foreach($statuses as $id){
                $pipe->hgetall('status:'.$id);
            }
        });
        return array_filter($result,function($val){
            return (bool)$val;
        });
    }

    public function follow_user($uid,$other_uid){
        $fkey1='following:'.$uid;
        $fkey2='followers:'.$other_uid;
        if(Redis::zscore($fkey1,$other_uid)){
            return null;
        }
        $now=time();
        $result=array_slice(Redis::pipeline(function($pipe)use($fkey1,$fkey2,$now,$uid,$other_uid){
            $pipe->zadd($fkey1,$now,$other_uid);
            $pipe->zadd($fkey2,$now,$uid);
            $pipe->zrevrange('profile:'.$other_uid,0,999,'withscores');
        }),-3);
        list($following,$followers,$status_and_score)=$result;
        Redis::pipeline(function($pipe)use($uid,$following,$other_uid,$followers,$status_and_score){
            $pipe->hincrby('user:'.$uid,'following',(int)$following);
            $pipe->hincrby('user:'.$other_uid,'followers',(int)$followers);
            if(!empty($status_and_score)){
                foreach($status_and_score as $k=>$v){
                    $pipe->zadd('home:'.$uid,$v,$k);
                }
            }
            $pipe->zremrangebyrank('home:'.$uid,0,-1001);
        });
        return true;
    }

    public function unfollow_user($uid,$other_uid){
        $fkey1='following:'.$uid;
        $fkey2='followers:'.$other_uid;
        if(!Redis::zscore($fkey1,$other_uid)){
            return null;
        }
        $result=array_slice(Redis::pipeline(function($pipe)use($fkey1,$fkey2,$uid,$other_uid){
            $pipe->zrem($fkey1,$other_uid);
            $pipe->zrem($fkey2,$uid);
            $pipe->zrevrange('profile:'.$other_uid,0,999);
        }),-3);
        list($following,$followers,$statuses)=$result;
        Redis::pipeline(function($pipe)use($uid,$other_uid,$following,$followers,$statuses){
            $pipe->hincrby('user:'.$uid,'following',(int)-$following);
            $pipe->hincrby('user:'.$other_uid,'followers',(int)-$followers);
            if(!empty($statuses)){
                $pipe->zrem('home:'.$uid,...$statuses);
            }
        });
        return true;
    }

    public function post_status($uid,$message,$data){
        $id=$this->create_status($uid,$message,$data);
        if(!$id){
            return null;
        }
        $posted=Redis::hget('status:'.$id,'posted');
        if(!$posted){
            return null;
        }
        $post=[strval($id)=>(float)$posted];
        foreach($post as $k=>$v){
            Redis::zadd('profile:'.$uid,$v,$k);
        }
        $this->syndicate_status($uid,$post);
        return $id;
    }

    public function syndicate_status($uid,$post,$start=0){
        $followers=Redis::zrangebyscore('followers:'.$uid,$start,'inf',['withscores'=>true,'limit'=>[0,1000]]);
        Redis::pipeline(function($pipe)use($followers,$post){
            foreach($followers as $follower=>$start){
                foreach($post as $k=>$v){
                    $pipe->zadd('home:'.$follower,$v,$k);
                }
                $pipe->zremrangebyrank('home:'.$follower,0,-1001);
            }
        });
        if(count($followers) >= 1000){
            QueueController::execute_later('default','syndicate_status',[$uid,$post,$start]);
        }
    }

    public function delete_status($uid,$status_id){
        $key='status:'.$status_id;
        $lock=LockController::acquire_lock_with_timeout($key,1);
        if(!$lock){
            return null;
        }
        if(Redis::hget($key,'uid') != strval($uid)){
            LockController::release_lock($key,$lock);
            return null;
        }
        Redis::pipeline(function($pipe)use($key,$uid,$status_id){
            $pipe->del($key);
            $pipe->zrem('profile:'.$uid,$status_id);
            $pipe->zrem('home:'.$uid,$status_id);
            $pipe->hincrby('user:'.$uid,'posts',-1);
        });
        LockController::release_lock($key,$lock);
        return true;
    } 
}
