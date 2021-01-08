<!DOCTYPE html>
<html lang="zh">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>社交网站主页</title>
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
                border: 1px solid #000;
            }
            .inputArea{
                height: 50px;
                line-height: 50px;
                text-align: center;
            }
            .homepage{
                flex:3
            }
            .homepage .title{
                border: 1px solid #000;
            }
            .homepage .title span{
                padding: 0 10px;
            }
            .homepage li{
                padding: 0 10px;
                display: flex;
                justify-content: space-between;
            }
            .userList{
                flex:1
            }
            .userList li{
                padding: 0 10px;
                display: flex;
                justify-content: space-between;
            }
            .following{
                flex:1
            }
            .followers{
                flex:1
            }
            .active{
                background: blue;
            }
        </style>
    </head>
    <body>
        <div id="app" class="container" v-if='loading'>
            <div class="header">
                <a href="/website/login">返回登录页面</a>
            </div>
            <div class="main">
                <div class="homepage">
                    <h4>主页</h4>
                    <div class="inputArea">
                        <span>
                            <input type="text" placeholder="请输入个人动态" v-model='msg'/>
                            <button @click='postMsg(msg)'>发送动态</button>
                            <span class="tip">@{{tip}}</span>
                        </span>
                    </div>
                    <div class="title">
                        <span :class="{'active':isAll}" @click="toggleClass('all')">全部</span>
                        <span :class="{'active':!isAll}" @click="toggleClass('own')">个人</span>
                    </div>
                    <ul>
                        <li v-for="item in msgList[type]">
                            <span>动态信息 : @{{item.message}}</span>
                            <span>时间 : @{{item.posted}}</span>
                            <span>用户名 : @{{item.login}}</span>
                            <button @click='delMsg(item.id)' v-show="item.uid===uid">删除</button>
                        </li>
                    </ul>
                </div>
                <div class="userList">
                    <h4>所有用户</h4>
                    <ul>
                        <li v-for='item in userList'>
                            <span>用户名:@{{item.name}}</span>
                            <button @click='follow(item.id)' v-show="!isfollowing.includes(item.id)">关注</button>
                        </li>
                    </ul>
                </div>
                <div class="following">
                    <h4>关注(@{{following['num']}})</h4>
                    <ul>
                        <li v-for="item in following['list']">
                            <span>用户名:@{{item.name}}</span>
                            <button @click='unfollow(item.id)'>取消关注</button>
                        </li>
                    </ul>
                </div>
                <div class="followers">
                    <h4>粉丝(@{{followers['num']}})</h4>
                    <ul>
                        <li v-for="item in followers['list']">用户名:@{{item.name}}</li>
                    </ul>
                </div>
            </div>
        </div>
        <script>
            //var asd="{{$id}}";
            //console.log(asd);
            let app = new Vue({
                el: '#app',
                data:{
                    uid:'',
                    msg:'',
                    uid:'',
                    type:'all',
                    msgList:[],
                    following:{},
                    isfollowing:[],
                    followers:{},
                    userList:[],
                    tip:'',
                    isAll:true,
                    loading:false
                },
                methods:{
                    toggleClass:function(type){
                        this.isAll=!this.isAll;
                        this.type=type;
                    },
                    getAllData:function(id){
                        axios.post('/website/getAllData',{
                            id:id
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                let msglist=res.data.allData['msgList'];
                                for(type in msglist){
                                    msglist[type].forEach(function(m){
                                        m['posted']=getFormatDate(m['posted']);
                                    });
                                }
                                this.msgList=msglist;
                                this.following = res.data.allData['following'];
                                this.followers = res.data.allData['followers'];
                                this.userList = Object.keys(res.data.allData['userList']).map(function($v){
                                    return {name:$v,id:res.data.allData['userList'][$v]};
                                });
                                this.isfollowing=this.following['idList'];
                                this.isfollowing.push(this.uid);
                                this.loading = true;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        });
                    },
                    postMsg:function(msg){
                        msg=msg.trim();
                        if(!msg){
                            this.tip='动态不能为空';
                            return;
                        }
                        axios.post('/website/postMsg',{
                            id:this.uid,
                            msg:msg
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.getAllData(this.uid);
                                this.tip='发送成功';
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        });
                    },
                    follow:function(other_uid){
                        axios.post('/website/followUser',{
                            uid:this.uid,
                            other_uid:other_uid
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.getAllData(this.uid);
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        });
                    },
                    unfollow:function(other_uid){
                        axios.post('/website/unfollowUser',{
                            uid:this.uid,
                            other_uid:other_uid
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.getAllData(this.uid);
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        });
                    },
                    delMsg:function(status_id){
                        axios.post('/website/delMsg',{
                            uid:this.uid,
                            status_id:status_id
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.getAllData(this.uid);
                                this.tip='删除成功';
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        });
                    }
                },
                created:function(){
                    let uid=window.location.href.split('/').pop();
                    this.uid=uid;
                    this.getAllData(this.uid);
                },
                mounted:function(){
                    
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
