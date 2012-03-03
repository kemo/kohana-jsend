<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Strict JSend (JSON) response format
 *
 * @author	Kemal Delalic <github.com/kemo>
 * @see		http://labs.omniti.com/labs/jsend
 */ 
class Kohana_JSend {

	// Status codes
	const ERROR		= 'error';		// Execution errors; exceptions, etc.
	const FAIL		= 'fail';		// App errors: validation etc.
	const SUCCESS	= 'success';	// Default status: everything seems to be OK

	// Release version
	const VERSION = '1.0.5';
	
	/**
	 * @var	array	Valid status types
	 */
	protected static $_status_types = array(
		JSend::ERROR,
		JSend::FAIL,
		JSend::SUCCESS,
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
		if (isset(JSend::$_error_messages[$code]))
			return JSend::$_error_messages[$code];
		
		return __('Unknown JSON error code: :code', array(':code' => $code));
	}
	
	/**
	 * Factory method
	 *
	 * @param	array	$data	Initial data to set
	 */
	public static function factory(array $data = NULL)
	{
		return new JSend($data);
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
	 * @var	string	Status (success, fail, error)
	 */
	protected $_status = JSend::SUCCESS;
	
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
		
		throw new Kohana_Exception('Nonexisting key requested: :key',
			array(':key' => $key));
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
	 * Data getter (Arr::path() enabled)
	 * 
	 * @param	string	$path to get, e.g. 'post.name'
	 * @param	mixed	$default value
	 * @return	mixed	Path value or $default (if path doesn't exist)
	 */
	public function get($path, $default = NULL)
	{
		return Arr::path($this->_data, $path, $default);
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
		
		$this->_code = (int) $code;
		
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
		
		if ( ! in_array($status, JSend::$_status_types, TRUE))
		{
			throw new Kohana_Exception('Status must be one of these: :statuses!',
				array(':statuses' => implode(', ', JSend::$_status_types)));
		}
		
		$this->_status = $status;
		
		return $this;
	}
	
	/**
	 * Renders the current object into JSend (JSON) string
	 * 
	 * @see		http://php.net/json_encode#refsect1-function.json-encode-parameters
	 * @param	int		$encode_options	json_encode() options bitmask
	 * @return	string	JSON representation of current object
	 */
	public function render($encode_options = NULL)
	{
		if ($encode_options = NULL)
		{
			// Default json_encode options setting is 0
			$encode_options = 0;
		}
		
		$data = array(
			'status'	=> $this->_status,
			'data'		=> $this->_data,
		);
		
		/**
		 * Error response must contain status & message
		 * while code & data are optional
		 */
		if ($this->_status === JSend::ERROR)
		{
			$data['message'] = $this->_message;
			
			if ($this->_code !== NULL)
			{
				$data['code'] = $this->_code;
			}
			
			if (empty($data['data']))
			{
				unset($data['data']);
			}
		}
		
		// Encode the response to JSON and check for errors
		$response = json_encode($data, $encode_options);
		
		$code = json_last_error();
		
		if ($message = JSend::error_message($code))
		{
			// If encoding failed, create a new JSend error object
			return JSend::factory()
				->status(JSend::ERROR)
				->message('JSON error: :error', array(':error' => $message))
				->code(500)
				->render();
		}
		
		return $response;
	}
	
	/**
	 * Sets the required HTTP Response headers and body.
	 * [!!] This is the last method you call because 
	 * 		*Response body is casted to string the moment it's set*
	 *
	 * Example action:
	 *
	 * 	JSend::factory()
	 * 		->set('posts', $posts)
	 * 		->status(JSend::SUCCESS)
	 *		->render_into($this->response);
	 *
	 * @param	Response	$response
	 * @param	int			$encode_options for json_encode()
	 * @return	void
	 */
	public function render_into(Response $response, $encode_options = NULL)
	{
		$response->body($this->render($encode_options))
			->headers('content-type','application/json')
			->headers('x-response-format','jsend'); // custom header for format recognition
	}
	
}
