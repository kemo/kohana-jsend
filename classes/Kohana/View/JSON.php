<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Strict JSend response formatting
 *
 * @author	Kemal Delalic <github.com/kemo>
 * @see		http://labs.omniti.com/labs/jsend
 */ 
class Kohana_View_JSON {

	// Status codes
	const ERROR 	= 'error';		// Execution errors; exceptions, etc.
	const FAIL		= 'fail';		// App errors: validation etc.
	const SUCCESS 	= 'success';	// Default status: everything seems to be OK

	// Release version
	const VERSION = '1.0.2';
	
	/**
	 * @var	array	Valid status types
	 */
	protected static $_status_types = array(
		View_JSON::ERROR,
		View_JSON::FAIL,
		View_JSON::SUCCESS,
	);
	
	/**
	 * @var	array	Error messages
	 */
	protected static $_error_messages = array(
		JSON_ERROR_DEPTH			=> 'Maximum stack depth exceeded',
		JSON_ERROR_STATE_MISMATCH	=> 'Underflow or the modes mismatch',
		JSON_ERROR_CTRL_CHAR		=> 'Unexpected control character found',
		JSON_ERROR_SYNTAX			=> 'Syntax error, malformed JSON',
		JSON_ERROR_UTF8				=> 'Malformed UTF-8 characters, possibly incorrectly encoded',
		JSON_ERROR_NONE				=> FALSE,
	);
	
	/**
	 * String representation of JSON error messages
	 * 
	 * @param	int		$code	Usually a predefined constant, e.g. JSON_ERROR_SYNTAX
	 * @return	mixed	String message or boolean FALSE if there is no error
	 * @see		http://www.php.net/manual/en/function.json-last-error.php
	 */
	public static function error_message($code)
	{
		if (isset(View_JSON::$_error_messages[$code]))
			return View_JSON::$_error_messages[$code];
		
		return __('Unknown JSON error code: :code', array(':code' => $code));
	}
	
	/**
	 * Factory method
	 *
	 * @param	array	$data	Initial data to set
	 */
	public static function factory(array $data = NULL)
	{
		return new View_JSON($data);
	}
	
	/**
	 * @var	int		Status code
	 */
	protected $_code;

	/**
	 * @var	array	Return data
	 */
	protected $_data = array();
	
	/**
	 * @var	int		Status message
	 */
	protected $_message;
	
	/**
	 * @var	string	Status (success, error)
	 */
	protected $_status = View_JSON::SUCCESS;
	
	/**
	 * @param	array	initial array of data
	 */
	public function __construct(array $data = NULL)
	{
		if ($data !== NULL)
		{
			$this->set($data);
		}
	}
	
	/**
	 * Magic getter method
	 */
	public function __get($key)
	{
		if (array_key_exists($key, $this->_data))
			return $this->_data[$key];
		
		throw new Kohana_Exception('Nonexisting key requested: :key', array(
			':key' => $key
		));
	}
	
	/**
	 * Magic setter method
	 */
	public function __set($key, $value)
	{
		return $this->set($key, $value);
	}
	
	/**
	 * More magic: what happens when echoed or casted to string?
	 */
	public function __toString()
	{
		try
		{
			return $this->render();
		}
		catch (Exception $e)
		{
			ob_start();
			
			Kohana_Exception::handler($e);

			return (string) ob_get_clean();
		}
	}
	
	/**
	 * Binds a param by reference
	 * 
	 * @param	string	key
	 * @param	mixed	var
	 * @return	object	$this
	 */
	public function bind($key, & $value)
	{
		$this->_data[$key] =& $value;
		
		return $this;
	}
	
	/**
	 * Data getter
	 * 
	 * @param	string	$key
	 * @param	mixed	$default value
	 * @return	mixed	Keys' value or $default if key doesn't exist
	 */
	public function get($key, $default = NULL)
	{
		return array_key_exists($key, $this->_data) ? $this->_data[$key] : $default;
	}
	
	/**
	 * Sets a key => value pair or the whole data array
	 * 
	 * @chainable
	 * @param	mixed	$key	string or array of key => value pairs
	 * @param	mixed	$value	to set (in case $key is string)
	 * @return	object	$this
	 */
	public function set($key, $value = NULL)
	{
		if (is_array($key))
		{
			$this->_data = $key;
			
			return $this;
		}
		
		$this->_data[$key] = $value;
		
		return $this;
	}
	
	/**
	 * Response code getter / setter
	 *
	 * @param	int		$code
	 * @return	mixed	$code on get / $this on set
	 */
	public function code($code = NULL)
	{
		if ($code === NULL)
			return $this->_code;
		
		$this->_code = $code;
		
		return $this;
	}
	
	/**
	 * Response message getter / setter
	 * 
	 * @param	string	$message
	 * @param	array	$values 	to use for translation
	 * @return	mixed	$message 	on get // $this on set
	 */
	public function message($message = NULL, array $values = NULL)
	{
		if ($message === NULL)
			return $this->_message;
		
		$this->_message = __($message, $values);
		
		return $this;
	}
	
	/**
	 * Response status getter / setter
	 *
	 * @param	string	$status
	 * @return	mixed	$status on get // $this on set
	 */
	public function status($status = NULL)
	{
		if ($status === NULL)
			return $this->_status;
		
		if ( ! in_array($status, View_JSON::$_status_types, TRUE))
			throw new Kohana_Exception('Status must be valid!');
		
		$this->_status = $status;
		
		return $this;
	}
	
	/**
	 * Renders the current object into JSend format
	 * 
	 * @param	int		$options	json_encode options bitmask
	 * @return	string	JSON representation of current object
	 * @see		http://php.net/json_encode#refsect1-function.json-encode-parameters
	 */
	public function render($options = 0)
	{
		// Clean up the data array
		$this->_data = array_filter($this->_data);
		
		$response = json_encode(array(
			'code' 		=> $this->_code,
			'data' 		=> $this->_data,
			'message' 	=> $this->_message,
			'status' 	=> $this->_status,
		), $options);
		
		$code = json_last_error();
		
		if ($message = View_JSON::error_message($code))
		{
			$this->code(500)
				->message('JSON error: :error', array(':error' => $message))
				->status(View_JSON::ERROR);
		}
		
		return $response;
	}
	
}
