# Kohana JSend module
### Author: Kemal Delalic

See: http://labs.omniti.com/labs/jsend
	
### Example multiple scenario action
	
	public function action_create()
	{
		$json = new JSend;
		$post = new Model_Post;
		
		if ($this->request->method() === Request::POST)
		{
			try
			{
				$post->values($this->request->post())
					->create();
					
				$json->data('post', $post); // success is default anyways
			}
			catch (ORM_Validation_Exception $e)
			{
				// Errors are extracted
				// from ORM_Validation_Exception objects
				$json->status(JSend::FAIL)
					->data('errors', $e); 
			}
			catch (Exception $e)
			{
				// Exception message will be extracted
				// and status will be set to JSend::ERROR
				// because only error responses support messages
				$json->message($e); 
			}
		}
		
		$json->render_into($this->response);
	}

### Example data retrieval action

	public function action_posts()
	{
		$posts = ORM::factory('post')
			->find_all()
			->as_array('name','text');
		
		// Success is the default JSend status
		JSend::factory(array('posts' => $posts))
			->render_into($this->response);
	}
	