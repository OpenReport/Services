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

// Allow from any origin
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
$app->map('/', function() {
    //http_response_code(200);
})->via('OPTIONS');

/**
 * Status
 *
 */
$app->get('/', function () use($app, $response)  {

    $response['message'] = 'Open Report v1.0';
    echo json_encode($response);

});

/**
 * Login user
 *
 */
$app->post('/login', function() use($app, $response){
    // get the data
    $email = $app->request()->post('email');
    $password = $app->request()->post('password');
    $authData = User::find('first', array('conditions'=>array('email=? AND password=?', $email, $password)));

    if ($authData == NULL) {
        $response['status'] = "error";
        $response['message'] = "Auth Error";
        $response['data'] = array('email'=>$email);
        echo json_encode($response);
        return;
    }
    $response['data'] = array('apiKey'=>$authData->account->api_key,'username'=>$authData->username);
    echo json_encode($response);
});


/**
 * Fetch all Reporting Tasks for apiKey
 *
 * get: /task/{apiKey}
 *
 */
//$app->get("/task/:apiKey", function($apiKey) use ($app, $response) {
//
//    // get date
//    $today = new DateTime('GMT');
//    $taskData = Task::find('all', array('conditions'=>array('api_key = ?', $apiKey)));
//    // package the data
//    $response['data'] = taskArrayMap($taskData);
//    $response['count'] = 1;
//    // send the data
//    echo json_encode($response);
//
//});

/**
 * Fetch Report Form
 *
 * get: /form/{api}/{id}
 */
$app->get('/form/:apiKey/:id', function ($apiKey, $id) use ($app, $response) {

    // get date
    $today = new DateTime('GMT');
    try {
        $formData = Form::find($id);
        // package the data
        $response['data'] = $formData->values_for(array('id','meta'));
        $response['count'] = count($formData->meta['fields']);//$response['data']->count();
    }
    catch (\ActiveRecord\RecordNotFound $e) {
        $response['message'] = 'No Records Found';
        $response['data'] = null;
        $response['count'] = 0;//$response['data']->count();
    }

    // send the data
    echo json_encode($response);


});



/**
 * Fetch all Report Forms for apiKey
 *
 * get: /form/{apiKey}
 *
 */
$app->get("/form/:apiKey", function ($apiKey) use ($app, $response) {

    // get date
    $today = new DateTime('GMT');
    try {
        $formData = Form::find('all', array('conditions'=>array('api_key = ?', $apiKey)));
        // package the data
        $response['data'] = formArrayMap($formData);
        $response['count'] = count($response['data']);
    }
    catch (\ActiveRecord\RecordNotFound $e) {
        $response['message'] = 'No Records Found';
        $response['data'] = array();;
        $response['count'] = 0;
    }

    // send the data
    echo json_encode($response);

});


/**
 * Post form data to records
 *
 *
 */
$app->post('/record/', function () use ($app, $response) {

    // get the data
    $request = json_decode($app->request()->getBody());

    // Validate calendar id
    //if($id == $request->form_id){
        // create the event
        $record = new Record();
        $record->api_key = $request->api_key;
        $record->form_id = $request->form_id;
        $record->meta = json_encode($request->meta);
        //$record->record_date = $request->????;
        //$record->user = $request->user;
        //$record->lon = $request->lon;
        //$record->lat = $request->lat;
        $record->save();
    //}
    // package the data
    $response['data'] = json_encode($request->meta);
    $response['count'] = 0;

    // send the data
    echo json_encode($response);
});



/**
 * Run the Slim application
 *
 * This method should be called last. This executes the Slim application
 * and returns the HTTP response to the HTTP client.
 */
$app->run();


/*** Data conversion utilites ***/

function formArrayMap($forms){

   return array_map(create_function('$m','return $m->values_for(array(\'id\',\'api_key\',\'title\',\'description\',\'meta\',\'date_created\'));'),$forms);

}
function taskArrayMap($tasks){

   return array_map(create_function('$m','return $m->values_for(array(\'id\',\'title\',\'description\',\'date_created\'));'),$tasks);

}
function getColumns($data){
    return array_keys($data[0]);
}
