<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_JSend_Exception extends Kohana_Exception {
	
	/**
	 * @var	array	Error messages
	 */
	protected static $_error_messages = array(
		JSON_ERROR_NONE            => FALSE,
		JSON_ERROR_DEPTH           => 'Maximum stack depth exceeded',
		JSON_ERROR_STATE_MISMATCH  => 'Underflow or the modes mismatch',
		JSON_ERROR_CTRL_CHAR       => 'Unexpected control character found',
		JSON_ERROR_SYNTAX          => 'Syntax error, malformed JSON',
		JSON_ERROR_UTF8            => 'Malformed UTF-8 characters, possibly incorrectly encoded',
	);
	
	/**
	 * Retrieves a string representation of JSON error messages
	 * 
	 * @param	int		$code	Usually a predefined constant, e.g. JSON_ERROR_SYNTAX
	 * @return	mixed	String message or boolean FALSE if there is no error
	 * @see		http://www.php.net/manual/en/function.json-last-error.php
	 */
	public static function error_message($code)
	{
		if (array_key_exists($code, JSend_Exception::$_error_messages))
			return JSend_Exception::$_error_messages[$code];
		
		return __('Unknown JSON error code: :code', array(':code' => $code));
	}
	
}
