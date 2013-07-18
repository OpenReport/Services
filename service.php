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
// Set Default Time Zone for All Records
date_default_timezone_set('GMT');

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
$app->map('/record/', function() {
    //http_response_code(200);
})->via('OPTIONS');

/**
 * Status
 *
 */
$app->get('/', function () use($app, $response)  {

    $response['message'] = 'OpenReport v1.0';
    echo json_encode($response);

});

/**
 * Login user
 *
 */
$app->post('/login', function() use($app, $response){
    $today = new DateTime('GMT');
    // get the data
    $email = $app->request()->post('email');
    $password = $app->request()->post('password');
    $authData = User::find('first', array('conditions'=>array('email=? AND password=? AND is_active = 1', $email, $password)));

    if ($authData == NULL) {
        $response['status'] = "error";
        $response['message'] = "Auth Error";
        $response['data'] = array('email'=>$email);
        echo json_encode($response);
        return;
    }
    $authData->date_last_accessed = $today;
    $authData->save();
    $acctInfo = $authData->account;
    $response['data'] = array('acctName'=>$acctInfo->name,'apiKey'=>$acctInfo->api_key,'username'=>$authData->username,'user_id'=>$authData->id,'roles'=>$authData->roles);
    echo json_encode($response);
});




/**
 * Fetch a Single Report Form
 *
 * get: /form/{api}/{id}
 */
$app->get('/form/:apiKey/:id', function ($apiKey, $id) use ($app, $response) {

    try {
        $formData = Form::find($id);
        // package the data
        $response['data'] = $formData->values_for(array('id','report_version','title','identity','meta'));
        $response['count'] = 1; //count($formData->meta['fields']); // return the number of form fields
    }
    catch (\ActiveRecord\RecordNotFound $e) {
        $response['message'] = 'No Records Found';
        $response['data'] = null;
        $response['count'] = 0;
    }

    // send the data
    echo json_encode($response);


});



/**
 * Fetch all Public and Distributed Reporting Forms for apiKey
 *
 * get: /form/{apiKey}
 *
 */
$app->get("/forms/:apiKey/:role", function ($apiKey, $role) use ($app, $response) {

    try {
        //$formData = Form::find('all', array('conditions'=>array('api_key = ? AND is_published = 1 AND is_public = 1 AND is_deleted = 0', $apiKey)));
        $formData = Form::all(array('select'=>'forms.*','joins'=>'LEFT JOIN distributions ON(distributions.form_tag = forms.tags)',
                                'conditions'=>array('forms.api_key = ? AND (FIND_IN_SET(distributions.user_role, ?) OR forms.is_public = 1) AND forms.is_deleted = 0', $apiKey, $role)));
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
 * Fetch all reporting assignmets records for userId
 *
 * get: /assignments/{apiKey}/{roles}
 *
 */
$app->get("/assignments/:apiKey/:userId", function ($apiKey, $userId) use ($app, $response) {

    $data = Form::all(array('joins'=>'LEFT JOIN assignments ON(assignments.form_id = forms.id)', 'conditions'=>array('forms.api_key = ?  AND assignments.user_id = ? AND forms.is_published = 1 AND forms.is_deleted = 0', $apiKey, $userId)));
    //var_dump($data);
    // package the data
    $response['data'] = formArrayMap($data);
    $response['count'] = count($data);
    // send the data
    echo json_encode($response);

});



/**
 * Fetch Recent Report Records (30day Window)
 *
 * GET: /report/{apiKey}/{username}
 *
 * Param: apiKey:
 * Param: username:
 *
 * Returns: List of user submited reports
 *    (records.id, forms.title, records.identity, records.record_date )
 */
$app->get("/reports/:apiKey/:user", function ($apiKey, $user) use ($app, $response) {

    $today = new DateTime('GMT');

    $join = array('JOIN forms ON(forms.id = records.form_id)');
    $select = 'forms.title AS title, records.id AS id, records.identity AS identity, records.record_date AS record_date, records.lat AS lat, records.lon AS lon';
    $conditions = array('records.api_key = ? AND records.user = ?', $apiKey, $user);
    $records = Record::all(array('joins' => $join, 'select'=>$select, 'conditions' =>$conditions));

    // package the data
    $data = array_map(create_function('$m','return $m->values_for(array(\'id\',\'title\',\'identity\',\'record_date\',\'lat\',\'lon\'));'),$records);

    //array_map(create_function('$m','return $m->values_for(array(\'id\',\'api_key\',\'title\',\'description\',\'meta\',\'date_modified\',\'report_version\'));'),$forms);

    $response['data'] = $data;
    $response['count'] = count($data);
    // send the data
    echo json_encode($response);

});

/**
 * Fetch Single Report Record
 *
 * get: /record/{apiKey}/{id}
 *
 */
$app->get("/record/:apiKey/:id", function ($apiKey, $id) use ($app, $response) {

    $record = Record::find($id);
    $headers = Report::last(array('conditions' => array('api_key=? AND form_id=? AND version=?', $apiKey, $record->form_id, $record->report_version)));
    // package the data
    $data = $record->values_for(array('id', 'form_id', 'report_version','meta', 'record_date','user', 'lat', 'lon', 'identity'));
    $response['data'] = array('title'=>$headers->title ,'headers'=>$headers->meta,  'record'=>$data);
    $response['count'] = 1;
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

    // get GMT Date
    $today = new DateTime('GMT');
    //$record_date = new DateTime($request->record_date);
    // Validate id
    //if($id == $request->form_id){
        // create the event
        $record = new Record();
        $record->api_key = $request->api_key;
        $record->form_id = $request->form_id;
        $record->report_version = $request->report_version;
        $record->identity = $request->identity;
        $record->meta = json_encode($request->meta);
        $record->user = $request->user;
        $record->record_date = $request->record_date;
        $record->record_time_offset = $request->record_time_offset;
        $record->lon = $request->lon;
        $record->lat = $request->lat;
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

   return array_map(create_function('$m','return $m->values_for(array(\'id\',\'report_version\',\'api_key\',\'title\',\'identity\',\'meta\'));'),$forms);

}

/**
 * Data conversion utilites
 *
 *
 */
function assignmentArrayMap($data){

   return array_map(create_function('$m','return $m->values_for(array(\'id\',\'user_id\',\'form_id\',\'date_assigned\',\'is_active\'));'),$data);

}
function getColumns($data){
    return array_keys($data[0]);
}
