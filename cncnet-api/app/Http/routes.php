<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::group(['prefix' => 'ladder/{game}'], function()
{
    Route::get('/', 'LadderController@getLadderIndex');
    Route::get('/player/', 'LadderController@getLadderIndex');
    Route::get('/player/{player}', 'LadderController@getLadderPlayer');
    Route::get('/games/{gameId}', 'LadderController@getLadderGame');
});

Route::controllers
([
	'auth' => 'Auth\AuthController',
	'password' => 'Auth\PasswordController',
]);

Route::group(['prefix' => 'api/v1/auth/', 'middleware' => 'auth.basic.once'], function()
{
    Route::get('/token', 'ApiAuthController@getAuth');
});

Route::group(['prefix' => 'api/v1/'], function () 
{
    Route::get('/user/account', 'ApiUserController@getAccount');
    Route::post('/user/create', 'ApiUserController@createUser');

    // General Endpoints
    Route::get('/ping', 'ApiLadderController@pingLadder');

    // Player Endpoints
    Route::post('/player/{username}', 'ApiPlayerController@createPlayer');
    
    // Ladder Endpoints
    Route::post('/ladder/{game}', 'ApiLadderController@postLadder');
    Route::get('/ladder/{game}', 'ApiLadderController@getLadder');
    Route::get('/ladder/{game}/game/{gameId}', 'ApiLadderController@getLadderGame');
    Route::get('/ladder/{game}/player/{player}', 'ApiLadderController@getLadderPlayer');

    // Clan Endpoints
});