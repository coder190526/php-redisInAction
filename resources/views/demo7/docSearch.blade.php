<!DOCTYPE html>
<html lang="zh">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>文档搜索</title>
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
            .search_area{
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div id="app" class="container">
            <div class="header">
                <a href="/">返回首页</a>
            </div>
            <div class='main'>
                <div class="doc_area">
                    <div class="create_area">
                        <textarea cols="60" rows="6" type="text" v-model='content' placeholder="请输入文档内容,每个单词至少两个字母,以空格间隔"></textarea>
                        <button style="vertical-align: top" @click='createDoc(content)'>创建文档</button>
                    </div>
                    <div class="search_area">
                        <input style="width: 400px" type="text" v-model='query' placeholder="请输入查询语句,每个单词至少两个字母,以空格间隔,只能搜索英文"/>
                        <button @click='searchAndSort(query)'>查询文档</button>
                        {{-- <button @click='searchAndZsort(query)'>特殊查询</button> --}}
                        <p>查询规则:可以在单词前面加上+-符号表示包含或者排除(如 aaa+bbb-ccc 表示搜索结果有aaa或者bbb,没有ccc)</p>
                        <p v-show='msg'>@{{msg}}</p>
                    </div>
                    <h4>文档列表</h4>
                    <ul v-show='list.length>0'>
                        <li v-for='l in list'>@{{l}}</li>
                    </ul>
                </div>
            </div>
        </div>
        <script>
            let app = new Vue({
                el: '#app',
                data:{
                    content:'',
                    query:'',
                    list:[],
                    msg:''
                },
                methods:{
                    createDoc:function(content){
                        console.log(content);
                        content.trim();
                        console.log(content);
                        if(!/[a-zA-Z]{2,}/.test(content)){
                            this.content='';
                            return;
                        }
                        axios.post('/docSearch/createDoc',{
                            content:content
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.msg=res.data.msg;
                            }else{
                                this.msg=res.data.msg;
                            }
                        })
                        .catch(err=>{
                            this.msg='所输入内容与之前的文档索引内容重复，无法生成'
                            console.log(err);
                        })
                    },
                    searchAndSort:function(query){
                        query=query.trim();
                        if(!/[+-]?[a-z']{2,}/.test(query)){
                            this.query=''
                            return;
                        }
                        axios.post('/docSearch/searchAndSort',{
                            query:query
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.list=res.data.list;
                                this.msg=res.data.msg;
                            }else{
                                this.list=[];
                                this.msg=res.data.msg;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    },
                    searchAndZsort:function(query){
                        query=query.trim();
                        if(!/[+-]?[a-z']{2,}/.test(query)){
                            this.query=''
                            return;
                        }
                        axios.post('/docSearch/searchAndZsort',{
                            query:query
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.list=res.data.list;
                                this.msg=res.data.msg;
                            }else{
                                this.list=[];
                                this.msg=res.data.msg;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        })
                    }
                }
            });
        </script>
    </body>
</html>
