<!DOCTYPE html>
<html lang="zh">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>查找ID所属城市</title>
        <script src="{{URL::asset('js/vue.js')}}"></script>
        <script src="{{URL::asset('js/axios.min.js')}}"></script>
        <style>
            h4,p{
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
            .main>div{
                flex: 1;
                border: 1px solid #000;
            }
            .inputArea input{
                width: 280px;
            }
            .title{
                margin-bottom: 20px;
            }
            .list li,.search{
                display: flex;
                justify-content: space-between;
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
                    <p>
                        <input type="text" v-model='cityFilename' placeholder="请输入China-City-Locations文件路径"/>
                        <button @click='citiesToRedis(cityFilename)'>进行导入</button>
                        <button @click='delFile()'>删除导入文件</button>
                    </p>
                    <p>
                        <input type="text" v-model='id' placeholder="请输入城市id进行查找"/>
                        <button @click='findCity(id)'>搜索</button>
                    </p>
                    <p v-show='path'>文件路径为@{{path}}</p>
                    <p v-show='msg'>@{{msg}}</p>
                    <p class="search" v-show='Object.keys(city).length>0'>
                        <span>id : @{{city.id}}</span>
                        <span>城市名称 : @{{city.city_name}}</span>
                        <span>省份代码 : @{{city.province_code}}</span>
                        <span>省份名称 : @{{city.province_name}}</span></p>
                </div>
                <div class="list">
                    <div class="title">
                        <span style="font-weight: bold;">城市列表 </span><span> (暂时只显示二十条数据)<span>
                    </div>
                    <ul>
                        <li v-for='c in list'>
                            <span>id : @{{c.info.id}}</span>
                            <span>城市名称 : @{{c.info && c.info.city_name}}</span>
                            <span>省份代码 : @{{c.info && c.info.province_code}}</span>
                            <span>省份名称 : @{{c.info && c.info.province_name}}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <script>
            let app = new Vue({
                el: '#app',
                data:{
                    id:'',
                    cityFilename:'',
                    msg:'',
                    city:{},
                    list:[],
                    path:''
                },
                methods:{
                    citiesToRedis:function(filename){
                        filename=filename.trim();
                        if(!filename){
                            this.msg='请输入文件路径'
                            return;
                        }
                        axios.post('/ipfind/citiesToRedis',{
                            filename:filename
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.msg='导入成功';
                                this.cityFilename='';
                                this.getCityList();
                            }else{
                                this.msg=res.data.msg;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    findCity:function(id){
                        id=id.trim();
                        if(!/^\d{7,}$/.test(id)){
                            this.msg='请输入纯数字,至少7位';
                            this.city={};
                            return;
                        }
                        axios.post('/ipfind/findCity',{
                            id:id
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.city=res.data.city;
                            }else{
                                this.msg=res.data.msg;
                                this.city={};
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    getCityList:function(){
                        axios.get('/ipfind/getCityList')
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.list=res.data.list;
                            }else{
                                this.msg=res.data.msg;
                                this.path=res.data.path;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    delFile:function(){
                        axios.get('/ipfind/delFile')
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.msg=res.data.msg;
                                this.path=res.data.path;
                                this.cityFilename='';
                                this.list=[];
                            }else{
                                this.msg=res.data.msg;
                                this.path=res.data.path;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    }
                },
                created:function(){
                    this.getCityList();
                }
            });
        </script>
    </body>
</html>
