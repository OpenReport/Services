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
//$app->add(new \Slim\Middleware\SessionCookie(array('secret' => 'Z54cN9Jf8nE6hqj9V0wAuoaldIQ=')));
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
// Standardize response
$response = array('status'=>'ok', 'message'=>'', 'count'=>0, 'data'=>array());

/**
 * Authenticate all requests
 *
 */
//$app->hook('slim.before.dispatch', function () use ($app) {
//
//    $params = $app->router()->getCurrentRoute()->getParams();
//    // Provide a better validation here...
//    if ($app->request()->params('apiKey') !== "65b109869265518f7801f2ce3ba55402") {
//
//        echo var_dump($params->apiKey); die;
//
//        $app->halt(403, "Invalid or Missing Key");
//    }
//});

/**
 * Set the default content type
 *
 */
$app->hook('slim.after.router', function() use ($app) {

    $res = $app->response();
    $res['Content-Type'] = 'application/json';
    $res['X-Powered-By'] = 'Open Reports';

});



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
        $response['data'] = $formData->values_for(array('id','report_version','title','identity_name','meta'));
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
 * Fetch all Public Distributed Reporting Forms for apiKey
 *
 * get: /form/{apiKey}
 *
 */
$app->get("/forms/:apiKey/:role", function ($apiKey, $role) use ($app, $response) {

    try {
        //$formData = Form::find('all', array('conditions'=>array('api_key = ? AND is_published = 1 AND is_public = 1 AND is_deleted = 0', $apiKey)));
        $formData = Form::all(array('select'=>'forms.*','joins'=>'LEFT JOIN distributions ON(distributions.form_tag = forms.tags)',
                                'conditions'=>array('forms.api_key = ? AND (FIND_IN_SET(distributions.user_role, ?) AND forms.is_public = 1) AND forms.is_deleted = 0', $apiKey, $role)));
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
 * get: /assignments/{apiKey}/{user}
 *
 */
$app->get("/assignments/:apiKey/:user", function ($apiKey, $user) use ($app, $response) {

    try {
        $options = array();
        $options['joins'] = array('LEFT JOIN forms ON(assignments.form_id = forms.id)','LEFT JOIN users ON(assignments.user = users.email)');
        $options['select'] = 'assignments.*, forms.identity_name AS identity_name, forms.report_version AS report_version, forms.title AS title';
        $options['conditions'] = array('assignments.api_key = ? AND assignments.user = ? AND assignments.is_active = 1', $apiKey,$user);
        $options['order'] = 'date_next_report ASC';
        $recCount = Assignment::count($options);

        $data = Assignment::all($options);
        // package the data
        $response['data'] = assignmentArrayMap($data);
        $response['count'] = $recCount;
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
 * Fetch Recent Report Records (30day Window)
 *
 * GET: /report/{apiKey}/{username}[?l=limit[,offset]]
 *
 * Param: apiKey:
 * Param: username:
 *
 * Returns: List of user submited reports
 *    (records.id, forms.title, records.identity, records.record_date )
 */
$app->get("/reports/:apiKey/:user", function ($apiKey, $user) use ($app, $response) {

    $today = new DateTime('GMT');
    try {
        $options = array();
        $options['joins'] = array('JOIN forms ON(forms.id = records.form_id)');
        $options['select'] = 'forms.title AS title, records.id AS id, records.identity AS identity, records.record_date AS record_date, records.lat AS lat, records.lon AS lon';
        $options['conditions'] = array('records.api_key = ? AND records.user = ?', $apiKey, $user);

        $recCount = Record::count($options);

        if((int)$recCount > 0){
            $page = $app->request()->params('l');
            if($page != null){
                $limit = split(',',$page);
                if(count($limit)>1){
                     $options['offset'] = $limit[1];
                }
                $options['limit'] = $limit[0];
            }
        }
        $options['order'] = 'record_date desc';
        $records = Record::all($options);
        // package the data
        $response['data'] = array_map(create_function('$m','return $m->values_for(array(\'id\',\'title\',\'identity\',\'record_date\',\'lat\',\'lon\'));'),$records);
        $response['count'] = $recCount;
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
        // check for assigned forms and update
        $options = array();
        if($request->identity_name == ''){
            $options['conditions'] = array('form_id = ? AND user = ? AND DATE(NOW()) <= date_expires AND status = \'open\'', $record->form_id,$record->user);
        }
        else{
            $options['conditions'] = array('form_id = ? AND user = ? AND identity = ? AND DATE(NOW()) <= date_expires AND status = \'open\'', $record->form_id,$record->user,$request->identity);
        }
        $assignment = Assignment::first($options);
        if($assignment != null){
           $assignment->date_last_reported = $request->record_date;
           $assignment->report_count++;
           if($assignment->report_count >= $assignment->repeat_schedule){
            $assignment->status = 'closed';
            $assignment->is_active = 0;
           }
           else{
            // set next due date
            // next date = last_date plus daily/weekly/monthly by 1
            $assignment->date_next_report = $assignment->date_last_reported;
            switch($assignment->schedule){
                case 'daily';
                    $interval = 'P1D';
                    break;
                 case 'weekly';
                    $interval = 'P7D';
                    break;
                case 'monthly';
                    $interval = 'P1M';
                    break;

            }
            $assignment->date_next_report->add(new DateInterval($interval));
           }
           $assignment->save();
        }
        // add identity if it does not exists.
        $count = Identity::count(array('conditions'=>array('api_key = ? AND identity_name = ? AND identity = ?', $request->api_key, $request->identity_name, $request->identity)));
        if($request->api_key == $request->api_key && $count == 0){
            // create new Identity
            $identity = new Identity();
            $identity->api_key = $request->api_key;
            //$identity->label = $request->label;
            $identity->identity_name = $request->identity_name;
            $identity->identity = $request->identity;
            $identity->is_active = true;
            $identity->save();
        }
    //}
    // package the data
    $response['data'] = json_encode($request->meta);
    $response['count'] = 0;

    // send the data
    echo json_encode($response);
});

/**
 * Fetch all Identity records for identity_name
 *
 * GET: /identities/{apiKey}/{name}
 *
 */
$app->get("/identities/:apiKey/:name", function ($apiKey, $name) use ($app, $response) {

   try{
      $identities = Identity::find('all', array('conditions'=>array('api_key = ? AND identity_name = ? AND is_active = 1', $apiKey, $name)));
      // package the data
      $response['data'] = array_map(create_function('$m','return $m->values_for(array(\'identity\',\'description\'));'),$identities);
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
 * Run the Slim application
 *
 * This method should be called last. This executes the Slim application
 * and returns the HTTP response to the HTTP client.
 */
$app->run();


/*** Data conversion utilites ***/

function formArrayMap($forms){

   return array_map(create_function('$m','return $m->values_for(array(\'id\',\'report_version\',\'title\',\'identity_name\',\'meta\'));'),$forms);

}

/**
 * Data conversion utilites
 *
 *
 */
function assignmentArrayMap($data){

   return array_map(create_function('$m','return $m->values_for(array(\'form_id\',\'report_version\',\'title\',\'identity_name\',\'identity\',\'schedule\',\'status\',\'date_assigned\',\'date_next_report\',\'date_expires\',\'is_active\'));'),$data);

}
function getColumns($data){
    return array_keys($data[0]);
}
