<?php
/**
 * Open Report
 *
 * Copyright 2013, The Austin Conner Group
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 *
 */

// Allow from any origin. see CORS
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}
// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

}

require $_SERVER['DOCUMENT_ROOT'].'system/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();
$app->add(new \Slim\Middleware\SessionCookie(array('secret' => 'Z54cN9Jf8nE6hqj9V0wAuoaldIQ=')));
require $_SERVER['DOCUMENT_ROOT'].'system/ActiveRecord.php';
ActiveRecord\Config::initialize(function($cfg) {
    $cfg->set_model_directory($_SERVER['DOCUMENT_ROOT'].'models');
    $cfg->set_connections(array(
        'development' => 'mysql://root:acg100199@localhost/meta_forms'
    ));
});


$app = new \Slim\Slim();
\Slim\Route::setDefaultConditions(array(
    'apiKey' => '[a-zA-Z0-9]{32}'
));

/**
 * Set the default content type
 *
 */
$app->hook('slim.after.router', function() use ($app) {

    $res = $app->response();
    $res['Content-Type'] = 'application/json';
    $res['X-Powered-By'] = 'Open Reports';

});

// Standardize response
$response = array('status'=>'ok', 'message'=>'', 'count'=>0, 'data'=>array());


// return HTTP 200 for HTTP OPTIONS requests
$app->map('/upload/:apiKey', function() {
    //http_response_code(200);
})->via('OPTIONS');

/**
 * Status
 *
 * GET:/media/
 *
 */
$app->get('/', function () use($app, $response)  {

    $response['message'] = 'Open Report v1.0';
    echo json_encode($response);

});

$app->post('/upload/:apiKey', function () use($app, $response)  {
    // get the data
    $request = json_decode($app->request()->getBody());

    if (!isset($_FILES[$request->upload])) {
        $response['message'] = "No file specify (".$request->upload.")";
        echo json_encode($response);
        return;
    }


});

/**
 * Run the Slim application
 *
 * This method should be called last. This executes the Slim application
 * and returns the HTTP response to the HTTP client.
 */
$app->run();