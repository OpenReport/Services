<?php
/**
 * Open Report
 *
 * An open source application framework for Open Report
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Open Software License version 3.0
 *
 * This source file is subject to the Open Software License (OSL 3.0) that is
 * bundled with this package in the files license.txt / license.rst.  It is
 * also available through the world wide web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to obtain it
 * through the world wide web, please send an email to
 * licensing@theaustinconnergroup.info so we can send you a copy immediately.
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
class Account extends ActiveRecord\Model {
  static $table_name = 'accounts';
  static $has_many = array(array('users', 'class'=>'User'));

}
