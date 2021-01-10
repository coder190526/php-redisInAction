<!DOCTYPE html>
<html lang="zh">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>广告定向</title>
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
            .select{
                float: right;
            }
            .click_block{
                width: 200px;
                height: 100px;
                background: #999;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
            }
            .msg{
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div id="app" class="container">
            <div class="header">
                <a href="/">返回首页</a>
            </div>
            <div class='main'>
                <div class="ad_create">
                    <select class="select" v-model='city' @change='targetAds(city,content)'>
                        <option v-for='item in citys' :value='item.val'>@{{item.text}}</option>
                    </select>
                    <select class="select" v-model='type' @change='targetAds(city,content)'>
                        <option v-for='item in types' :value='item.val'>@{{item.text}}</option>
                    </select>
                    <h4>广告生成</h4>
                    <div class="inputArea">
                        <input type="text" v-model='content' placeholder="请输入广告相关内容"/>
                        <input type="text" v-model='value' placeholder="请输入广告价格"/>
                        <button @click='createAd(city,content,type,value)'>生成广告</button>
                        <button @click='targetAds(city,content)'>投放广告</button>
                    </div>
                    <p>广告索引规则:只能根据两位字母以上的单词进行索引生成</p>
                    <p v-show='msg' class='msg'>提示:@{{msg}}</p>
                </div>
                <div class="ad_area">
                    <button v-show="showType==1" style="height: 50px" @click='recordClick'>广告点击按钮</button>
                    <div v-show="showType==2" class="click_block" @click='recordClick'><span>广告点击区域</span></div>
                    <a v-show="showType==3" href="javascript:void(0)" @click='recordClick'>广告链接</a>
                </div>
            </div>
        </div>
        <script>
            let app = new Vue({
                el: '#app',
                data:{
                    city:'beijing',
                    citys:[
                        {text:'北京',val:'beijing'},
                        {text:'上海',val:'shanghai'},
                        {text:'广州',val:'guangzhou'},
                        {text:'深圳',val:'shenzhen'}
                    ],
                    content:'',
                    types:[
                        {text:'点击类型',val:'cpc'},
                        {text:'执行类型',val:'cpa'},
                        {text:'浏览类型',val:'cpm'}
                    ],
                    type:'cpc',
                    showType:'',
                    value:0.4,
                    msg:'',
                    ids:[]
                },
                methods:{
                    createAd:function(locations,content,type,value){
                        if(!/[a-zA-Z]{2,}/.test(content)){
                            this.msg='请输入至少两位连续字母';
                            return;
                        }
                        if(!/^([1-9]+)|(\d+\.[1-9]+)$/.test(value)){
                            this.msg='只能输入非负数';
                            return;
                        }
                        this.msg='';
                        axios.post('/adtarget/createAd',{
                            locations:[locations],
                            content:content,
                            type:type,
                            value:value
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.msg='广告生成成功';
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    targetAds:function(locations,content){
                        if(!/[a-zA-Z]{2,}/.test(content)){
                            this.msg='请输入至少两位连续字母';
                            return;
                        }
                        this.msg='';
                        axios.post('/adtarget/targetAds',{
                            locations:[locations],
                            content:content
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                if(res.data.ids && !res.data.ids['ad_id']){
                                    this.msg='暂未相关广告可以投放';
                                }else{
                                    this.ids=res.data.ids;
                                    this.msg='广告定向投放成功';
                                    this.showType=Math.ceil(Math.random()*3);
                                    this.logRecent('ad_target','广告投放id:'+this.ids['target_id']+'---广告id:'+this.ids['ad_id']);
                                }
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    recordClick:function(){
                        axios.post('/adtarget/recordClick',{
                            target_id:this.ids['target_id'],
                            ad_id:this.ids['ad_id']
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.msg='点击广告成功';
                                this.logRecent('ad_click','广告投放id:'+this.ids['target_id']+'---广告id:'+this.ids['ad_id']);
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    logRecent:function(name,message){
                        axios.post('/log/logRecent',{
                            name:name,
                            message:message
                        })
                        .then(res=>{
                            
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
