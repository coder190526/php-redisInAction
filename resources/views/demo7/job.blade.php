<!DOCTYPE html>
<html lang="zh">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>职位搜索</title>
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
                <div class="job_list">
                    <span>
                        <input type="text" v-model='job_id' placeholder="请输入工作id"/>
                        <input type="text" v-model='required_skills' placeholder="请输入工作所需技能,以逗号分隔"/>
                        <button @click='addJob(job_id,required_skills)'>增加工作</button>
                        <span v-show='msg'>消息:@{{msg}}</span>
                    </span>
                    <h4>所有工作</h4>
                    <div class="list">
                        <ul v-show='jobList.length>0'>
                            <li v-for="item in jobList">
                                <span>工作id : @{{item}}</span>
                                <button @click='removeContact(item)'>删除</button>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="findArea">
                    <span>
                        <input type="text" v-model='skills' placeholder="请输入你的技能"/>
                        <button @click='findJobs(skills)'>查找工作</button>
                        <span v-show='g_msg'>消息:@{{s_msg}}</span>
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
                    job_id:'',
                    required_skills:'',
                    skills:'',
                    msg::'',
                    s_msg::'',
                    searchList:[],
                    jobList:[]
                },
                methods:{
                    addJob:function(job_id,required_skills){
                        job_id=job_id.trim();
                        if(!/\d+/.test(job_id)){
                            this.msg='工作id只能为数字'
                            return;
                        }
                        required_skills = required_skills.split(',').filter(function(v){
                            return v.trim()!=='';
                        });
                        if(!required_skills){
                            this.msg='技能不能为空';
                            return;
                        }
                        axios.post('/job/addJob',{
                            job_id:job_id,
                            required_skills:required_skills,
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
                    findJobs:function(skills){
                        if(!prefix.trim()){
                            this.searchList=[];
                            return;
                        }
                        axios.post('/job/findJobs',{
                            skills:skills,
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.searchList=res.data.list;
                            }else{
                                this.msg=res.data.msg;
                                this.searchList=[];
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
