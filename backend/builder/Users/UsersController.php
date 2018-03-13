<?php namespace Builder\Users;

use Cartalyst\Sentry;
use Silex\Application;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UsersController {

	/**
	 * Request instance.
	 * 
	 * @var Symfony\Component\HttpFoundation\Request
	 */
	private $request;

	/**
	 * Paramater bag instance.
	 * 
	 * @var Symfony\Component\HttpFoundation\ParameterBag
	 */
	private $input;

	private $app;

	/**
	 * Create new ProjectsController instance.
	 * 
	 * @param Application $app    
	 * @param Request     $request
	 */
	public function __construct($app, Request $request, $sentry, $validator, $creator)
	{	
		$this->app = $app;
		$this->sentry = $sentry;
		$this->request = $request;
		$this->validator = $validator;
		$this->input = $request->request;
        $this->creator = $creator;
	}

	public function index()
	{
		if ($this->sentry->getUser()->hasAccess('users.update') || $this->app['is_demo']) {
			return UserModel::all();
		}

		return $this->app['translator']->trans('noPermissionsGeneric');
	}

	public function delete($id)
	{
		$u = $this->sentry->getUser();

		if ($u->hasAccess('users.delete') && $u->id != $id) {
			return UserModel::destroy($id);
		}

		return new Response($this->app['translator']->trans('noPermissionsGeneric'), 403);
	}

	public function login()
	{
		$creds = $this->input->all();

		//do some basic validation just in case
		if ($this->validator->fails($creds, 'login')) {
			return new Response(json_encode($this->validator->errors), 400);
		}

		$error = array();

		try {
			$user = $this->sentry->authenticate($creds, true);
		}
		catch (\Cartalyst\Sentry\Users\WrongPasswordException $e) {
		    $error['*'] = $this->app['translator']->trans('wrongEmailOrPassword');
		}
		catch (\Cartalyst\Sentry\Users\UserNotFoundException $e) {
		    $error['*'] = $this->app['translator']->trans('wrongEmailOrPassword');
		}
		catch (\Cartalyst\Sentry\Throttling\UserSuspendedException $e) {
		    $error['*'] = $this->app['translator']->trans('userSuspended');
		}
		catch (\Cartalyst\Sentry\Throttling\UserBannedException $e) {
		    $error['*'] = $this->app['translator']->trans('userBanned');
		}

		if (count($error)) {
			return new Response(json_encode($error), 400);
		}

		$response = new Response($user, 200);
		$response->headers->setCookie(new Cookie('blUser', $user, 0, '/', null, false, false));

		return $response;
	}

	/**
	 * Register a new user.
	 * 
	 * @return Response
	 */
	public function store()
	{
        $registrationEnabled = isset($this->app['settings']['enable_registration']) ? $this->app['settings']['enable_registration'] : true;

        //if registration is disabled and we don't have logged in admin, bail
		if ( ! $registrationEnabled && ! ($this->sentry->getUser() && $this->sentry->getUser()->hasAccess('users.create'))) {
			return new Response($this->app['translator']->trans('registrationDisabled'), 403);
		}

        //if basic validation fails return errors
		if ($this->validator->fails($this->input->all(), 'register')) {
			return new Response(json_encode($this->validator->errors), 400);
		}

        //create a new user
		try {
			$user = $this->creator->create($this->input->all());
		} catch (\Cartalyst\Sentry\Users\UserExistsException $e) {
		    return new Response(json_encode(array('email' => $this->app['translator']->trans('emailTaken'))), 400);
		}

		$response = new Response($user, 200);

        //set cookie on response with new user data
	    $response->headers->setCookie(new Cookie('blUser', $user, 0, '/', null, false, false));

		return $response;
	}

	public function modifyPermissions($id)
	{
		if ($this->app['is_demo']) {
			return new Response('Permission modifications are disabled on demo site.', 403);
		}

		if ($this->sentry->getUser()->hasAccess('users.update')) {
			$user = UserModel::find($id);

			//only superusers can modify other superusers permissions
			if ( ! $user || ($user->isSuperUser() && ! $this->sentry->getUser()->isSuperUser())) {
				return $this->app['translator']->trans('noPermissionsGeneric');
			}

			$user->update(array('permissions' => $this->input->get('permissions')));

			return new Response('Permissions modified successfully.', 201);
		}

		return $this->app['translator']->trans('noPermissionsGeneric');
	}

	public function assignPermissionsToAll()
	{
		if ( ! $this->sentry->getUser()->hasAccess('superuser') || ! $this->input->has('permissions')) {
			return new Response($this->app['translator']->trans('noPermissionsGeneric'), 403);
		}

		UserModel::whereNull('permissions')->update(array('permissions' => $this->input->get('permissions')));

		return new Response($this->app['translator']->trans('permissionsUpdated'), 200);
	}

	public function logout()
	{
		$this->sentry->logout();

		$response = new Response();
		$response->headers->clearCookie('blUser');

		return $response;
	}
}