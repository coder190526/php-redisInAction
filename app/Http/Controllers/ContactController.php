<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class ContactController extends Controller
{
    const CHARS='`abcdefghijklmnopqrstuvwxyz{';

    public function addUpdateContact(Request $req){
        $user=$req->input('user');
        $contact=$req->input('contact');
        $this->add_update_contact($user,$contact);
        return ['msg'=>'确认成功','success'=>true];
    }

    public function removeContact(Request $req){
        $user=$req->input('user');
        $contact=$req->input('contact');
        $this->remove_contact($user,$contact);
        return ['msg'=>'移除成功','success'=>true];
    }
    public function fetchAutocompleteList(Request $req){
        $user=$req->input('user');
        $prefix=$req->input('prefix');
        $data=$this->fetch_autocomplete_list($user,$prefix);
        if(!empty($data)){
            return ['list'=>$data,'success'=>true];
        }else{
            return ['msg'=>'未找到联系人','success'=>false];
        }
    }

    public function autocompleteOnPrefix(Request $req){
        $guild=$req->input('guild');
        $prefix=$req->input('prefix');
        $data = $this->autocomplete_on_prefix($guild,$prefix);
        if(!empty($data)){
            return ['list'=>$data,'success'=>true];
        }else{
            return ['msg'=>'未找到成员','success'=>false];
        }
    }

    public function joinGuild(Request $req){
        $guild=$req->input('guild');
        $user=$req->input('user');
        $this->join_guild($guild,$user);
        return ['msg'=>'成员加入成功','success'=>true];
    }

    public function leaveGuild(Request $req){
        $guild=$req->input('guild');
        $user=$req->input('user');
        $this->leave_guild($guild,$user);
        return ['msg'=>'成员退出成功','success'=>true];
    }

    public function add_update_contact($user,$contact){
        $ac_list='recent:'.$user;
        Redis::transaction(function($redis)use($ac_list,$contact){
            $redis->lrem($ac_list,0,$contact);
            $redis->lpush($ac_list,$contact);
            $redis->ltrim($ac_list,0,99);
        });
    }

    public function remove_contact($user,$contact){
        Redis::lrem('recent:'.$user,0,$contact);
    }

    public function fetch_autocomplete_list($user,$prefix){
        $candidates=Redis::lrange('recent:'.$user,0,-1);
        $matches=[];
        foreach($candidates as $can){
            if(strpos(strtolower($can),$prefix)===0){
                array_push($matches,$can);
            }
        }
        return $matches;
    }

    public function find_prefix_range($prefix){
        $posn = array_search(substr($prefix,-1),str_split(self::CHARS));
        $suffix=self::CHARS[($posn ?: 1)-1];
        return [substr($prefix,0,-1).$suffix.'{',$prefix.'{'];
    }

    public function autocomplete_on_prefix($guild,$prefix){
        list($start,$end)=$this->find_prefix_range($prefix);
        $identifier=strval($this->uuid());
        $start=$start.$identifier;
        $end=$end.$identifier;
        $zset_name='members:'.$guild;
        Redis::zadd($zset_name,0,$start,0,$end);

        while(1){
            try{
                Redis::watch($zset_name);
                $sindex=Redis::zrank($zset_name,$start);
                $eindex=Redis::zrank($zset_name,$end);
                $erange=min($sindex+9,$eindex-2);
                $result=Redis::transaction(function($redis)use($zset_name,$start,$end,$sindex,$erange){
                        $redis->zrem($zset_name,$start,$end);
                        $redis->zrange($zset_name,$sindex,$erange);
                });
                $items=array_pop($result);
                break;
            }catch(Exception $e){
                continue;
            }
        }
        return array_filter($items,function($v){return strpos($v,'{')===false;});
    }

    public function join_guild($guild,$user){
        Redis::zadd('members:'.$guild,0,$user);
    }

    public function leave_guild($guild,$user){
        Redis::zrem('members:'.$guild,$user);
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
