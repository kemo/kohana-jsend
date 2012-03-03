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
					
				$json->status(JSend::SUCCESS)
					->set('post', $post);
			}
			catch (ORM_Validation_Exception $e)
			{
				$json->status(JSend::FAIL)
					->set('errors', $e);
			}
			catch (Exception $e)
			{
				$json->status(JSend::ERROR)
					->message($e);
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
	