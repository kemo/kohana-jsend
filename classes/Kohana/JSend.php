<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Strict JSend (JSON) response format
 *
 * @author	Kemal Delalic <github.com/kemo>
 * @see		http://labs.omniti.com/labs/jsend
 */ 
class Kohana_JSend {

	const DEFAULT_ASSOC = FALSE;    // Default $assoc is FALSE
	const DEFAULT_DEPTH = 512;      // Default $depth is 512
	const DEFAULT_OPTIONS = 0;      // Default $options is 0

	const ERROR     = 'error';      // Execution errors; exceptions, etc.
	const FAIL      = 'fail';       // App errors: validation etc.
	const SUCCESS   = 'success';    // Default status: everything seems to be OK

	const VERSION   = '1.3.0';      // Release version
	
	/**
	 * @var array   Valid status types
	 */
	protected static $_status_types = array(
		JSend::ERROR,
		JSend::FAIL,
		JSend::SUCCESS,
	);
	
	/**
	 * Checks if an error occured during the last json_encode() / json_decode() operation
	 * 
	 * @param   mixed   $passthrough var to return (in case exception isn't thrown)
	 * @return  mixed   $passthrough
	 * @throws  JSend_Exception
	 */
	public static function check_json_errors($passthrough = NULL)
	{
		$error = json_last_error();
		
		if ($error !== JSON_ERROR_NONE and $message = JSend_Exception::error_message($error))
			throw new JSend_Exception($message, NULL, $error);
			
		return $passthrough;
	}
	
	/**
	 * Decodes a value to JSON 
	 * 
	 * This is a proxy method to json_decode() with proper exception handling
	 *
	 * @param   string  $json       The JSON string being decoded.
	 * @param   bool    $assoc      Convert the result into associative array?
	 * @param   int     $depth      User specified recursion depth.
	 * @param   int     $options    Bitmask of JSON decode options.
	 * @return  mixed   Decoded value
	 * @throws  JSend_Exception
	 */
	public static function decode($json, $assoc = NULL, $depth = NULL, $options = NULL)
	{
		if ($assoc === NULL)
		{
			$assoc = JSend::DEFAULT_ASSOC;
		}
		
		if ($depth === NULL)
		{
			$depth = JSend::DEFAULT_DEPTH;
		}
		
		if ($options === NULL)
		{
			$options = JSend::DEFAULT_OPTIONS;
		}
		
		$result = json_decode($json, $assoc, $depth, $options);
		
		return JSend::check_json_errors($result);
	}
	
	/**
	 * Encodes a value to JSON
	 *
	 * This is a proxy method to json_encode() with proper exception handling
	 * 
	 * @param   mixed   $value
	 * @param   int     $options bitmask
	 * @return  string  JSON encoded
	 * @throws  JSend_Exception
	 */
	public static function encode($value, $options = NULL)
	{
		if ($options === NULL)
		{
			$options = JSend::DEFAULT_OPTIONS;
		}
		
		// Encode the value to JSON and check for errors
		$result = json_encode($value, $options);
		
		return JSend::check_json_errors($result);
	}
	
	/**
	 * Factory method
	 *
	 * @param   array    $data   Initial data to set
	 */
	public static function factory(array $data = NULL)
	{
		return new JSend($data);
	}
	
	/**
	 * Default method for rendering objects into their values equivalent
	 *
	 * Override on app level for additional classes:
	 *
	 *     public static function object_values($object)
	 *     {
	 *         if ($object instanceof SomeClass)
	 *             return $object->some_method();
	 *     
	 *         return parent::object_values($object);
	 *     }
	 * 
	 * @param   object  $object
	 * @return  mixed   Value to encode (e.g. array, string, int)
	 */
	public static function object_values($object)
	{
		/**
		 * JsonSerializable
		 * @see http://php.net/JsonSerializable
		 */
		if ($object instanceof JsonSerializable)
			return $object->jsonSerialize();
			
		if ($object instanceof ORM or $object instanceof AutoModeler)
			return $object->as_array();
			
		if ($object instanceof ORM_Validation_Exception)
			return $object->errors('');
			
		if ($object instanceof Database_Result)
		{
			$items = array();
			
			foreach ($object as $result)
			{
				$items[] = JSend::object_values($result);
			}
			
			return $items;
		}
		
		if ($object instanceof ArrayObject)
			return $object->getArrayCopy();
			
		if (method_exists($object, '__toString'))
			return (string) $object;
		
		// If no matches, return the whole object
		return $object;
	}
	
	/**
	 * @var int     Status code
	 */
	protected $_code;

	/**
	 * @var array   Return data
	 */
	protected $_data = array();
	
	/**
	 * @var int     Status message
	 */
	protected $_message;
	
	/**
	 * @var string  Status (success, fail, error)
	 */
	protected $_status = JSend::SUCCESS;
	
	/**
	 * @var array   Array of key => callback render-time filters
	 */
	protected $_filters = array();
	
	/**
	 * @param   array   initial array of data
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
		
		throw new JSend_Exception('Nonexisting data key requested: :key',
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
			
			JSend_Exception::handler($e);

			return (string) ob_get_clean();
		}
	}
	
	/**
	 * Binds a param by reference
	 * 
	 * @chainable
	 * @param   string  $key
	 * @param   mixed   $value to reference
	 * @return  object  $this
	 */
	public function bind($key, & $value)
	{
		$this->_data[$key] =& $value;
		
		return $this;
	}

	/**
	 * Data filter getter / setter
	 * 
	 * @param   string  $key (returns the whole filters array if NULL)
	 * @param   mixed   $filter (set to FALSE to remove)
	 * @return  $this   (on set)
	 * @return  mixed   filter value
	 */
	public function filter($key = NULL, $filter = NULL)
	{
		if (is_array($key))
		{
			$this->_filters = $key;
			
			return $this;
		}
		
		if ($key === NULL)
			return $this->_filters;
			
		if ($filter === NULL)
			return Arr::get($this->_filters, $key);
		
		if ($filter === FALSE)
		{
			unset($this->_filters[$key]);
		}
		else
		{
			$this->_filters[$key] = $filter;
		}
		
		return $this;
	}
	
	/**
	 * Data getter (Arr::path() enabled)
	 * 
	 * @param   string  $path to get, e.g. 'post.name'
	 * @param   mixed   $default value
	 * @return  mixed   Path value or $default (if path doesn't exist)
	 */
	public function get($path, $default = NULL)
	{
		return Arr::path($this->_data, $path, $default);
	}
	
	/**
	 * Sets a key => value pair or the whole data array
	 * 
	 * Example with callback (for setting objects):
	 * 
	 *     $jsend->set('foo', new Model_Bar, 'Foo::bar');
	 *
	 * @chainable
	 * @param   mixed   $key string or array of key => value pairs
	 * @param   mixed   $value to set (in case $key is int or string)
	 * @param   mixed   $filter to use (when setting objects)
	 * @return  JSend   $this
	 */
	public function set($key, $value = NULL, $filter = NULL)
	{
		/**
		 * If array passed, replace the whole data
		 */
		if (is_array($key))
		{
			$this->_data = array();
			
			foreach ($key as $_key => $_value)
			{
				$this->set($_key, $_value);
			}
			
			return $this;
		}
		
		$this->_data[$key] = $value;
		$this->_filters[$key] = $filter;
		
		return $this;
	}
	
	/**
	 * Returns a callable function for extracting object data
	 * 
	 * @return  callable
	 */
	public function default_callback()
	{
		return 'JSend::object_values';
	}
	
	/**
	 * Response code getter / setter
	 *
	 * @chainable
	 * @param   int     $code
	 * @return  mixed   $code on get / $this on set
	 */
	public function code($code = NULL)
	{
		if ($code === NULL)
			return $this->_code;
		
		$this->_code = (int) $code;
		
		return $this;
	}
	
	/**
	 * Data getter (whole data array) / proxy to set()
	 *
	 * @chainable
	 * @param   mixed   $key string or array of key => value pairs
	 * @param   mixed   $value to set (in case $key is int or string)
	 * @param   mixed   $filter to use for setting objects
	 * @return  mixed   $this on set, complete data array if $key is NULL
	 */
	public function data($key = NULL, $value = NULL, $filter = NULL)
	{
		// If key is empty, use as getter
		if ($key === NULL)
			return $this->_data;
			
		return $this->set($key, $value, $filter);
	}
	
	/**
	 * Response message getter / setter
	 * 
	 * [!!] This will set status to JSend::ERROR
	 * 
	 * @chainable
	 * @param   mixed   $message string or Exception object
	 * @param   array   $values to use for translation
	 * @return  mixed   $message on get
	 * @return  JSend   $this on set
	 */
	public function message($message = NULL, array $values = NULL)
	{
		if ($message === NULL)
			return $this->_message;
		
		if ($message instanceof Exception)
		{
			// If the code hasn't been set, use Exception code
			if ($this->_code === NULL and $code = $message->getCode())
			{
				$this->code($code);
			}
			
			$this->_message = __(':class: :message', array(
				':class' 	=> get_class($message),
				':message' 	=> $message->getMessage(),
			));
		}
		else
		{
			$this->_message = __($message, $values);
		}
		
		/**
		 * Set the status to error 
		 * (response message is used *only* for error responses)
		 */
		$this->_status = JSend::ERROR;
		
		return $this;
	}
	
	/**
	 * Response status getter / setter
	 *
	 * @chainable
	 * @param   string  $status
	 * @return  mixed   $status on get
	 * @return  JSend   $this on set
	 */
	public function status($status = NULL)
	{
		if ($status === NULL)
			return $this->_status;
		
		if ( ! in_array($status, JSend::$_status_types, TRUE))
		{
			throw new JSend_Exception('Status must be one of these: :statuses',
				array(':statuses' => implode(', ', JSend::$_status_types)));
		}
		
		$this->_status = $status;
		
		return $this;
	}
	
	/**
	 * Renders the current object into JSend (JSON) string
	 * 
	 * @param   int     $encode_options json_encode() options bitmask
	 * @return  string  JSON representation of current object
	 * @see     http://php.net/json_encode#refsect1-function.json-encode-parameters
	 */
	public function render($encode_options = NULL)
	{
		$data = array();
		
		foreach ($this->_data as $key => $value)
		{
			$filter = Arr::get($this->_filters, $key);
			
			$data[$key] = $this->run_filter($value, $filter);
		}
		
		$result = array(
			'status' => $this->_status,
			'data'   => $data,
		);
		
		/**
		 * Error response must contain status & message
		 * while code & data are optional
		 */
		if ($this->_status === JSend::ERROR)
		{
			$result['message'] = $this->_message;
			
			if ($this->_code !== NULL)
			{
				$result['code'] = $this->_code;
			}
			
			if (empty($result['data']))
			{
				unset($result['data']);
			}
		}
		
		try
		{
			$response = JSend::encode($result, $encode_options);
		}
		catch (JSend_Exception $e)
		{
			// If encoding failed, create a new JSend error object based on exception
			return JSend::factory()
				->message($e)
				->render();
		}
		
		return $response;
	}
	
	/**
	 * Sets the required HTTP Response headers and body.
	 *
	 * [!!] This is the last method you call because 
	 *     *Response body is casted to string the moment it's set*
	 *
	 * Example action:
	 *
	 * JSend::factory()
	 *     ->data('posts', $posts)
	 *     ->status(JSend::SUCCESS)
	 *     ->render_into($this->response);
	 *
	 * @param   Response    $response
	 * @param   int         $encode_options for json_encode()
	 * @return  void
	 */
	public function render_into(Response $response, $encode_options = NULL)
	{
		$response->body($this->render($encode_options))
			->headers('content-type','application/json')
			->headers('x-response-format','jsend'); 
	}
	
	/**
	 * Runs the passed filter on a value
	 * 
	 * @param    mixed $value
	 * @param    mixed $filter
	 * @return   mixed
	 */
	public function run_filter($value, $filter)
	{
		/**
		 * If filter is set to FALSE, object won't be filtered at all
		 */
		if ($filter !== FALSE)
		{
			if (is_object($value))
			{
				if ($filter === NULL)
				{
					$filter = $this->default_callback();
				}
				
				$value = call_user_func($filter, $value);
			}
			elseif ($filter !== NULL)
			{
				// If a filter has been passed for other values..
				$value = call_user_func($filter, $value);
			}
		}

		return $value;
	}
	
}
