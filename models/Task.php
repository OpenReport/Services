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
 * @package		Open Report
 * @author		Open Report Dev Team
 * @copyright   Copyright (c) 2013, The Austin Conner Group.
 * @license		http://www.apache.org/licenses/LICENSE-2.0
 * @link		https://sites.google.com/site/openfieldreport/
 * @since		Version 1.0
 *
 */



/**
 * Report Task Model
 */
class Task extends ActiveRecord\Model {
  static $table_name = 'task';

  public function get_meta(){
    return json_decode($this->read_attribute('meta'), true);
  }


}
