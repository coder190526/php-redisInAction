<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

//视图路由
Route::get('/welcome', function () {
    return view('welcome');
});
Route::get('/', function () {
    return view('home');
});
Route::get('/shop', function () {
    return view('demo4.shop');
});
Route::get('/log', function () {
    return view('demo5.log');
});
Route::get('/count', function () {
    return view('demo5.count');
});
// Route::get('/stats', function () {
//     return view('demo5.stats');
// });
Route::get('/ipfind', function () {
    return view('demo5.ipfind');
});
Route::get('/contact', function () {
    return view('demo6.contact');
});
Route::get('/chat', function () {
    return view('demo6.chat');
});
Route::get('/docSearch', function () {
    return view('demo7.docSearch');
});
Route::get('/adtarget', function () {
    return view('demo7.adtarget');
});
Route::get('/job', function () {
    return view('demo7.job');
});
Route::redirect('/website', '/website/login');
Route::get('/website/login', function () {
    return view('demo8.websiteLogin');
});
Route::get('/website/{id}', function ($id) {
    return view('demo8.websiteHome',['id'=>$id]);
})->whereNumber('id');


//接口函数用驼峰命名,具体控制器函数用下划线分割命名
//接口路由
Route::prefix('shop')->group(function(){
    Route::get('/getInitData','ShopController@getInitData');
    Route::post('/listItem','ShopController@listItem');
    Route::get('/getAllData','ShopController@getAllData');
    Route::post('/buyItem','ShopController@buyItem');
});
Route::prefix('log')->group(function(){
    Route::post('/getRecentLogList','LogController@getRecentLogList');
    Route::post('/getCommonLogList','LogController@getCommonLogList');
    Route::post('/logRecent','LogController@logRecent');
});
Route::prefix('count')->group(function(){
    Route::post('/getCounter','CountController@getCounter');
    Route::post('/updateCounter','CountController@updateCounter');
});
Route::prefix('stats')->group(function(){
    Route::post('/getStats','CountController@getStats');
    Route::post('/updateStats','CountController@updateStats');
});
Route::prefix('ipfind')->group(function(){
    // Route::post('/ipsToRedis','IPfindController@ipsToRedis');
    Route::post('/citiesToRedis','IPfindController@citiesToRedis');
    Route::post('/findCity','IPfindController@findCity');
    Route::get('/getCityList','IPfindController@getCityList');
    Route::get('/delFile','IPfindController@delFile');
});
Route::prefix('contact')->group(function(){
    Route::post('/addUpdateContact','ContactController@addUpdateContact');
    Route::post('/removeContact','ContactController@removeContact');
    Route::post('/fetchAutocompleteList','ContactController@fetchAutocompleteList');
    Route::post('/autocompleteOnPrefix','ContactController@autocompleteOnPrefix');
    Route::post('/joinGuild','ContactController@joinGuild');
    Route::post('/leaveGuild','ContactController@leaveGuild');
});
Route::prefix('chat')->group(function(){
    Route::get('/getAllChats','ChatController@getAllChats');
    Route::post('/createChat','ChatController@createChat');
    Route::post('/fetchPendingMessages','ChatController@fetchPendingMessages');
    Route::post('/joinChat','ChatController@joinChat');
    Route::post('/leaveChat','ChatController@leaveChat');
});
Route::prefix('docSearch')->group(function(){
    Route::post('/createDoc','SearchController@createDoc');
    Route::post('/searchAndSort','SearchController@searchAndSort');
    Route::post('/searchAndZsort','SearchController@searchAndZsort');
});
Route::prefix('adtarget')->group(function(){
    Route::post('/createAd','SearchController@createAd');
    Route::post('/targetAds','SearchController@targetAds');
    Route::post('/recordClick','SearchController@recordClick');
});
Route::prefix('job')->group(function(){
    Route::post('/addJob','SearchController@addJob');
    Route::post('/findJobs','SearchController@findJobs');
});
Route::prefix('website')->group(function(){
    Route::get('/getUserList','WebsiteController@getUserList');
    Route::post('/createUser','WebsiteController@createUser');
    Route::post('/toLogin','WebsiteController@toLogin');
    Route::post('/getAllData','WebsiteController@getAllData');
    Route::post('/postMsg','WebsiteController@postMsg');
    Route::post('/followUser','WebsiteController@followUser');
    Route::post('/unfollowUser','WebsiteController@unfollowUser');
    Route::post('/delMsg','WebsiteController@delMsg');
});


