<!DOCTYPE html>
<html lang="zh">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>社交网站登录页面</title>
        <script src="{{URL::asset('js/vue.js')}}"></script>
        <script src="{{URL::asset('js/axios.min.js')}}"></script>
        <style>
            h4{
                margin: 0;
            }
            ul{
                list-style-type: none;
                margin: 0 15px;
                padding: 0;
            }
            .main{
                display: flex;
            }
            .main>div{
                border: 1px solid #000;
            }
            .userList{
                flex：1;
                border-right:1px solid #000;
            }
            .loginArea{
                flex:5;
                text-align: center;
            }
            .inputArea{
                display: :inline-block;
            }
            .active{
                background: blue;
            }
        </style>
    </head>
    <body>
        <div id="app" class="container" v-if='this.loading'>
            <div class="header">
                <a href="/">返回首页</a>
            </div>
            <div class="main">
                <div class="userList">
                    <ul>
                        <li v-for='item in userList'>
                            <span>用户名:@{{item.loginName}}</span><span style="margin-left:20px">id:@{{item.id}}</span>
                        </li>
                    </ul>
                </div>
                <div class="loginArea">
                    <div class="inputArea">
                        <div class="title">
                            <span :class="{'active':!isLogin}" @click='toggleClass'>注册</span>
                            <span :class="{'active':isLogin}" @click='toggleClass'>登录</span>
                        </div>
                        <div class="register" v-show='!this.isLogin'>
                            <input type="text" v-model='loginName' placeholder="请输入要注册的用户名"/>
                            <input type="text" v-model='name' placeholder="请输入自己的姓名"/>
                            <button @click='toRegister(loginName,name)'>注册</button>
                            <div class="tip">信息:@{{tip}}</div>
                        </div>
                        <div class="login" v-show='this.isLogin'>
                            <input type="text" v-model='loginName' placeholder="请输入要登录的用户名"/>
                            <button @click='toLogin(loginName)'>登录</button>
                            <div class="tip">信息:@{{tip}}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            var app = new Vue({
                el: '#app',
                data:{
                    userList: [],
                    loginName:'',
                    name:'',
                    tip:'',
                    isLogin:false,
                    loading:false
                },
                methods:{
                    toggleClass:function(){
                        this.isLogin=!this.isLogin;
                        this.tip='';
                    },
                    getUserList:function(){
                        axios.get('/website/getUserList')
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.userList = Object.keys(res.data.userList).map(function($v){
                                    return {loginName:$v,id:res.data.userList[$v]};
                                });
                                this.loading=true;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        });
                    },
                    toRegister:function(loginName,name){
                        loginName=loginName.trim();
                        if(!loginName.trim()){
                            this.tip='用户名不能为空';
                            return;
                        }
                        var pa=/[a-zA-Z][0-9a-z_]{2,}/;
                        if(loginName && !pa.test(loginName)){
                            this.tip='用户名只能以字母开头,可以加入数字和下划线,至少三位';
                            return;
                        }
                        name=name.trim();
                        if(!name.trim()){
                            this.tip='姓名不能为空';
                            return;
                        }
                        axios.post('/website/createUser',{
                            loginName:loginName,
                            name:name
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.loginName='';
                                this.name='';
                                this.tip=res.data.msg;
                                this.getUserList();
                            }else{
                                this.tip='用户名已存在';
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        });
                    },
                    toLogin:function(loginName){
                        loginName=loginName.trim();
                        if(!loginName.trim()){
                            this.tip='请输入用户名';
                            return;
                        }
                        axios.post('/website/toLogin',{
                            loginName:loginName
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                window.location.href='/website/'+res.data.id;
                            }else{
                                this.loginName='';
                                this.name='';
                                this.tip=res.data.msg;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        });
                    }
                },
                created:function(){
                    this.getUserList();
                }
            })
        </script>
    </body>
</html>
