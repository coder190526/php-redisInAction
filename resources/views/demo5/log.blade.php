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
            .main>div{
                border: 1px solid #000;
            }
            .main .list li{
                display: flex;
                justify-content: space-between;
            }
            .main .select{
                float: right;
            }
            .recent_list{
                margin-bottom: 30px;
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
                    <h4>近期日志列表(@{{r_count}}条)</h4>
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
                    <h4>常见日志列表(@{{c_count}}条)</h4>
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
                    r_count:0,
                    c_count:0,
                    logType:[
                        {type:'ad_click',val:'广告点击'},
                        {type:'ad_target',val:'广告投放'},
                        {type:'count_click',val:'计数器点击'}
                    ],
                    r_logtype:'ad_click',
                    c_logtype:'ad_click',
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
                                this.r_count=res.data.count;
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
                                this.c_count=res.data.count;
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
