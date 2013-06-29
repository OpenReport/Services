<?php
/**
 * Open Report
 *
 * An open source application framework for Open Report
 *
 * NOTICE OF LICENSE
 * This work is licensed under the Creative Commons Attribution-ShareAlike 3.0 Unported License.
 * To view a copy of this license, visit http://creativecommons.org/licenses/by-sa/3.0/.
 *
 * @package		Open Report
 * @author		Open Report Dev Team
 * @copyright   Copyright (c) 2013, The Austin Conner Group. (http://theaustinconnergroup.info/)
 * @license		http://creativecommons.org/licenses/by-sa/3.0/
 * @link		https://sites.google.com/site/openfieldreport/
 * @since		Version 1.0
 * @filesource
 */


/**
 *
 */
class User extends ActiveRecord\Model {
  static $table_name = 'users';
  static $belongs_to = array(array('account', 'class_name'=>'Account'));

}
