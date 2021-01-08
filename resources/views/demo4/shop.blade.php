<!DOCTYPE html>
<html lang="zh">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>商品买卖市场</title>
        <script src="{{URL::asset('js/vue.js')}}"></script>
        <script src="{{URL::asset('js/axios.min.js')}}"></script>
        <style>
            h4{
                margin: 0;
            }
            .container>div{
                border: 1px solid #000;
                margin-bottom: 10px;
            }
            .goods{
                margin-right: 10px;
            }
            .goods>button{
                margin-left: 5px;
            }
            .goods input{
                width: 100px;
            }
        </style>
    </head>
    <body>
        <div id="app" class="container" v-if='this.loading'>
            <div class="header">
                <a href="/">返回首页</a>
            </div>
            <div class="inventory">
                <h4 v-if='this.userData.length>0'>背包</h4>
                <table>
                    <tr v-for='item in userData'>
                        <td>用户名:@{{item.info.name}}</td>
                        <td>金钱:@{{item.info.funds}}</td>
                        <td class="goods" v-for='val in item.inventory'>
                            <span>物品名:@{{val.name}}
                                <input type="text" placeholder="请输入价格" v-model='val.price' oninput="value=value.replace(/[^\d]|^0/g,'')">
                                <button v-on:click='listItem(val.name,item.info.id,val.price)'>售出</button>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="market">
                <h4>市场</h4>
                <table v-if='this.marketData.length>0'>
                    <tr v-for='item in marketData'>
                        <td>物品名称:@{{item.name}}</td>
                        <td>价格:@{{item.price}}</td>
                        <td class="goods">
                            <select v-model='buyerid'>
                                <option v-for='user in userData' :value='user.info.id'>@{{user.info.name}}</option>
                            </select>
                            <button v-on:click='buy(buyerid,item.itemid,item.sellerid,item.price)'>购买</button>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <script>
            var app = new Vue({
                el: '#app',
                data:{
                    userData: [],
                    marketData: [],
                    buyerid:'',
                    loading:false
                },
                methods:{
                    getInitData:function(){
                        axios.get('/shop/getInitData')
                        .then(res=>{
                            res.data.forEach(item => {
                                item.inventory = item.inventory.map(val =>({
                                    name:val,
                                    price:''
                                    })
                                );
                            });
                            this.userData = res.data;
                            this.loading=true;
                        })
                        .catch(err=>{
                            console.log(err);
                        });
                    },
                    listItem:function(itemid,sellerid,price){
                        if(price==''){
                            return
                        }
                        axios.post('/shop/listItem',{
                            itemid:itemid,
                            sellerid:sellerid,
                            price:price
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.getAllData();
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        });
                    },
                    getAllData:function(){
                        axios.get('/shop/getAllData')
                        .then(res=>{
                            if(res.data&&res.data.success){
                                res.data.user.forEach(item => {
                                    item.inventory = item.inventory.map(val =>({
                                        name:val,
                                        price:''
                                    })
                                    );
                                });
                            this.userData = res.data.user;
                            this.marketData = Object.keys(res.data.market).map(item =>{
                                    let idArr = item.split('.');
                                    return{
                                        name:item,
                                        itemid:idArr[0],
                                        sellerid:idArr[1],
                                        price:res.data.market[item]
                                    }
                                });
                            this.buyerid=this.userData[0].info.id;
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        });
                    },
                    buy:function(buyerid,itemid,sellerid,price){
                        axios.post('/shop/buyItem',{
                            buyerid:buyerid,
                            itemid:itemid,
                            sellerid:sellerid,
                            price:price
                        })
                        .then(res=>{
                            if(res.data&&res.data.success){
                                this.getAllData();
                            }
                        })
                        .catch(err=>{
                            console.log(err);
                        });
                    }
                },
                created:function(){
                    this.getInitData();
                }
            })
        </script>
    </body>
</html>
