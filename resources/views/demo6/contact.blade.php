<!DOCTYPE html>
<html lang="zh">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>自动补全联系人</title>
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
        </style>
    </head>
    <body>
        <div id="app" class="container">
            <div class="header">
                <a href="/">返回首页</a>
            </div>
            <div class='main'>
                <div class="contact_list">
                    <span>
                        <span>你的姓名@{{user}}</span>
                        <input type="text" v-model='prefix' @keyup='fetchAutocompleteList(prefix)' placeholder="请输入联系人"/>
                        <button @click='addUpdateContact(prefix)'>确认</button>
                        <span v-show='msg'>消息:@{{msg}}</span>
                    </span>
                    <h4>最近联系人</h4>
                    <div class="list">
                        <ul v-show='contactList.length>0'>
                            <li v-for="item in contactList">
                                <span>联系人 : @{{item}}</span>
                                <button @click='removeContact(item)'>删除</button>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="guild_list">
                    <select class="select" v-model='guild' @change='autocompleteOnPrefix(memberName)'>
                        <option v-for='item in guildArr' :value='item'>当前公会 : @{{item}}</option>
                    </select>
                    <span>
                        <input type="text" v-model='memberName' @keyup='autocompleteOnPrefix(memberName)' placeholder="请输入成员姓名"/>
                        <button @click='joinGuild(memberName)'>加入公会</button>
                        <span v-show='g_msg'>消息:@{{g_msg}}</span>
                    </span>
                    <h4>公会成员列表</h4>
                    <div class="list">
                        <ul v-show='guildList.length>0'>
                            <li v-for="item in guildList">
                                <span>成员姓名 : @{{item}}</span>
                                <button @click='leaveGuild(item)'>退出公会</button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <script>
            let app = new Vue({
                el: '#app',
                data:{
                    contactList:[],
                    guildList:[],
                    user:'Jack',
                    prefix:'',
                    guild:'php',
                    guildArr:['php','laravel','python'],
                    memberName:'',
                    msg:'',
                    g_msg:''
                },
                methods:{
                    fetchAutocompleteList:function(prefix){
                        if(!prefix.trim()){
                            this.contactList=[];
                            return;
                        }
                        axios.post('/contact/fetchAutocompleteList',{
                            user:this.user,
                            prefix:prefix
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.contactList=res.data.list;
                            }else{
                                this.msg=res.data.msg;
                                this.contactList=[];
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    addUpdateContact:function(contact){
                        if(!contact){
                            return;
                        }
                        axios.post('/contact/addUpdateContact',{
                            user:this.user,
                            contact:contact
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.msg=res.data.msg;
                                this.fetchAutocompleteList(contact);
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    removeContact:function(contact){
                        if(!contact){
                            return;
                        }
                        axios.post('/contact/removeContact',{
                            user:this.user,
                            contact:contact
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.msg=res.data.msg;
                                this.fetchAutocompleteList(contact);
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    autocompleteOnPrefix:function(prefix){
                        if(!prefix.trim()){
                            this.guildList=[];
                            return;
                        }
                        if(!/[a-zA-Z]+/.test(prefix)){
                            prefix='';
                        }
                        axios.post('/contact/autocompleteOnPrefix',{
                            guild:this.guild,
                            prefix:prefix
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.guildList=res.data.list;
                            }else{
                                this.g_msg=res.data.msg;
                                this.guildList=[];
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    joinGuild:function(user){
                        if(!/[a-zA-Z]+/.test(user)){
                            this.g_msg='成员名字要为纯英文';
                            return;
                        }
                        axios.post('/contact/joinGuild',{
                            guild:this.guild,
                            user:user
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.g_msg=res.data.msg;
                                this.autocompleteOnPrefix(user);
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    leaveGuild:function(user){
                        if(!user){
                            return;
                        }
                        axios.post('/contact/leaveGuild',{
                            guild:this.guild,
                            user:user
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.g_msg=res.data.msg;
                                this.autocompleteOnPrefix(user);
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                }
            });
        </script>
    </body>
</html>
