<!DOCTYPE html>
<html lang="zh">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>查找IP所属城市</title>
        <script src="{{URL::asset('js/vue.js')}}"></script>
        <script src="{{URL::asset('js/axios.min.js')}}"></script>
        <style>
            h4{
                margin: 0;
            }
            ul{
                list-style-type: none;
                margin: 0;
                padding: 0;
            }
            .main{
                display: flex;
            }
        </style>
    </head>
    <body>
        <div id="app" class="container">
            <div class="header">
                <a href="/">返回首页</a>
            </div>
            <div class='main'>
                <div class="inputArea">
                    <span>
                        <input type="text" v-model='ipFilename' placeholder="请输入GeoLite2-City-Blocks-IPv4文件路径"/>
                        <button @click='ipsToRedis(ipFilename)'>进行导入</button>
                    </span>
                    <span>
                        <input type="text" v-model='cityFilename' placeholder="请输入GeoLite2-City-Locations文件路径"/>
                        <button @click='citiesToRedis(cityFilename)'>进行导入</button>
                    </span>
                    <span>
                        <input type="text" v-model='ip' placeholder="请输入ip进行查找"/>
                        <button @click='findCity(ip)'>搜索</button>
                    </span>
                </div>
            </div>
        </div>
        <script>
            let app = new Vue({
                el: '#app',
                data:{
                    ip:'',
                    ipFilename:'',
                    cityFilename:'',
                    msg:'',
                    city:''
                },
                methods:{
                    ipsToRedis:function(filename){
                        filename=filename.trim();
                        if(!filename){
                            return;
                        }
                        axios.post('/ipfind/ipsToRedis',{
                            filename:filename
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.msg=res.data.msg;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    citiesToRedis:function(filename){
                        filename=filename.trim();
                        if(!filename){
                            return;
                        }
                        axios.post('/ipfind/citiesToRedis',{
                            filename:filename
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.msg=res.data.msg;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    findCity:function(ip){
                        ip=ip.trim();
                        if(!ip){
                            return;
                        }
                        axios.post('/ipfind/findCity',{
                            ip:ip
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.city=res.data.city;
                            }else{
                                this.msg=res.data.msg;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    }
                }
            });
        </script>
    </body>
</html>
