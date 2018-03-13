<?php namespace Builder\Themes;

use Silex\Application;

class Less {

	/**
	 * Application instance.
	 * 
	 * @var Silex\Application
	 */
	private $app;

	/**
	 * Less parser instance.
	 * 
	 * @var Less_Parser
	 */
	private $parser;

	/**
	 * Create new Less instance.
	 * 
	 * @param Application $app
	 */
	public function __construct(Application $app)
	{
		$this->app = $app;
		$this->parser = new \Less_Parser(array('cache_dir'=>$this->app['base_dir'].'/backend/storage/cache'));
	}

	/**
	 * Parse given css file with given vars.
	 * 
	 * @param  string $file
	 * @param  array  $vars
	 * 
	 * @return string
	 */
	public function parse($file, array $vars, $custom)
	{
		$this->parser->parseFile($this->app['base_dir'].'/assets/less/vendor/bootstrap/'.$file.'.less', $this->app['base_url']);
		$this->parser->modifyVars($vars);
		$css = $this->parser->getCss();

		//parse custom less if any passed
		if ($custom) {
			$this->parser->reset();
			$customCss = $this->parser->parse($custom)->getCss();
		} else {
			$customCss = '';
		}

		return $css.$customCss;
	}

	public function compileBootstrapLessToArray()
	{
		$compiled = array();

		//explode file contents into less variable groups
		$split = explode('//==', $this->getBootstrapLessVarsFile());

		foreach ($split as $k => $string)
		{	
			//ignore first item
			if ($k === 0) continue;

			if (str_contains($string, 'Iconography') || str_contains($string, 'Navs')) continue;

			//match variables group name and description
			preg_match('/([a-zA-Z ]+).+?##(.*?)\n/s', $string, $description);

			//trim name/description from string
			$string = trim(preg_replace('/.+?@/s', '@', $string, 1));
					
			if ( ! isset($description[1])) continue;

			//variable name - variable value
			$vars = preg_match_all('/(@.+?): +(.+?);/s', $string, $m);
			
			$variables = array();
			foreach ($m[1] as $key => $name) {
				$variables[] = array('name' => $name, 'value' => $m[2][$key]);
			}
			
			$compiled[trim($description[1])] = array(
				'variables' => $variables,
				'description' => trim($description[2]),
			);
		}

		return $compiled;
	}

	private function getBootstrapLessVarsFile()
	{
		return file_get_contents($this->app['base_dir'].'/assets/less/vendor/bootstrap/variables.less');
	}

}