<?php namespace Builder\Home;

use Silex\Application;
use Builder\Pages\PageModel;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HomeController {

	/**
	 * Sentry instance.
	 * 
	 * @var Cartalyst\Sentry\Sentr
	 */
	private $sentry;

	/**
	 * Application container instance.
	 * 
	 * @var Silex\Application
	 */
	private $app;

	/**
	 * Symfonys finder.
	 * 
	 * @var Symfony\Component\Finder\Finder
	 */
	private $finder;

	/**
	 * Create new HomeController instance.
	 * 
	 * @param Application $app    
	 * @param Request     $request
	 */
	public function __construct(Application $app, $sentry, $finder)
	{	
		$this->app = $app;
		$this->sentry = $sentry;
		$this->finder = $finder;
	}

	/**
	 * Main app page.
	 * 
	 * @return Response
	 */
	public function index()
	{
        $response = new Response($this->app['twig']->render('main.twig.html'));
		$response->headers->clearCookie('blUser');

		@$user = $this->sentry->getUser();

		if ($user) {
			$response->headers->setCookie(new Cookie('blUser', $user, 0, '/', null, false, false));
		}

	    return $response;
	}

	/**
	 * Parse and compile all custom elements in elements folder.
	 * 
	 * @return Response
	 */
	public function customElements()
	{
		$elements = array();

		$files = $this->finder->in($this->app['base_dir'].'/elements')->files();

		foreach ($files as $file) {
			$contents = $file->getContents();

			preg_match('/<script>(.+?)<\/script>/s', $contents, $config);
			preg_match('/<style.*?>(.+?)<\/style>/s', $contents, $css);
			preg_match('/<\/style.*?>(.+?)<script>/s', $contents, $html);
			
			if ( ! isset($config[1]) || ! isset($html[1])) {
				continue;
			}

			$elements[] = array(
				'css' => isset($css[1]) ? trim($css[1]) : '',
				'html' => trim($html[1]),
				'config' => trim($config[1])
			);
		}
		
		return new Response(json_encode($elements));
	}
}