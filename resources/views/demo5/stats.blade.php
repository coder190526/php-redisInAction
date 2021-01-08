<!DOCTYPE html>
<html lang="zh">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>统计数据</title>
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
            .count_list select{
                float: right;
                width: 60px;
            }
            .count_list .list li{
                display: flex;
                justify-content: space-between;
                padding: 0 20px;
            }
            .click_area{
                text-align: center;
            }
            .click_area .btn{
                margin: 50px;
                padding: 20px;
            }
        </style>
    </head>
    <body>
        <div id="app" class="container" v-if='this.loading'>
            <div class="header">
                <a href="/">返回首页</a>
            </div>
            <div class='main'>
                <div class="count_list">
                    <select class="select" v-model='type' @change='getStats(prec,type)'>
                        <option v-for='item in events' :value='item.type'>@{{item.val}}</option>
                    </select>
                    <select class="select" v-model='prec' @change='getStats(type,prec)'>
                        <option v-for='item in precision' :value='item'>@{{item}} s</option>
                    </select>
                    <h4>计数器列表</h4>
                    <div class="list">
                        <ul>
                            <li v-for="item in counterList">
                                <span>时间 : @{{item.time}}</span>
                                <span>次数 : @{{item.count}}</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="click_area">
                    <button class="btn" @click="updateCounter('click')">点击计数</button>
                </div>
            </div>
        </div>
        <script>
            let app = new Vue({
                el: '#app',
                data:{
                    statsList:[],
                    precision:[1,5,60,300,3600,18000,86400],
                    prec:1,
                    events:[
                        {type:'click',val:'点击'}
                    ],
                    type:'click',
                    loading:false
                },
                methods:{
                    getStats:function(context,type){
                        axios.post('/stats/getStats',{
                            context:context,
                            type:type
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.counterList = Object.keys(res.data.list).map(function($v){
                                    return {time:getFormatDate($v),count:res.data.list[$v]};
                                });
                                this.loading=true;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    updateStats:function(name){
                        axios.post('/stats/updateStats',{
                            name:name
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.getStats(name,this.prec);
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    }
                },
                created:function(){
                    this.getStats(1,'click');
                }
            });
            let getFormatDate=function(timestamp) {
                let time = new Date(parseInt(timestamp)*1000);
                let year = time.getFullYear();
                const month = (time.getMonth() + 1).toString().padStart(2, '0');
                const date = (time.getDate()).toString().padStart(2, '0');
                const hours = (time.getHours()).toString().padStart(2, '0');
                const minute = (time.getMinutes()).toString().padStart(2, '0');
                const second = (time.getSeconds()).toString().padStart(2, '0');

                return year + '-' + month + '-' + date + ' ' + hours + ':' + minute + ':' + second;
            };
        </script>
    </body>
</html>
