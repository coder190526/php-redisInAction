<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\LogController;

class ShopController extends Controller
{
    public function getInitData(){
        Redis::hmset('users:17',['id'=>17,'name'=>'小李','funds'=>43]);
        Redis::del('inventory:17');
        Redis::sadd('inventory:17','itemL','itemM','itemN');
        Redis::hmset('users:27',['id'=>27,'name'=>'小张','funds'=>125]);
        Redis::del('inventory:27');
        Redis::sadd('inventory:27','itemO','itemP','itemQ');
        Redis::zremrangebyrank('market:',0,-1);
        $data=[
            ['info'=>Redis::hgetall('users:17'),'inventory'=>Redis::smembers('inventory:17')],
            ['info'=>Redis::hgetall('users:27'),'inventory'=>Redis::smembers('inventory:27')]
        ];
        LogController::logRecent('refresh','demo4.shop');
        return response()->json($data)->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }

    public function listItem(Request $request){
        $req_itemid=$request->input('itemid');
        $req_sellerid=$request->input('sellerid');
        $req_price=$request->input('price');
        $inventory='inventory:'.$req_sellerid;
        $item=$req_itemid.'.'.$req_sellerid;
        $end=time()+5;

        while(time() < $end){
            try{
                Redis::watch($inventory);
                if(!Redis::sismember($inventory,$req_itemid)){
                    Redis::unwatch();
                    return ['success'=>false];
                }
                Redis::transaction(function($redis)use($req_price,$item,$inventory,$req_itemid){
                    $redis->zadd('market:',$req_price,$item);
                    $redis->srem($inventory,$req_itemid);
                });
                return ['success'=>true];
            }catch(Exception $e){

            }
        }
        return ['success'=>false];
    }

    public function getAllData(){
        $userArr = [
            ['info'=>Redis::hgetall('users:17'),'inventory'=>Redis::smembers('inventory:17')],
            ['info'=>Redis::hgetall('users:27'),'inventory'=>Redis::smembers('inventory:27')]
        ];
        $data=['success'=>true,'user'=>$userArr,'market'=>Redis::zrange('market:',0,-1,'withscores')];
        LogController::logRecent('refresh','demo4.shop');
        return response()->json($data)->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }

    public function buyItem(Request $request){
        $req_buyerid=$request->input('buyerid');
        $req_itemid=$request->input('itemid');
        $req_sellerid=$request->input('sellerid');
        $req_price=$request->input('price');
        $buyer='users:'.$req_buyerid;
        $seller='users:'.$req_sellerid;
        $item=$req_itemid.'.'.$req_sellerid;
        $inventory='inventory:'.$req_buyerid;
        $end=time()+10;

        while(time() < $end){
            try{
                Redis::watch('market:',$buyer);
                $price=Redis::zscore('market:',$item);
                $funds=(int)Redis::hget($buyer,'funds');
                if($price!=$req_price || $price>$funds){
                    Redis::unwatch();
                    return ['success'=>false];
                }
                Redis::transaction(function($redis)use($seller,$buyer,$price,$item,$inventory,$req_itemid){
                    $redis->hincrby($seller,'funds',(int)$price);
                    $redis->hincrby($buyer,'funds',(int)-$price);
                    $redis->sadd($inventory,$req_itemid);
                    $redis->zrem('market:',$item);
                });
                return ['success'=>true];
            }catch(Exception $e){

            }
        }
        return ['success'=>false];
    }
}
