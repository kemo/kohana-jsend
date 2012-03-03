# Kohana JSend module
### Author: Kemal Delalic

See: http://labs.omniti.com/labs/jsend

[!!] Not unit-tested yet
	
### Example multiple scenario action
	
	public function action_create()
	{
		$json = JSend::factory();
		$post = new Model_Post;		
		
		if ($this->request->method() === Request::POST)
		{
			try
			{
				$post->values($this->request->post())
					->create();
					
				$json->status(JSend::SUCCESS)
					->set('post', $post->as_array());
			}
			catch (ORM_Validation_Exception $e)
			{
				$json->status(JSend::FAIL)
					->set('errors', $e->errors(''));
			}
			catch (Exception $e)
			{
				$json->status(JSend::ERROR)
					->message($e->getMessage());
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
		
		JSend::factory(array('posts' => $posts))
			->render_into($this->response);
	}
	