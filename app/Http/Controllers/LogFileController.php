<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\ChatController;

class LogFileController extends Controller
{
    public function daily_country_aggregate($line){
        if($line){
            $line = str_split($line);
            $ip=$line[0];
            $day=$line[1];
            $country=find_city_by_ip_local($ip)[2];
            $aggregates[$day][$country]+=1;
            return;
        }
        foreach($aggregates as $day=>$aggregate){
            foreach($aggregate as $key=>$val){
                Redis::zadd('daily:country:'.$day,$val,$key);
                unset($aggregates[$day]);
            }
        }
    }

    public function copy_logs_to_redis($path,$channel,$count=10,$limit=2**30,$quit_when_done=true){
        $bytes_in_redis=0;
        $waiting = [];
        ChatController::create_chat('source',array_map('strval',range($count)),'',$channel);
        $count=strval($count);
        if($handle=opendir($path)!==false){
            while(false!==($flie=readdir($handle))){
                array_push($fileArr,$file);
            }
        }
        foreach(sort($fileArr) as $logfile){
            $full_path=$path.$logfile;
            $fsize=filesize($full_path);
            while($bytes_in_redis+$fsize>$limit){
                $cleaned=$this->_clean($channel,$waiting,$count);
                if($cleaned){
                    $bytes_in_redis -= $cleaned;
                }else{
                    sleep(0.25);
                }
            }
            ChatController::send_message($channel,'source',$logfile);
            $bytes_in_redis+=$fsize;
            array_push($waiting,[$logfile=>$fsize]);
        }
        if($quit_when_done){
            ChatController::send_message($channel,'source',':done');
        }
        while(each($waiting)){
            $cleaned = $this->_clean($channel,$waiting,$count);
            if($cleaned){
                $bytes_in_redis -= $cleaned;
            }else{
                sleep(0.25);
            }
        }
    }

    public function _clean($channel,$waiting,$count){
        if(!$waiting){
            return 0;
        }
        $w0=$waiting[0][0];
        if(Redis::get($channel.$w0.':done')==$count){
            Redis::del($channel.$w0,$channel.$w0.':done');
            return array_shift($waiting)[1];
        }
        return 0;
    }

    public function process_logs_from_redis($id,$callback){
        while(1){
            $fdata=ChatController::fetch_pending_messages($id);
            foreach($fdata as $ch=>$mdata){
                foreach($mdata as $message){
                    $logfile=$message['message'];
                    if($logfile==':done'){
                        return;
                    }elseif(!$logfile){
                        continue;
                    }
                    $block_reader='readblocks';
                    if(strpos($logfile,'.gz')==strlen($logfile)-3){
                        $block_reader='readblocks_gz';
                    }
                    foreach(readlines($ch.$logfile,$block_reader) as $line){
                        $callback($line);
                    }
                    $callback(null);
                    Redis::incr($ch.$logfile.':done');
                }
            }
            if(!$fdata){
                sleep(0.1);
            }
        }
    }

    public function readlines($key,$rblocks){
        $out='';
        foreach($rblocks($key) as $block){
            $out .= $block;
            $posn=strrpos($out,'\n');
            if($posn>=0){
                foreach(explode('\n',array_slice($out,0,$posn)) as $line){
                    yield $line.'\n';
                }
                $out=array_slice($out,$posn+1);
            }
            if(!$block){
                yield $out;
                break;
            }
        }
    }

    public function readblocks($key,$blocksize=2**17){
        $lb=$blocksize;
        $pos=0;
        while($lb==$blocksize){
            $block=Redis::substr($key,$pos,$pos+$blocksize-1);
            yield $block;
            $lb=strlen($block);
            $pos += $lb;
        }
        yield '';
    }

    public function readerblocks_gz($key){
        $inp='';
        $decoder=null;
        foreach($this->readblocks($key,2**17) as $block){
            if(!$decoder){
                $inp+=$block;
                try{
                    if(implode(array_slice(str_split($inp),0,3)) != "\x1f\x8b\x08"){
                        throw new Exception('invalid gzip data');
                    }
                    $i=10;
                    $flag=ord($inp[3]);
                    if($flag & 4){
                        $i += 2 +ord($inp[$i]) + 256*ord($inp[$i+1]);
                    }
                    if($flag & 8){
                        $i=strpos($inp,'\0',$i)+1;
                    }
                    if($flag & 16){
                        $i=strpos($inp,'\0',$i)+1;
                    }
                    if($flag & 2){
                        $i += 2;
                    }
                    if($i>strlen($inp)){
                        throw new Exception('not enough data');
                    }
                }catch(Exception $e){
                    continue;
                }
            }else{
                $block=implode(array_slice(str_split($inp),$i));
                $inp=null;
                if(!$block){
                    continue;
                }
            }
            if(!$block){
                yield $decoder->flush();
                break;
            }
            yield gzuncompress($block);
        }
    }
}
