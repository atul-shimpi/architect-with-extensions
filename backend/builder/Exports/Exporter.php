<?php namespace Builder\Exports;

use Sentry;
use ZipArchive;
use Builder\Themes\ThemeModel;
use Builder\Projects\PageModel;
use Builder\Projects\ProjectModel;
use Builder\Libraries\LibraryModel as Library;

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp as Adapter;

class Exporter {

	/**
	 * Symfony filesystem.
	 * 
	 * @var Symfony\Component\Filesystem\Filesystem
	 */
	private $fs;

	/**
	 * Page Model Instance.
	 * 
	 * @var Builder\Pages\PageModel
	 */
	private $page;

	/**
	 * Project Model Instance.
	 * 
	 * @var Builder\Projects\ProjectModel
	 */
	private $project;

	/**
	 * Theme Model Instance.
	 * 
	 * @var Builder\Themes\ThemeModel
	 */
	private $theme;

	/**
	 * Exports folder path.
	 * 
	 * @var string
	 */
	private $exportsPath;

	/**
	 * Bootstrap css file path.
	 * 
	 * @var string
	 */
	private $bootstrapDir;

	/**
	 * Create new Exporter isntance.
	 * 
	 * @param Application $app     
	 * @param Request     $request
	 */
	public function __construct($app, PageModel $page, ProjectModel $project, ThemeModel $theme)
	{
		$this->app = $app;
		$this->page = $page;
		$this->theme = $theme;
		$this->project = $project;
		$this->exportsPath = $app['base_dir'].'/storage/exports/';
		$this->bootstrapDir = $app['base_dir'].'/themes/default/stylesheet.css';

		$this->fs = new \Symfony\Component\Filesystem\Filesystem;

		$this->url = $this->app['base_url'];
	}

	/**
	 * Create export zip file for given page.
	 * 
	 * @param  string/int $id
	 * @return boolean/string
	 */
	public function page($id)
	{
		$page = $this->page->find($id);
		
		//fix eloquent morphTo issue with namespaced models
		if ($page) {
			$page->pageable_type = '\Builder\Projects\ProjectModel';
		}

		//basic access check
		if ( ! $page || ! $page->pageable->users()->where('users.id', $this->app['sentry']->getUser()->id)->first()) {
			return false;
		}

		if ($path = $this->createFolders($page->pageable)) {

			//create a fake project with given page so we can 
			//reuse same methods as when exporting a project
			$project = new \stdClass();
			$project->pages = array($page);

			$this->createFiles($path, $project);

			return $this->zip($path, $page->id);
		}
	}

	/**
	 * Create export zip file for given project.
	 * 
	 * @param  string/int $id
	 * @param  boolean $zip
	 * 
	 * @return boolean/string
	 */
	public function project($id, $zip = true)
	{
		$project = $this->project->find($id);

		//bail if no project found
		if ( ! $project) return false;

		//bail if project not public and doesn't belong to current user
		if ( ! $project->public) {
			if ( ! $project->users()->where('users.id', $this->app['sentry']->getUser()->id)->first()) {
				return false;
			}
		}
		
		if ($path = $this->createFolders($project)) {
			$this->createFiles($path, $project);
			
			if ($zip) {
				return $this->zip($path, $project->id);
			}

			return $path;
		}
	}

	/**
	 * Create css and html files from pages in given project.
	 * 
	 * @param  string $path  
	 * @param  Object $project
	 * 
	 * @return void
	 */
	public function createFiles($path, $project)
	{
		//create mail file
		$mail = @file_get_contents($this->app['base_dir'].'/templates/contact_me.php');
		file_put_contents($path.'mail/contact_me.php', $mail);
	
		$cssPaths = array();

		//get a list of custom elements
		$elems = scandir($this->app['base_dir'].'/elements');

		//create html, css and js files for each page in the project
		foreach ($project->pages as $key => $page) {

			//create a file with user custom css
			if ($page->css) {
				@unlink($path."css/{$page->name}-stylesheet.css");

				$css = $this->handleImages($page->css, $path, 'css');

				if (@file_put_contents($path."css/{$page->name}-stylesheet.css", $css)) {
					$cssPaths[$page->name] = "css/{$page->name}-stylesheet.css";
				}
			}

			$scripts = $this->handleLibraries($page->libraries, $path);

			//create a file with user custom js
			if ($page->js) {
				if (@file_put_contents($path."js/{$page->name}-scripts.js", $page->js)) {
					$jsPaths[$page->name] = "js/{$page->name}-scripts.js";
				}
			}

			//create html files
			//TODO: use DOM instead of regex
			if ($page->html) {

				$cssPaths = $this->handleCustomElementsCss($elems, $cssPaths, $page, $path);
							
				//bootstrap
				$page->html = preg_replace('/<link id="main-sheet".+?>/', '', $page->html);
				$page->html = preg_replace('/<link.+?font-awesome.+?>/', '', $page->html);
				
				$theme = strtolower($page->theme ? $page->theme : 'default');

				$bs = @file_get_contents($this->app['base_dir'].'/themes/'.$theme.'/stylesheet.css');
				@file_put_contents($path.'css/bootstrap.min.css', $bs);

				$page->html = preg_replace('/(<\/head>)/ms', "\n\t<link rel=\"stylesheet\" href=\"css/bootstrap.min.css\">\n$1", $page->html);

				//font-awesome
				$page->html = preg_replace('/(<\/head>)/ms', "\n\t<link rel=\"stylesheet\" href=\"//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css\">\n$1", $page->html);
				
				if (isset($cssPaths[$page->name])) {
					$page->html = preg_replace('/(<\/head>)/ms', "\n\t<link rel=\"stylesheet\" href=\"".$cssPaths[$page->name]."\">\n$1", $page->html);
				} 

				if (isset($jsPaths[$page->name])) {
					$page->html = preg_replace('/(<\/body>)/ms', "<script id=\"main-script\" src=\"".$jsPaths[$page->name]."\"></script>\n$1", $page->html);
				}

				if (isset($scripts) && $scripts) {
						
					//if we have any custom js for this page insert libraries before it
					if (isset($jsPaths[$page->name])) {
						$page->html = preg_replace('/(<script id="main-script".+?<\/script>)/ms', "$scripts$1", $page->html);

					//otherwise insert libraries before closing body tag
					} else {
						$page->html = preg_replace('/(<\/body>)/ms', "$scripts\n$1", $page->html);
					}
				}
				
				$page->html = $this->handleImages($page->html, $path, 'html');
				$page->html = $this->handleMeta($page->html, $page);
                $page->html = $this->handlePreviews($page->html);
				$page->html = preg_replace('/(<base.+?>)/ms', '', $page->html);

				file_put_contents($path."{$page->name}.html", $page->html);	
			}
		}	
	}

    private function handlePreviews($html)
    {
        return preg_replace('/<img data-name="responsive video" data-src="(.+?)".+?>/ims', '<div class="embed-responsive embed-responsive-16by9"><iframe class="embed-responsive-item" src="$1"></iframe></div>', $html);
    }

	/**
	 * Append any used custom elements css to css file.
	 * 
	 * @param  array      $elems 
	 * @param  array      $paths
	 * @param  PageModel  $page 
	 * @param  string     $path 
	 * 
	 * @return array     
	 */
	private function handleCustomElementsCss(array $elems, array $paths, $page, $path)
	{
		$elemsCss = '';

		//search for any custom elements in the page html
		foreach ($elems as $element) {
			if ($element !== '.' && $element !== '..') {

				//convert element to dash-case, remove .html and remove last s to convert to singular
				$name = rtrim(str_replace('.html', '', strtolower(preg_replace('/(?<=\\w)(?=[A-Z])/', "-$1", $element))), 's');

				if (str_contains($page->html, $name)) {

					//get css from the element
					$elemContents = file_get_contents($this->app['base_dir']."/elements/$element");
					preg_match('/<style.*?>(.+?)<\/style>/s', $elemContents, $css);

					//if custom element has any css append it to the already existing page css.
					if (isset($css[1]) && $css = trim($css[1])) {
						$elemsCss .= "\n\n$css";
					}						
				}
			}
		}

		if ($elemsCss) {
			@file_put_contents($path."css/{$page->name}-stylesheet.css", trim($elemsCss), FILE_APPEND);
			$paths[$page->name] = "css/{$page->name}-stylesheet.css";
		}

		return $paths;
	}

	/**
	 * Add meta tags to head html.
	 * 
	 * @param  string $html
	 * @param  PageModel $page
	 * 
	 * @return string
	 */
	private function handleMeta($html, $page) {
		$meta = '';

		if ($page->title) {
			$meta .= "\n\t<title>{$page->title}</title>\n";
			$meta .= "\t<meta name=\"title\" content=\"{$page->title}\">\n";
		}

		if ($page->tags) {
			$meta .= "\t<meta name=\"tags\" content=\"{$page->tags}\">\n";
		}

		if ($page->description) {
			$meta .= "\t<meta name=\"description\" content=\"{$page->description}\">\n";
		}
		
		return preg_replace('/(<meta name="viewport" content="width=device-width, initial-scale=1">)/', "$1$meta", $page->html);
	}
	
	/**
	 * Convert local relative path to absolute one.
	 * 
	 * @param  string $path
	 * @return string
	 */
	private function relativeToAbsolute($path)
	{
		if (str_contains($path, '//')) {
			return $path;
		}

		$path = str_replace('"', "", $path);
		$path = str_replace("'", "", $path);
		$path = str_replace("../", "", $path);

		return $this->app['base_dir'].'/'.$path;
	}

	/**
	 * Copy any local images used in html/css to export folder.
	 * 
	 * @param  string $string
	 * @param  string $path  
	 * @param  string $type  
	 * 
	 * @return string
	 */
	private function handleImages($string, $path, $type)
	{
		preg_match_all('/url\((.+?)\)/ms', $string, $matches1);
		preg_match_all('/<img.*?src="(.+?)".*?>/ms', $string, $matches2);

		$matches = array_merge($matches1[1], $matches2[1]);
		
		//include any local images used in css or html in the zip
		if ( ! empty($matches)) {
			foreach ($matches as $url) {

				if (str_contains($url, $this->app['base_url']) || ! str_contains($url, '//')) {
					$absolute = $this->relativeToAbsolute($url);

					try {
						@$this->fs->copy($absolute, $path.'images/'.basename($absolute), true);
					} catch (\Exception $e) {
						continue;
					}

					if ($type == 'css') {
						$string = str_replace($url, '../images/'.basename($absolute), $string);
					} else {
						$string = str_replace($url, 'images/'.basename($absolute), $string);
					}
				}
			}
		}
		
		return $string;
	}

	/**
	 * Cope any local js libraries to export folder.
	 * 
	 * @param  string  $libraries
	 * @param  string  $path
	 * 
	 * @return string  scripts html to insert before closing body tag
	 */
	private function handleLibraries($libraries, $path)
	{
		
		$scripts = "<script src=\"js/jquery.js\"></script>\n<script src=\"js/bootstrap.js\"></script>\n";
		
		@$this->fs->copy($this->app['base_dir'].'/assets/js/vendor/jquery.js', $path.'js/jquery.js', true);
		@$this->fs->copy($this->app['base_dir'].'/assets/js/vendor/bootstrap/bootstrap.min.js', $path.'js/bootstrap.js', true);

		$libraries = json_decode($libraries);

		if ($libraries) {
			foreach ($libraries as $library) {

				if (is_string($library)) {
					$library = Library::where('name', $library)->first();
				}

				if ( ! str_contains($library->path, '//')) {
					$absolute = $this->relativeToAbsolute($library->path);
					
					try {
						@$this->fs->copy($absolute, $path.'js/'.basename($absolute), true);
					} catch (\Exception $e) {
						continue;
					}

					$scripts .= '<script src="js/'.basename($library->path)."\"></script>\n";
				} else {
					$scripts .= '<script src="'.$library->path."\"></script>\n";
				}
			}
		}
		
		return $scripts;
	}

	/**
	 * Create a folder structure to hold export files.
	 *
	 * @param ProjectModel $project
	 * @return string
	 */
	private function createFolders(ProjectModel $project)	
	{
		$name = $project->id;

		if (is_dir($this->exportsPath.$name)) {
			return $this->exportsPath.$name.'/';
		}

		if (@mkdir($this->exportsPath.$name)) {
			@mkdir($this->exportsPath.$name.'/css');
			@mkdir($this->exportsPath.$name.'/js');
			@mkdir($this->exportsPath.$name.'/images');
			@mkdir($this->exportsPath.$name.'/mail');

			return $this->exportsPath.$name.'/';
		}
	}

	/**
	 * Zip files and folders at the given path.
	 * 
	 * @param  string $path
	 * @param  string $name archive name
	 * 
	 * @return boolean/string
	 */
	private function zip($path, $id)
	{
		$realPath = realpath($path);
		$absolute = $realPath.DIRECTORY_SEPARATOR;
		$ignore   = array(realpath($this->exportsPath), $realPath);

		//delete old zip if it exists
		if (is_file($absolute.$id.'.zip')) {
			unlink($absolute.$id.'.zip');
		}

		$zip = new ZipArchive();
		$zip->open($absolute.$id.'.zip', ZipArchive::CREATE);

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($realPath), \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file)
        {
        	$path = $file->getRealPath();
        	
	        if ( ! in_array($file->getRealPath(), $ignore)) {
	        	if (is_dir($file))
	            {
	                $zip->addEmptyDir(str_replace($absolute, '', $path));              
	            }
	            else if (is_file($file))
	            {
	                $zip->addFromString(str_replace($absolute, '', $path), file_get_contents($file));
	            }
	        }
        }

	    if ($zip->close()) {
	    	return $absolute.$id.'.zip';
	    }
	}

	/**
	 * Export project with given id to given ftp.
	 * 
	 * @param  string|int  $id 
	 * @param  array       $input 
	 * 
	 * @return boolean|void
	 */
	public function projectToFtp($id, array $input) {

		$filesystem = new Filesystem(new Adapter(array(
		    'host' => $input['host'],
		    'username' => $input['user'],
		    'password' => $input['password'],
		    'port' => isset($input['port']) ? $input['port'] : 21,
		    'root' => $input['root'],
		    'passive' => isset($input['passive']) ? $input['passive'] : true,
		    'ssl' => isset($input['ssl']) ? $input['ssl'] : false,
		    'timeout' => 30,
		)));

		if ($path = $this->project($id, false)) {

			//base project export folder on the server
			$path = realpath($path).DIRECTORY_SEPARATOR;

			//loop trough all files and folders recursively
			$di = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
			foreach (new \RecursiveIteratorIterator($di) as $file) {

				//get relative path only and make sure we it uses forward slashes
				$remote = str_replace(DIRECTORY_SEPARATOR, '/', str_replace($path, '', $file->getRealPath()));

				//write the file to ftp
				$filesystem->put($remote, file_get_contents($file->getRealPath()));
			}

			return true;
		}
	}

	/**
	 * Create an absolute path to a theme from given string.
	 * 
	 * @param  string $string
	 * @return string
	 */
	public function makeThemePath($string)
	{
		if ( ! str_contains('http', $string)) {
			return $this->app['base_dir']."/themes/{$string}/stylesheet.css";
		}

		$arr = explode('/', preg_replace('/https?:\/\//', '',  $string));
		$arr[0] = $this->app['base_dir'];

		return implode('/', $arr);
	}

	/**
	 * Check whether or not currently logged in user
	 * can download theme with the given name.
	 * 
	 * @param  string $name
	 * @return boolean
	 */
	public function canDownloadTheme($name)
	{
		$theme = $this->theme->where('name', $name)->first();

		if ($theme) {
			return $this->app['sentry']->getUser()->id == $theme->user_id || $theme->type == 'public';
		}
	}

	/**
	 * Compile absolute url to local image from url encoded string.
	 * 
	 * @param  string $url
	 * @return string
	 */
	public function decodeImageUrl($url)
	{
		return $this->app['base_dir'].str_replace('-', '/', urldecode($url));
	}
}