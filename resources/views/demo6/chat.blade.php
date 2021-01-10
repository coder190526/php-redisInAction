<!DOCTYPE html>
<html lang="zh">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>群组消息系统</title>
        <script src="{{URL::asset('js/vue.js')}}"></script>
        <script src="{{URL::asset('js/axios.min.js')}}"></script>
        <style>
            h4{
                margin: 0;
            }
            p{
                margin: 5px 0;
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
            .chat_area{
                flex: 2;
            }
            .chat_list{
                flex: 1;
            }
            .long_input{
                width: 540px;
            }
            .float_right{
                float: right;
            }
            .msg_list li{
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
                <div class="chat_area">
                    <div class="input_area">
                        <p>
                            <p v-show='current_user'>当前用户为@{{current_user}}</p>
                            <p v-show='!current_user'>还未设置用户</p>
                            <input class="long_input" type="text" v-model='user' placeholder="请设置当前用户,由字母和数字构成,至少有3个字母和2个数字"/>
                            <button @click='setUser'>设置用户</button>
                        </p>
                        <p>
                            <p v-show='current_recipients.length>0'>当前群组接收人为@{{recipients_str}}</p>
                            <p v-show='current_recipients.length==0'>还未设置群组接收人</p>
                            <input class="long_input" type="text" v-model='recipients' placeholder="请设置群组消息接收人,名字由至少3个字母和2个数字组成,以逗号分隔(若有群组ID可不填)"/>
                            <button @click='setRecipients'>设置消息接收人</button>
                        </p>
                        <input type="text" v-model='message' placeholder="请输入想要发送给群组的消息"/>
                        <input type="text" v-model='chat_id' placeholder="可设置群组ID(非必填)"/>
                        <button @click='createChat'>创建或更新群组并发送消息</button>
                        <p v-show='msg'>提示 : @{{msg}}</p>
                        <div class="chat_btn">
                            <input type="text" v-model='to_chat_id' placeholder="请输入要加入或离开的群组ID"/>
                            <button @click='joinChat()'>加入群组</button>
                            <button @click='leaveChat()'>离开群组</button>
                        </div>
                    </div>
                    <div class="msg_list">
                        <button class="float_right" @click='fetchPendingMessages()'>拉取未读消息</button>
                        <h4>消息列表</h4>
                        <ul v-show='msg_list.length>0'>
                            <li v-for='item in msg_list'>
                                <span>消息ID : @{{item.c_id}}</span>
                                <span>群组ID : @{{item.id}}</span>
                                <span>群组消息 : @{{item.message}}</span>
                                <span>发送人 : @{{item.sender}}</span>
                                <span>发送时间 : @{{getFormatDate(item.ts)}}</span>
                            </li>
                        </ul>
                        <p v-show='msg_list.length==0'>暂无群组消息</p>
                    </div>
                </div>
                <div class="chat_list">
                    <h4>群组列表</h4>
                    <ul v-show='chat_list.length>0'>
                        <li v-for='c in chat_list'>
                            <span>群组ID : @{{c.id}}</span>
                            <span>群组成员 : @{{c.recipients}}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <script>
            let app = new Vue({
                el: '#app',
                data:{
                    user:'',
                    current_user:'',
                    recipients:'',
                    current_recipients:[],
                    message:'',
                    chat_id:'',
                    to_chat_id:'',
                    msg_list:[],
                    chat_list:[],
                    msg:'',
                },
                computed:{
                    recipients_str:function(){
                        let str='';
                        if(this.current_recipients.length==0){
                            return str;
                        }else{
                            this.current_recipients.forEach(r => {
                                if(!str){
                                    str += r;
                                }else{
                                    str += ','+r;
                                }
                            });
                            return str;
                        }
                    }
                },
                methods:{
                    getAllChats:function(){
                        axios.get('/chat/getAllChats')
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.chat_list=res.data.list.map(function(v){
                                    let str='';
                                    v.recipients.forEach(function(r){
                                        if(!str){
                                            str += r;
                                        }else{
                                            str += ','+r;
                                        }
                                    });
                                    return {id:v.id,recipients:str};
                                });
                                this.fetchPendingMessages();
                            }else{
                                this.msg=res.data.msg;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    setUser:function(){
                        this.user=this.user.trim();
                        if(!/^[a-zA-Z]{3,}[\d]{2,}$/.test(this.user)){
                            this.user='';
                            this.msg='请正确设置当前用户';
                        }else{
                            this.current_user=this.user;
                            this.msg='当前用户设置成功';
                        }
                    },
                    setRecipients:function(){
                        let arr=this.recipients.split(',').filter(function(r){
                            if(r && !/^[a-zA-Z]{3,}[\d]{2,}$/.test(r.trim())){
                                return true;
                            }
                        });
                        if(arr.length>0){
                            this.current_recipients=[];
                            this.msg='群组接收人名字有非法字符';
                        }else{
                            this.current_recipients=this.recipients.split(',').filter(function(r){
                                if(/^[a-zA-Z]{3,}[\d]{2,}$/.test(r.trim())){
                                    return true;
                                }
                            });
                            this.msg='群组接收人设置成功';
                        }
                    },
                    createChat:function(){
                        this.chat_id=this.chat_id.trim();
                        if(!this.current_user){
                            this.msg='请设置当前用户';
                            return
                        }
                        if((!this.chat_id || !/^\d+$/.test(this.chat_id)) && this.current_recipients.length==0){
                            this.msg='请正确设置群组接收人';
                            return
                        }
                        axios.post('/chat/createChat',{
                            sender:this.current_user,
                            recipients:this.current_recipients,
                            message:this.message,
                            chat_id:this.chat_id || null
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.getAllChats();
                                this.msg='当前创建的群组ID为'+res.data.id;
                            }else{
                                this.msg=res.data.msg;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    fetchPendingMessages:function(){
                        if(!this.current_user){
                            this.msg='未设置当前用户';
                            return
                        }
                        axios.post('/chat/fetchPendingMessages',{
                            recipient:this.current_user
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.msg_list=[];
                                let arr=[];
                                if(res.data.list.length>0){
                                    res.data.list.forEach(function(v){
                                        v.msg.forEach(function(m){
                                            m['c_id']=v.id;
                                            arr.push(m);
                                        });
                                    });
                                }
                                this.msg_list=arr;
                            }else{
                                this.msg_list=[];
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    joinChat:function(){
                        this.to_chat_id=this.to_chat_id.trim();
                        if(!this.current_user){
                            this.msg='请设置当前用户';
                            return
                        }
                        if(!/^\d+$/.test(this.to_chat_id)){
                            this.msg='请正确设置要加入或离开的群组ID';
                            return
                        }
                        if(this.to_chat_id.length>5){
                            this.msg='群组ID最好设置为5位或5位以下';
                            return
                        }
                        axios.post('/chat/joinChat',{
                            chat_id:this.to_chat_id,
                            user:this.current_user
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.msg=res.data.msg;
                            }else{
                                this.msg=res.data.msg;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    leaveChat:function(){
                        this.to_chat_id=this.to_chat_id.trim();
                        if(!this.current_user){
                            this.msg='请设置当前用户';
                            return
                        }
                        if(!/^\d+$/.test(this.to_chat_id)){
                            this.msg='请正确设置要加入或离开的群组ID';
                            return
                        }
                        if(this.to_chat_id.length>5){
                            this.msg='群组ID最好设置为5位或5位以下';
                            return
                        }
                        axios.post('/chat/leaveChat',{
                            chat_id:this.to_chat_id,
                            user:this.current_user
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.msg=res.data.msg;
                            }else{
                                this.msg=res.data.msg;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    getFormatDate:function(timestamp) {
                        let time = new Date(parseInt(timestamp)*1000);
                        let year = time.getFullYear();
                        const month = (time.getMonth() + 1).toString().padStart(2, '0');
                        const date = (time.getDate()).toString().padStart(2, '0');
                        const hours = (time.getHours()).toString().padStart(2, '0');
                        const minute = (time.getMinutes()).toString().padStart(2, '0');
                        const second = (time.getSeconds()).toString().padStart(2, '0');

                        return year + '-' + month + '-' + date + ' ' + hours + ':' + minute + ':' + second;
                    }
                },
                created:function(){
                    this.getAllChats()
                }
            });
        </script>
    </body>
</html>
