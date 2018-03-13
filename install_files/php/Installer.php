<?php

class Installer {

    private $bootStrapped = false;
    private $app = false;
    private $seeder = false;
    private $schema = false;

    /**
	 * PHP Extensions and their expected state
	 * (enabled, disabled) in order for this 
	 * app to work properly.
	 * 
	 * @var array
	 */
	private $extensions = array(
		array('name' => 'fileinfo', 'type' => 'extension', 'expected' => true),
		array('name' => 'mbstring', 'type' => 'extension', 'expected' => true),
		array('name' => 'pdo', 'type' => 'extension', 'expected' => true),
		array('name' => 'pdo_mysql', 'type' => 'extension', 'expected' => true),
		array('name' => 'gd', 'type' => 'extension', 'expected' => true),
		array('name' => 'Mcrypt', 'type' => 'extension', 'expected' => true),
		array('name' => 'mysql_real_escape_string', 'type' => 'extension', 'expected' => false),
		array('name' => 'curl', 'type' => 'extension', 'expected' => true),
		array('name' => 'putenv', 'type' => 'function', 'expected' => true),
		array('name' => 'getenv', 'type' => 'function', 'expected' => true),
        array('name' => 'curl', 'type' => 'extension', 'expected' => true),
	);

	/**
	 * Directories that need to be writable.
	 * 
	 * @var array
	 */
    private $dirs = array('/backend', '/assets/images/projects', '/assets/images/uploads', '/storage',
        '/storage/exports', '/backend/storage/cache', '/backend/storage/logs', '/backend/config', '/themes'
    );

	/**
	 * Holds the compatability check results.
	 * 
	 * @var array
	 */
	private $compatResults = array('problem' => false);

    public function __construct()
    {
        if (strpos($_SERVER['REQUEST_URI'], 'htaccess-test') > -1) {
            echo 'success'; exit;
        }

        $post = json_decode(file_get_contents('php://input'), true);
        $data = isset($post['data']) ? $post['data'] : array();
        
        if ($post && array_key_exists('handler', $post)) {
            try {
                $this->{$post['handler']}($data);
            } catch (Exception $e) {
                echo json_encode(array('status' => 'error', 'message'=> $e->getMessage())); exit;
            }
        }
    }

	/**
	 * Check for any issues with the server.
	 * 
	 * @return JSON
	 */
	public function checkForIssues()
	{
		$this->compatResults['extensions'] = $this->checkExtensions();
		$this->compatResults['folders']    = $this->checkFolders();
		$this->compatResults['phpVersion'] = $this->checkPhpVersion();

		return json_encode($this->compatResults);
	}

	/**
	 * Check if we've got required php version.
	 * 
	 * @return integer
	 */
	public function checkPhpVersion()
	{
		return version_compare(PHP_VERSION, '5.4.0');
	}

	/**
	 * Check if required folders are writable.
	 * 
	 * @return array
	 */
	public function checkFolders()
	{
		$checked = array();

		foreach ($this->dirs as $dir)
		{
            $path = BASE_PATH.$dir;

		 	$writable = is_writable($path);

		 	$checked[] = array('path' => realpath($path), 'writable' => $writable);

		 	if ( ! $this->compatResults['problem']) {
		 		$this->compatResults['problem'] = $writable ? false : true;
		 	}		 	
		}
		
		return $checked;
	}

	/**
	 * Check for any issues with php extensions.
	 * 
	 * @return array
	 */
	private function checkExtensions()
	{
		$problem = false;

		foreach ($this->extensions as $k => &$ext)
		{
			if ($ext['type'] === 'function') {
                $loaded = function_exists($ext['name']);
            } else {
                $loaded = extension_loaded($ext['name']);
            }

			//make notice if any extensions status
			//doesn't match what we need
			if ($loaded !== $ext['expected'])
			{
				$problem = true;
			}

			$ext['actual'] = $loaded;
		}

		$this->compatResults['problem'] = $problem;

		return $this->extensions;
	}

	/**
	 * Store admin account and basic details in db.
	 * 
	 * @param  array  $input
	 * @return void
	 */
	public function createAdmin($input)
	{
		$this->validateAdminCredentials($input);

        $this->bootFramework();

        $this->openConnection();

        $input['activated'] = 1;
        $input['permissions'] = array('superuser' => 1);
        unset($input['password_confirmation']);

        $this->app['sentry']->createUser($input);

        //mark as installed
        $content = file_get_contents('backend/env.example');

        $content = preg_replace("/(.*?INSTALLED=).*?(.+?)\\n/msi", '${1}1'."\n", $content);

        file_put_contents('backend/env.example', $content);

        rename('backend/env.example', 'backend/.env');

        echo json_encode(array('status' => 'success')); exit;
	}

	/**
	 * Insert db credentials if needed, create schema and seed the database.
	 * 
	 * @param  array  $input
	 * @return array
	 */
	public function createDb($input)
	{
        if ($message = $this->validateDbCredentials($input)) {
            echo json_encode(array('status' => 'error', 'message' => $message)); exit;
        }

        $this->insertDBCredentials($input);

        $this->bootFramework();

        $this->openConnection($input);

        $this->seeder = new Builder\Install\Seeder($this->app, $this->app['projects.creator']);
        $this->schema = new Builder\Install\Schema($this->app);

        try {
        	$this->schema->create();
        } catch(Illuminate\Database\QueryException $e) {
            //catch only table exists error
        	if ($e->errorInfo[1] !== 1050) {
        		throw $e;
        	}
        }

        $this->seeder->seed();
        
        echo json_encode(array('status' => 'success')); exit;
	}

    /**
     * Open a database connection with given credentials.
     *
     * @param  array  $input
     * @return void
     */
    private function openConnection($input = array())
    {
        if (empty($input)) {
            $input = array(
                'host'     => getenv('DB_HOST'),
                'database' => getenv('DB_DATABASE'),
                'username' => getenv('DB_USERNAME'),
                'password' => getenv('DB_PASSWORD'),
                'prefix'   => getenv('DB_PREFIX'),
            );
        }

        if ( ! isset($input['driver'])) {
            $input['driver'] = 'mysql';
        }

        if ( ! isset($input['collation'])) {
            $input['collation'] = 'utf8_unicode_ci';
        }

        if ( ! isset($input['charset'])) {
            $input['charset'] = 'utf8';
        }

        $capsule = $this->app['illuminate.capsule'];
        $capsule->addConnection($input);
        $capsule->bootEloquent();
        $capsule->setAsGlobal();
    }

    private function validateAdminCredentials($input)
    {
        if ( ! isset($input['email'])) { echo json_encode(array('status' => 'error', 'message' => 'Please specify the administrator email address.')); exit; }
        if ( ! isset($input['password'])) { echo json_encode(array('status' => 'error', 'message' => 'Please specify the administrator password.')); exit; }
        if ( ! isset($input['password_confirmation'])) { echo json_encode(array('status' => 'error', 'message' => 'Please confirm the administrator password.')); exit; }
        if ( ! filter_var($input['email'], FILTER_VALIDATE_EMAIL)) { echo json_encode(array('status' => 'error', 'message' => 'Please enter a valid emails address.')); exit; }
        if (strlen($input['password']) < 4) { echo json_encode(array('status' => 'error', 'message' => 'Password must be at least 4 characters length')); exit; }
        if (strcmp($input['password'], $input['password_confirmation'])) { echo json_encode(array('status' => 'error', 'message' => 'Specified password does not match the confirmed password')); exit; }
    }

    private function validateDbCredentials($input)
    {
        $credentials = array_merge(array(
            'host'     => null,
            'database' => null,
            'username' => null,
            'password' => null
        ), $input);

        $db =  'mysql:host='.$credentials['host'].';dbname='.$credentials['database'];

        try {
            $db = new PDO($db, $credentials['username'], $credentials['password'], array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        } catch(PDOException $e) {
            return $e->getMessage();
        }
    }

	/**
	 * Insert user supplied db credentials into .env file.
	 * 
	 * @param  array   $input
	 * @return void
	 */
	private function insertDBCredentials(array $input)
	{
        $content = file_get_contents('backend/env.example');

        foreach ($input as $key => $value) {
        	if ( ! $value) $value = '';
        	$content = preg_replace("/(.*?DB_$key=)(.*?)\\n/msi", '${1}'.$value."\n", $content);
        }

		//put new credentials in a .env file
        file_put_contents('backend/env.example', $content);
	}

    /**
     * Change app env to production and set debug to false in .env file.
     */
    private function putAppInProductionEnv()
    {
        $content = file_get_contents('backend/env.example');

        //set env to production
        $content = preg_replace("/(.*?APP_ENV=).*?(.+?)\\n/msi", '${1}production'."\n", $content);

        //set debug to false
        $content = preg_replace("/(.*?APP_DEBUG=).*?(.+?)\\n/msi", '${1}false'."\n", $content);

        //set base url for env
        $content = preg_replace("/(.*?BASE_URL=).*?(.+?)\\n/msi", '${1}'.url()."\n", $content);

        file_put_contents('backend/.env', $content);
    }

    private function bootFramework()
    {
        if ( ! $this->bootStrapped) {
            require_once BASE_PATH.'/backend/vendor/autoload.php';

            $dotenv = new Dotenv\Dotenv(BASE_PATH.'/backend', 'env.example');
            $dotenv->load();

            date_default_timezone_set('Europe/Vilnius');

            $this->app = new Silex\Application();
            $this->app['base_dir'] = BASE_PATH;

            $this->app->register(new Silex\Provider\ServiceControllerServiceProvider());
            $this->app->register(new Builder\Database\DatabaseServiceProvider());
            $this->app->register(new Builder\Users\SentryServiceProvider());
            $this->app->register(new Builder\Projects\Services\ProjectCreatorServiceProvider());

            $this->bootStrapped = true;
        }
    }

    public function finalizeInstallation()
    {
        $this->bootFramework();

        try {
            $this->deleteInstallationFiles();
        } catch (Exception $e) {}

        if ($response['status'] === 'error') {
            echo json_encode($response); exit;
        }

        $this->putAppInProductionEnv();

        echo json_encode(array('status' => 'success', 'message' => 'success')); exit;
    }

    private function deleteInstallationFiles()
    {
        $dir = BASE_PATH.'/install_files';

        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

        foreach($files as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        @rmdir($dir);
    }

    private function testAndFixHtaccessFile()
    {
        $response = $this->htaccessTest();

        if ($response === 404 || $response === 500) {
            $this->htaccessAddSlash();

            $response = $this->htaccessTest();

            if ($response === 404 || $response === 500) {
                $this->htaccessRemoveSlash();

                $this->htaccessDisableMultiViews();

                $response = $this->htaccessTest();

                if ($response === 404 || $response === 500) {
                    $this->htaccessEnableMultiViews();
                    return (array('status' => 'error', 'message' => 'htacces error'));          
                }
            
            }
        }

        return (array('status' => 'success', 'message' => 'success'));  
    }

    private function htaccessDisableMultiViews()
    {
        $path = BASE_PATH.'/.htaccess';
        file_put_contents($path, str_replace('Options -MultiViews', '', file_get_contents($path)));
    }

    private function htaccessEnableMultiViews()
    {
        $path = BASE_PATH.'/.htaccess';
        $contents = file_get_contents($path);

        if (strrpos($contents, 'Options -MultiViews') === false) {
            file_put_contents($path, str_replace('<IfModule mod_negotiation.c>', "<IfModule mod_negotiation.c>\n\t\tOptions -MultiViews", $contents));
        }
    }

    private function htaccessAddSlash()
    {
        $path = BASE_PATH.'/.htaccess';
        file_put_contents($path, str_replace('RewriteRule ^ index.php [QSA,L]', 'RewriteRule ^ /index.php [QSA,L]', file_get_contents($path)));
    }

    private function htaccessRemoveSlash()
    {
        $path = BASE_PATH.'/.htaccess';
        file_put_contents($path, str_replace('RewriteRule ^ /index.php [QSA,L]', 'RewriteRule ^ index.php [QSA,L]', file_get_contents($path)));
    }

    private function htaccessTest()
    {
        $url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]htaccess-test";

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_HEADER, true);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($handle, CURLOPT_TIMEOUT, 6);

        $response = curl_exec($handle);

        curl_close($handle);

        if (strpos($response, '404 Not Found') > -1) {
            return 404;
        }

        if (strpos($response, '500 Internal Server Error') > -1) {
            return 500;
        }

        return 'success';
    }

    public function handleHtaccessFile()
    {
        $path = BASE_PATH.'/.htaccess';

        if ( ! file_exists($path)) {
            $contents =
'<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews
    </IfModule>

    RewriteEngine On
    #RewriteBase /architect
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [QSA,L]
</IfModule>';

        file_put_contents($path, $contents);

        }
    }
}