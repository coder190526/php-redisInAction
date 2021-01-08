<!DOCTYPE html>
<html lang="zh">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>日志列表</title>
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
            .main>div{
                flex: 1;
                border: 1px solid #000;
            }
            .main .list li{
                display: flex;
                justify-content: space-between;
            }
            .main .select{
                float: right;
            }
        </style>
    </head>
    <body>
        <div id="app" class="container" v-if='this.loading'>
            <div class="header">
                <a href="/">返回首页</a>
            </div>
            <div class='main'>
                <div class="recent_list">
                    <select class="select" v-model='r_logtype' @change='getRecentLogList'>
                        <option v-for='item in logType' :value='item.type'>@{{item.val}}</option>
                    </select>
                    <h4>近期日志列表</h4>
                    <div class="list">
                        <ul>
                            <li v-for="item in r_logList">
                                <span>名称 : @{{item.name}}</span>
                                <span>级别 : @{{item.severity}}</span>
                                <span>详情 : @{{item.log}}</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="common_list">
                    <select class="select" v-model='c_logtype' @change='getCommonLogList'>
                        <option v-for='item in logType' :value='item.type'>@{{item.val}}</option>
                    </select>
                    <h4>常见日志列表</h4>
                    <div class="list">
                        <ul>
                            <li v-for="item in c_logList">
                                <span>名称 : @{{item.name}}</span>
                                <span>级别 : @{{item.severity}}</span>
                                <span>详情 : @{{item.log}}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <script>
            var app = new Vue({
                el: '#app',
                data:{
                    r_logList:[],
                    c_logList:[],
                    logType:[
                        {type:'refresh',val:'刷新'},
                        {type:'click',val:'点击'}
                    ],
                    r_logtype:'refresh',
                    c_logtype:'refresh',
                    loading:false
                },
                methods:{
                    getRecentLogList:function(){
                        axios.post('/log/getRecentLogList',{
                            name:this.r_logtype
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.r_logList = res.data.list;
                                this.loading=true;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    getCommonLogList:function(){
                        axios.post('/log/getCommonLogList',{
                            name:this.c_logtype
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.c_logList = res.data.list;
                                this.loading=true;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    }
                },
                created:function(){
                    this.getRecentLogList();
                    this.getCommonLogList();
                }
            })
        </script>
    </body>
</html>
