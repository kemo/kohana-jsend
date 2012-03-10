<?php defined('SYSPATH') OR die('Kohana bootstrap needs to be included before tests run');

/**
 * Tests the Arr lib that's shipped with kohana
 *
 * @group jsend
 *
 * @category   Tests
 */
class JSend_JSendTest extends Unittest_TestCase
{
	/**
	 * Provides test data for __construct
	 *
	 * @return array
	 */
	public function provider_construct()
	{
		return array(
			array(
				array('foo' => 'bar')
			),
		);
	}
	
	/**
	 * @group jsend.construct
	 * @test
	 * @dataProvider provider_construct
	 * @param array $data
	 */
	public function test_construct(array $data)
	{
		$jsend = new JSend($data);
		$this->assertSame($jsend->data(), $data);
		
		$jsend2 = JSend::factory($data);
		$this->assertSame($jsend2->data(), $data);
	}
	
	
	public function provider_set()
	{
		return array(
			array(
				array('foo' => 'bar'),
				NULL,
				NULL,
				array('foo' => 'bar'),
			),
			array(
				'foo',
				'bar',
				NULL,
				array('foo' => 'bar'),
			),
		);
	}
	
	/**
	 * @test
	 * @group jsend.set
	 * @dataProvider provider_set
	 * @param mixed $key
	 * @param mixed $value
	 * @param array $expected_data
	 */
	public function test_set($key, $value = NULL, $filter = NULL, $expected_data = NULL)
	{
		$jsend = new JSend;
		$jsend->set($key, $value, $filter);

		$this->assertSame($jsend->data(), $expected_data);
	}
	
	
	public function provider_get()
	{
		return array(
			array(
				array('foo' => 'bar'),
				'foo',
				'bar',
			),
			array(
				array(),
				'foo',
				NULL, // default is NULL
			),
			array(
				array('foo' => array('bar' => 'fubar')),
				'foo.bar', // path test
				'fubar',
			),
		);
	}
	
	/**
	 * @group jsend.get
	 * @test
	 * @dataProvider provider_get
	 * @param array $data
	 * @param string $key
	 * @param mixed $expected return value
	 */
	public function test_get(array $data, $key, $expected)
	{
		$jsend = new JSend($data);
		
		$this->assertSame($jsend->get($key), $expected);
	}
	
	
	public function provider_code()
	{
		return array(
			array(500, 500),
			array('500', 500),
			array(FALSE, 0),
		);
	}
	
	/**
	 * @group jsend.code
	 * @test
	 * @dataProvider provider_code
	 * @param mixed $code
	 * @param int $expected
	 */
	public function test_code($code, $expected)
	{
		$jsend = new JSend;
		$jsend->code($code);
		
		$this->assertSame($jsend->code(), $expected);
	}
	
	
	public function provider_message()
	{
		return array(
			array(
				':foo is bar', 
				array(':foo' => 'bar'), 
				'bar is bar', 
				NULL,
			),
			array(
				new Exception('Bar is bar', 500),
				NULL,
				'Exception: Bar is bar',
				500,
			),
		);
	}
	
	/**
	 * @group jsend.message
	 * @test
	 * @dataProvider provider_message
	 * @param mixed $message
	 * @param mixed $values
	 * @param string $expected_message
	 * @param int $expected_code
	 */
	public function test_message($message, $values = NULL, $expected_message, $expected_code)
	{
		$jsend = new JSend;
		
		$jsend->message($message, $values);
		
		$this->assertSame($jsend->message(), $expected_message);
		
		// Assert that the code was set (exceptions etc.)
		$this->assertSame($jsend->code(), $expected_code);
		
		// Status is set to error when JSend::message() is called!
		$this->assertSame($jsend->status(), JSend::ERROR);
	}
	
	
	public function provider_status()
	{
		return array(
			array(
				JSend::SUCCESS,
				FALSE
			),
			array(
				'invalid',
				'JSend_Exception',
			),
		);
	}
	
	/**
	 * @group jsend.status
	 * @test
	 * @dataProvider provider_status
	 * @param mixed $status
	 * @param mixed $exception_type
	 */
	public function test_status($status, $exception_type)
	{
		$jsend = new JSend;
		
		try
		{
			$jsend->status($status);
		
			$this->assertSame($status, $jsend->status());
		}
		catch (Exception $e)
		{
			if ($exception_type !== FALSE)
			{
				$this->assertTrue($e instanceof $exception_type);
			}
		}
	}
	
	/**
	 * Tests that JSend::render_into() sets appropriate response headers and body
	 * 
	 * @group jsend.render_into
	 * @test
	 */
	public function test_render_into()
	{
		$response = new Response;
		
		$data = array('foo' => 'bar');
		
		$jsend = JSend::factory($data);
		$jsend->render_into($response);
		
		$decoded = json_decode($response->body(), TRUE);
		
		$this->assertSame($decoded['data'], $data);
		$this->assertTrue($response->headers('content-type') === 'application/json');
		$this->assertTrue($response->headers('x-response-format') === 'jsend');
	}
	
}
