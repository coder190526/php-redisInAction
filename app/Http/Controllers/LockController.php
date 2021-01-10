<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class LockController extends Controller
{
    public static function acquire_lock($lockname,$acquire_timeout=10){
        $identifier = strval(self::uuid());
        $end=time()+$acquire_timeout;
        while(time()<$end){
            if(Redis::setnx('lock:'.$lockname,$identifier)){
                return $identifier;
            }
            sleep(0.001);
        }
        return false;
    }

    public static function release_lock($lockname,$identifier){
        $lockname='lock:'.$lockname;
        while(true){
            try{
                Redis::watch($lockname);
                if(Redis::get($lockname) == $identifier){
                    Redis::transaction(function($redis)use($lockname){
                        $redis->del($lockname);
                    });
                    return true;
                }
                Redis::unwatch();
                break;
            }catch(Exception $e){

            }
        }
        return false;
    }

    public static function acquire_lock_with_timeout($lockname,$acquire_timeout=10,$lock_timeout=10){
        $identifier = strval(self::uuid());
        $lockname='lock:'.$lockname;
        $lock_timeout=(int)ceil($lock_timeout);

        $end=time()+$acquire_timeout;
        while(time()<$end){
            if(Redis::setnx($lockname,$identifier)){
                Redis::expire($lockname,$lock_timeout);
                return $identifier;
            }elseif(Redis::ttl($lockname)<=0){
                Redis::expire($lockname,$lock_timeout);
            }
            sleep(0.001);
        }
        return false;
    }

    public static function acquire_semaphore($semname,$limit,$timeout=10){
        $identifier = strval(self::uuid());
        $now=time();
        $result=Redis::transaction(function($redis)use($semname,$now,$identifier,$timeout){
            $redis->zremrangebyscore($semname,'-inf',$now-$timeout);
            $redis->zadd($semname,$now,$identifier);
            $redis->zrank($semname,$identifier);
        });
        if(array_pop($result) < $limit){
            return $identifier;
        }
        Redis::zrem($semname,$identifier);
        return null;
    }

    public static function release_semaphore($semname,$identifier){
        return Redis::zrem($semname,$identifier);
    }

    public static function acquire_fair_semaphore($semname,$limit,$timeout=10){
        $identifier = strval(self::uuid());
        $czset=$semname.':owner';
        $ctr=$semname.':counter';
        $now=time();
        $result=Redis::transaction(function($redis)use($semname,$now,$timeout,$czset,$ctr){
            $redis->zremrangebyscore($semname,'-inf',$now-$timeout);
            $redis->zinterstore($czset,2,$czset,$semname,['weights'=>[1,0]]);
            $redis->incr($ctr);
        });
        $counter=array_pop($result);
        $result1=Redis::transaction(function($redis)use($semname,$now,$timeout,$czset,$counter){
            $redis->zadd($semname,$now,$identifier);
            $redis->zadd($czset,$counter,$identifier);
            $redis->zrank($czset,$identifier);
        });
        if(array_pop($result1) < $limit){
            return $identifier;
        }
        Redis::zrem($semname,$identifier);
        Redis::zrem($czset,$identifier);
        return null;
    }

    public static function release_fair_semaphore($semname,$identifier){
        $result=Redis::pipeline(function($pipe)use($semname,$identifier){
            $pipe->zrem($semname,$identifier);
            $pipe->zrem($semname.':owner',$identifier);
        });
        return $result[0];
    } 
    
    public static function refresh_fair_semaphore($semname,$identifier){
        if(Redis::zadd($semname,time(),$identifier)){
            self::release_fair_semaphore($semname,$identifier);
            return false;
        }
        return true;
    }  

    public static function acquire_semaphore_with_lock($semname,$limit,$timeout=10){
        $identifier = self::acquire_lock($semname,0.01);
        if($identifier){
            try{
                return self::acquire_fair_semaphore($semname,$limit,$timeout);
            }finally{
                self::release_lock($semname,$identifier);
            }
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
