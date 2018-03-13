<?php namespace Builder\Validation;

use Symfony\Component\Validator\Constraints as Assert;

class UserValidator {

	public $errors = array();

	private $app;

	private $validator;

	public function __construct($app, $validator)
	{
		$this->app = $app;
		$this->validator = $validator;

		$this->minMax = array(
	        'min' => 4,
	        'max' => 50,
	        'minMessage' => $this->app['translator']->trans('4CharsMin'),
	        'maxMessage' => $this->app['translator']->trans('50CharsMax'),
	    );
	}

	/**
	 * Validation rules for user login.
	 * 
	 * @return Assert\Collection
	 */
	private function login()
	{
		return new Assert\Collection(array(
			'fields' => array(
				 'email' => array(
			    	new Assert\NotBlank(array('message' => $this->app['translator']->trans('shouldntBeBlank'))), 
			    	new Assert\Email(array('message' => $this->app['translator']->trans('notValidEmail')))
			    ),

			    'password' => array(
			    	new Assert\NotBlank(array('message' => $this->app['translator']->trans('shouldntBeBlank'))),
			    	new Assert\Length($this->minMax)
			    )
			),
			'missingFieldsMessage' => $this->app['translator']->trans('fieldCantBeEmpty'),		   
		));
	}

	/**
	 * Validation rules for user registration.
	 * 
	 * @return Assert\Collection
	 */
	private function register(array $data)
	{
		if ( ! isset($data['password'])) {
			$data['password'] = '';
		}
		
		return new Assert\Collection(array(
		    'fields' => array(
		    	'email' => array(
			    	new Assert\NotBlank(array('message' => $this->app['translator']->trans('shouldntBeBlank'))), 
			    	new Assert\Email(array('message' => $this->app['translator']->trans('notValidEmail'))), new Assert\Length($this->minMax)),

			    'password' => array(
			    	new Assert\NotBlank(array('message' => $this->app['translator']->trans('shouldntBeBlank'))), 
			    	new Assert\Length($this->minMax),
			    ),

			    'repeatPassword' => array(
			    	new Assert\NotBlank(array('message' => $this->app['translator']->trans('shouldntBeBlank'))), 
			    	new Assert\Length($this->minMax),
			    	new Assert\EqualTo(array('value' => $data['password'], 'message' => $this->app['translator']->trans('passwordsDontMatch'))),
			    ),
		    ),
		    'missingFieldsMessage' => $this->app['translator']->trans('fieldCantBeEmpty'),	
		));
	}

	public function fails(array $data, $rules)
	{
		$errors = array();
		
		foreach($this->validator->validateValue($data, $this->$rules($data)) as $error)
		{	
		    $field = str_replace(array('[', ']'), '', $error->getPropertyPath());
		    $this->errors[$field] = $error->getMessage();
		}

		return (boolean) count($this->errors);
	}
}