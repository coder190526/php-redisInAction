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
                    <div>
                        <input type="text" v-model='job_id' placeholder="请输入工作id"/>
                        <input type="text" v-model='required_skills' placeholder="请输入工作所需技能,以逗号分隔"/>
                        <button @click='addJob(job_id,required_skills)'>增加工作</button>
                        <span v-show='msg'>消息:@{{msg}}</span>
                    </div>
                </div>
                <div class="findArea">
                    <span>
                        <input type="text" v-model='skills' style='width:300px' placeholder="请输入要查找工作的所需技能"/>
                        <button @click='findJobs(skills)'>查找工作</button>
                        <span v-show='s_msg'>消息:@{{s_msg}}</span>
                    </span>
                    <h4>符合的工作列表</h4>
                    <div class="list">
                        <ul v-show='searchList.length>0'>
                            <li v-for="item in searchList">
                                <span>工作id : @{{item}}</span>
                                {{-- <button @click='isQualified(item,myskill)'>匹配工作</button> --}}
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
                    msg:'',
                    s_msg:'',
                    searchList:[]
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
                        if(required_skills.length == 0){
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
                        skills = skills.split(',').filter(function(v){
                            return v.trim()!=='';
                        });
                        if(!skills){
                            this.s_msg='技能不能为空';
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
                                this.s_msg=res.data.msg;
                                this.searchList=[];
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    isQualified:function(job_id,candidate_skills){
                        candidate_skills = candidate_skills.split(',').filter(function(v){
                            return v.trim()!=='';
                        });
                        if(!candidate_skills){
                            this.s_msg='你的技能不能为空';
                            return;
                        }
                        axios.post('/job/isQualified',{
                            job_id:job_id,
                            candidate_skills:candidate_skills,
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.s_msg='你满足了该工作的所有技能要求';
                            }else{
                                this.s_msg='你还需要';
                                res.data.skill.forEach(v => {
                                    this.s_msg+=',';
                                });
                                this.s_msg=this.s_msg.slice(0,-1);
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
