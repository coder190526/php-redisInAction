<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class SearchController extends Controller
{
    //array_unique(explode('',$STOP_WORDS))
    const STOP_WORDS= <<<EOF
        able about across after all almost also am among an and any are as at be because been but by can cannot
        could dear did do does either else ever every for from get got had has have he her hers him his how however
        if in into is it its just least let like likely may me might most must my neither no nor not of off often on
        only or other our own rather said say says she shold since so some than that the their them then there these
        they this tis to too twas us wants was we were what when where which while who whom why will with would yet
        you your
EOF;

    const TO_ECPM=[
        'cpc'=>'cpc_to_ecpm',
        'cpa'=>'cpa_to_ecpm',
        'cpm'=>'get_last_arg'
    ];
    public $AVERAGE_PER_1K=[];

    public function tokenize($content){
        $words=[];
        preg_match_all("/[a-z']{2,}/",strtolower($content),$pregArr);
        foreach($pregArr[0] as $match){
            $word=trim($match,"'");
            if(strlen($word) >= 2){
                array_push($words,$word);
                $words=array_unique($words);
            }
        }
        return array_diff($words,array_unique(str_split($STOP_WORDS)));
    }

    public function index_document($docid,$content){
        $words=$this->tokenize($content);
        $result=Redis::pipeline(function($pipe)use($words,$docid){
            foreach($words as $word){
                $pipe->sadd('idx:'.$word,$docid);
            }
        });
        return count($result);
    }

    public function _set_common($method,$names,$ttl=30,$execute=true){
        $id=strval($this->uuid());
        if($execute){
            $names=array_map(function($v){return 'idx:'.$v;},$names);
            if($execute){
                Redis::pipeline(function($pipe)use($id,$names,$ttl){
                    $pipe->$method('idx:'.$id,...$names);
                    $pipe->expire('idx:'.$id,$ttl);
                });
            }
            return $id;
        }
    }

    public function intersect($items,$ttl=30,$_execute=true){
        return _set_common('sinterstore',$items,$ttl,$_execute);
    }

    public function union($items,$ttl=30,$_execute=true){
        return _set_common('sunionstore',$items,$ttl,$_execute);
    }

    public function difference($items,$ttl=30,$_execute=true){
        return _set_common('sdiffstore',$items,$ttl,$_execute);
    }

    public function parse($query){
        $unwatch=[];
        $all=[];
        $current=[];
        preg_match_all("/[+-]?[a-z']{2,}/",strtolower($query),$macthArr);
        foreach($macthArr[0] as $match){
            $word=$match;
            $prefix=substr($word,0,1);
            if(strpos('+-',$prefix)!==false){
                $word=substr($word,1);
            }else{
                $prefix=null;
            }
            $word=trim($word,"'");
            if(strlen($word)<2 || strpos(array_unique(str_split(self::STOP_WORDS)),$word)!==false){
                continue;
            }
            if($prefix == '-'){
                $unwatch[]=$word;
                $unwatch=array_unique($unwatch);
                continue;
            }
            if($current && !($prefix)){
                $all[]=$current;
                $current=[];
            }
            $current[]=$word;
            $current=array_unique($current);
        }
        if(!empty($current)){
            $all[]=$current;
        }
        return [$all,$unwatch];
    }

    public function parse_and_search($query,$ttl=30){
        list($all,$unwatch)=$this->parse($query);
        if(!empty($all)){
            return null;
        }
        $to_intersect=[];
        foreach($all as $syn){
            if(strlen($syn)>1){
                array_push($to_intersect,$this->union($syn,$ttl));
            }else{
                array_push($to_intersect,$syn[0]);
            }
        }
        if(count($to_intersect) > 1){
            $intersect_result=$this->intersect($to_intersect,$ttl);
        }else{
            $intersect_result=$to_intersect[0];
        }
        if(!empty($unwatch)){
            array_splice($unwatch,0,0,$intersect_result);
            return $this->difference($unwatch,$ttl);
        }
        return $intersect_result;
    }

    public function search_and_sort($query,$id=null,$ttl=300,$sort='-updated',$start=0,$num=20){
        $desc=(bool)(strpos($sort,'-') === 0);
        $sort=ltrim($sort,'-');
        $by='kb:doc:*->'.$sort;
        $alpha=in_array($sort,['update','id','created']);
        if(!empty($id) && Redis::expire($id,$ttl)){
            $id=null;
        }
        if(empty($id)){
            $id=$this->parse_and_search($query,$ttl);
        }
        $result=Redis::pipeline(function($pipe)use($id,$by,$start,$num){
            $pipe->scard('ids:'.$id);
            $pipe->sort('idx:'.$id,['by'=>$by,'sort'=>'desc','limit'=>[$start,$num],'alpha'=>true]);
        });
        return [$result[0],$result[1],$id];
    }

    public function search_and_zsort($query,$id=null,$ttl=300,$update=1,$vote=0,$start=0,$num=20,$desc=true){
        if(!empty($id) && !Redis::expire($id,$ttl)){
            $id=null;
        }
        if(empty($id)){
            $id=$this->parse_and_search($query,$ttl);
            $scored_search=['id'=>0,'sort:update'=>$update,'sort:votes'=>$vote];
            $id=$this->zintersect($scored_search,$ttl);
        }
        $result=Redis::pipeline(function($pipe)use($id,$start,$num){
            $pipe->zcard('idx:'.$id);
            if($desc){
                $pipe->zrevrange('idx:'.$id,$start,$start+$num-1);
            }else{
                $pipe->zrange('idx:'.$id,$start,$start+$num-1);
            }
        });
        return [$result[0],$result[1],$id];
    }

    public function _zset_common($method,$scores,$ttl,$params){
        $id=strval($this->uuid());
        if($params['_execute']){
            $execute=$params['_execute'];
            unset($params['_execute']);
        }else{
            $execute=true;
        }
        Redis::pipeline(function($pipe)use($scores,$method,$id,$params,$ttl){
            foreach(array_keys($scores) as $key){
                $scores['idx:'.$key]=array_splice($scores,array_keys($scores,$key)[0],1)[0];
            }
            $pipe->$method('idx:'.$id,$scores,$params);
            $pipe->expire('idx:'.$id,$ttl);
        });
        return $id;
    }

    public function zintersect($items,$ttl,...$params){
        return $this->_zset_common('zinterstore',$items,$ttl,$params[0] ?: $params);
    }

    public function zunion($items,$ttl,...$params){
        return $this->_zset_common('zunionstore',$items,$ttl,$params[0] ?: $params);
    }

    public function string_to_score($string,$ignore_case=true){
        if($ignore_case){
            $string=strtolower($string);
        }
        $pieces=array_map('ord',substr($string,0,6));
        while(count($pieces)<6){
            array_push($pieces,-1);
        }
        $score=0;
        foreach($pieces as $piece){
            $score=$score*257+$piece+1;
        }
        return $score*2+(strlen($string)>6);
    }

    public function cpc_to_ecpm($views,$clicks,$cpc){
        return floatval(1000*$cpc*$clicks/$views);
    }
    
    public function cpa_to_ecpm($views,$actions,$cpa){
        return floatval(1000*$cpa*$actions/$views);
    }

    public function get_last_arg(...$args){
        return array_pop($args);
    }

    public function index_ad($id,$locations,$content,$type,$value){
        Redis::pipeline(function($pipe)use($locations,$id,$content,$type,$value){
            foreach($locations as $location){
                $pipe->sadd('idx:req:'.$location,$id);
            }
            $words=$this->tokenize($content);
            foreach($this->tokenize($content) as $word){
                $pipe->zadd('idx:'.$word,0,$id);
            }
            $rvalue=self::TO_ECPM[$type](1000,$AVERAGE_PER_1K[$type] ?: 1,$value);
            $pipe->hset('type:',$id,$type);
            $pipe->zadd('idx:ad:value:',$rvalue,$id);
            $pipe->zadd('ad:base_value:',$value,$id);
            $pipe->sadd('terms:'.$id,...$words);
        });
    }

    public function target_ads($locations,$content){
        $result=Redis::pipeline(function($pipe)use($locations,$content){
            list($matched_ads,$base_ecpm)=$this->match_location($locations);
            list($words,$targeted_ads)=$this->finish_scoring($matched_ads,$base_ecpm,$content);
            $pipe->incr('ads:served:');
            $pipe->zrevrange('idx:'.$targeted_ads,0,0); 
        });
        list($target_id,$targeted_ad)=array_slice($result,-2);
        if(empty($targeted_ad)){
            return [null,null];
        }
        $ad_id=$targeted_ad[0];
        $this->record_targeting_result($target_id,$ad_id,$words);
        return [$target_id,$ad_id];
    }

    public function match_location($locations){
        $required=array_map(function($val){return 'req:'.$val;},$locations);
        $matched_ads=$this->union($required,300,false);
        return [$matched_ads,$this->zintersect(['matched_ads'=>0,'ad:value:'=>1],30,['_execute'=>false])];
    }

    public function finish_scoring($matched,$base,$content){
        $bonus_ecpm=[];
        $words=$this->tokenize($content);
        foreach($words as $word){
            $word_bonus=$this->zintersect(['matched'=>0,'word'=>1],30,['_execute'=>false]);
            $bonus_ecpm[$word_bonus]=1;
        }
        if(!empty($bonus_ecpm)){
            $minimum=$this->zunion($bonus_ecpm,30,['aggregate'=>'MIN','_execute'=>false]);
            $maximum=$this->zunion($bonus_ecpm,30,['aggregate'=>'MAX','_execute'=>false]);
            return [$words,$this->zunion(['base'=>1,'minimum'=>5,'maximum'=>5],30,['_execute'=>false])];
        }
        return [$words,$base];
    }

    public function record_targeting_result($target_id,$ad_id,$words){
        $terms = Redis::smembers('terms:'.$ad_id);
        $matched=array_intersect($words,$terms);
        $type=Redis::hget('type:',$ad_id);
        $result=Redis::pipeline(function($pipe)use($target_id,$ad_id,$words,$matched,$type){
            if(!empty($matched)){
                $matched_key='terms:matched:'.$target_id;
                $pipe->sadd($matched_key,...$matched);
                $pipe->expire($matched_key,900);
            }
            $pipe->incr('type:'.$type.':views:');
            foreach($matched as $word){
                $pipe->zincrby('views:'.$ad_id,$word);
            }
            $pipe->zincrby('views:'.$ad_id,'');
        });
        if(!array_pop($result)%100){
            $this->update_cpms($ad_id);
        }
    }

    public function record_click($target_id,$ad_id,$action=false){
        $click_key='clicks:'.$ad_id;
        $match_key='terms:matched:'.$target_id;
        $type=Redis::hget('type:',$ad_id);
        $result=Redis::pipeline(function($pipe)use($target_id,$ad_id,$action,$type,$match_key,$matched,$click_key){
            if($type == 'cpa'){
                $pipe->expire($match_key,900);
                if($action){
                    $click_key='actions:'.$ad_id;
                }
            }
            if($action && $type=='cpa'){
                $pipe->incr('type:'.$type.':actions:');
            }else{
                $pipe->incr('type:'.$type.':clicks:');
            }
            $matched=Redis::smembers($match_key);
            array_push($matched,'');
            foreach($matched as $word){
                $pipe->zincrby($click_key,$word);
            }
        });
        $this->update_cpms($ad_id);
    }

    public function update_cpms($ad_id){
        $result1=Redis::pipeline(function($pipe)use($ad_id){
            $pipe->hget('type:',$ad_id);
            $pipe->zscore('ad:base_value:',$ad_id);
            $pipe->smembers('terms:'.$ad_id);
        });
        list($type,$base_value,$words)=$result1;
        
        $which='clicks';
        if($type=='cpa'){
            $which='actions';
        }

        $result2=Redis::pipeline(function($pipe)use($type,$which){
            $pipe->get('type:'.$type.':views:');
            $pipe->get('type:'.$type.$which);
        });
        list($type_views,$type_clicks)=$result2;
        $AVERAGE_PER_1K[$type]=floatval(1000*(int)($type_clicks ?: 1)/(int)($type_views ?: 1));
        if($type == 'cpm'){
            return;
        }
        $view_key='views:'.$ad_id;
        $click_key=$which.$ad_id;
        $to_ecpm=self::TO_ECPM[$type];
        $result3=Redis::pipeline(function($pipe)use($view_key,$click_key){
            $pipe->zscore($view_key,'');
            $pipe->zscore($click_key,'');
        });
        list($ad_views,$ad_clicks)=$result3;
        if(($ad_clicks ?: 0) < 1){
            $ad_ecpm=Redis::zscore('idx:ad:value',$ad_id);
        }else{
            $ad_ecpm=$to_ecpm($ad_views ?: 1,$ad_clicks ?: 0,$base_value);
            Redis::zadd('idx:ad:value:',$ad_ecpm,$ad_id);
        }
        foreach($words as $word){
            list($views,$clicks)=array_slice(Redis::pipeline(function($pipe)use($view_key,$click_key,$word){
                $pipe->zscore($view_key,$word);
                $pipe->zscore($click_key,$word);
            }),-2);
            if(($clicks ?: 0) < 1){
                continue;
            }
            $word_ecpm=$to_ecpm($views ?: 1,$clicks ?: 0,$base_value);
            $bonus=$word_ecpm - $ad_ecpm;
            Redis::pipeline(function($pipe)use($bonus,$ad_id){
                $pipe->zadd('idx:.$word',$bonus,$ad_id);
            });
        }
    }    


    public function addJob(Request $req){
        $job_id=$req->input('job_id');
        $required_skills=$req->input('required_skills');
        $this.add_job($job_id,$required_skills);
        $this->index_job($job_id,$required_skills);
        return ['msg'=>'添加工作成功','success'=>true];
    }

    public function add_job($job_id,$required_skills){
        Redis::sadd('job:'.$job_id,...$required_skills);
    }

    public function is_qualified($job_id,$candidate_skills){
        $temp=strval($this->uuid());
        $result=Redis::pipeline(function($pipe)use($temp,$job_id,$candidate_skills){
            $pipe->sadd($temp,...$candidate_skills);
            $pipe->expire($temp,5);
            $pipe->sdiff('job:'.$job_id,$temp);
        });
        return !array_pop($result);
    }

    public function index_job($job_id,$skills){
        Redis::pipeline(function($pipe)use($job_id,$skills){
            foreach($skills as $skill){
                $pipe->sadd('idx:skill:'.$skill,$job_id);
            }
            $pipe->zadd('idx:jobs:req',count(array_unique($skills)),$job_id);
        });
    }

    public function find_jobs($candidate_skills){
        $skills=[];
        foreach(array_unique($candidate_skills) as $skill){
            $skills['skill:'.$skill]=1;
        }
        $job_scores=$this->zunion($skills,30);
        $final_result=$this->zintersect(['job_scores'=>-1,'jobs:req'=>1],30);
        return Redis::zrangebyscore('idx:'.$final_result,0,0);
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
