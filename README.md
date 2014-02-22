# Kohana JSend module (easy JSON API)
### Author: Kemal Delalic

See: http://labs.omniti.com/labs/jsend
	
### Example API controller
	
	public function before()
	{
		parent::before();

		$this->json = new JSend;
	}
	
	public function action_create()
	{
		if ($this->request->method() === Request::POST)
		{	
			try
			{
				$post = ORM::factory('post')
					->values($this->request->post(), array('title','message'))
					->create();
					
				$this->json->data('post', $post); // success is default anyways
			}
			catch (ORM_Validation_Exception $e)
			{
				// Errors are extracted
				// from ORM_Validation_Exception objects
				$this->json->status(JSend::FAIL)
					->data('errors', $e); 
			}
			catch (Exception $e)
			{
				// Exception message will be extracted
				// and status will be set to JSend::ERROR
				// because only error responses support messages
				$this->json->message($e); 
			}
		}
		else
		{
			$this->json->message('Not a POST request?');
		}
	}

	public function action_all()
	{
		$this->json->set('posts', ORM::factory('post')->find_all());
	}

	public function action_read()
	{
		$post = ORM::factory('post', $id = $this->router->param('id'));

		if ( ! $post->loaded())
			return $this->json->message('Post :id not found!', array('id' => $id));

		$this->json->data('post', $post);
	}

	public function after()
	{
		$this->json->render_into($this->response);

		return parent::after();
	}

### Example jQuery response handling

	$.post('/posts', {from: 1337}, function(jsend) {
		if (jsend.status === 'success') {
			$.each(jsend.data.posts, function(key, post) {
				$('#posts').append('<a href="' + post.url + '">' + post.title + '</a>')
			})
		}
		else if (jsend.status === 'fail') {
			$.each(jsend.data.errors, function(field, error) {
				$('#form').find('#' + field).append('<span class="error">' + error + '</span>')
			})
		}
		else {
			$('#posts').addClass('error').text('Internal error: ' + post.message)
		}
	});
	
This can also be overriden on more 'global' level, by overriding or adding jQuery methods.