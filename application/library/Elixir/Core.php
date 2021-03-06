<?php

/**
 * Contains the most low-level helpers methods in Elixir:
 *
 * - Environment initialization
 * - Locating files within the cascading filesystem
 * - Auto-loading and transparent extension of classes
 * - Variable and path debugging
 *
 * @package    Elixir
 * @category   Base
 * @author    Not well-known man
 * @copyright  (c) 2016-2017 Elixir Team
 * @license
 */
class Elixir_Core
{

    // Release version and codename
    const VERSION = '1.0.0';

    // Common environment type constants for consistency and convenience
    const PRODUCTION = 10;
    const STAGING = 20;
    const TESTING = 30;
    const DEVELOPMENT = 40;

    // Security check that is added to all generated PHP files
    const FILE_SECURITY = '<?php defined(\'DOCROOT\') OR die(\'No direct script access.\');';

    // Format of cache files: header, cache name, and data
    const FILE_CACHE = ":header \n\n// :name\n\n:data\n";

    /**
     * @var  string  Current environment name
     */
    public static $environment = Elixir::DEVELOPMENT;

    /**
     * @var  boolean  True if [magic quotes](http://php.net/manual/en/security.magicquotes.php) is enabled.
     */
    public static $magic_quotes = FALSE;

    /**
     * @var  string
     */
    public static $content_type = 'text/html';

    /**
     * @var  string  character set of input and output
     */
    public static $charset = 'utf-8';

    /**
     * @var  string  the name of the server Elixir is hosted upon
     */
    public static $server_name = '';

    /**
     * @var  array   list of valid host names for this instance
     */
    public static $hostnames = array();

    /**
     * @var  string  base URL to the application
     */
    public static $base_url = '/';

    /**
     * @var  string  Application index file, added to links generated by Elixir. Set by [Elixir::init]
     */
    public static $index_file = 'index.php';

    /**
     * @var  string  Cache directory, used by [Elixir::cache]. Set by [Elixir::init]
     */
    public static $cache_dir;

    /**
     * @var  integer  Default lifetime for caching, in seconds, used by [Elixir::cache]. Set by [Elixir::init]
     */
    public static $cache_life = 60;


    /**
     * @var  Log  logging object
     */
    public static $log;

    /**
     * @var  boolean  Has [Elixir::init] been called?
     */
    protected static $_init = FALSE;


    /**
     * Initializes the environment:
     *
     * - Disables register_globals and magic_quotes_gpc
     * - Determines the current environment
     * - Set global settings
     * - Sanitizes GET, POST, and COOKIE variables
     * - Converts GET, POST, and COOKIE variables to the global character set
     *
     * The following settings can be set:
     *
     * Type      | Setting    | Description                                    | Default Value
     * ----------|------------|------------------------------------------------|---------------
     * `string`  | base_url   | The base URL for your application.  This should be the *relative* path from your DOCROOT to your `index.php` file, in other words, if Elixir is in a subfolder, set this to the subfolder name, otherwise leave it as the default.  **The leading slash is required**, trailing slash is optional.   | `"/"`
     * `string`  | index_file | The name of the [front controller](http://en.wikipedia.org/wiki/Front_Controller_pattern).  This is used by Elixir to generate relative urls like [HTML::anchor()] and [URL::base()]. This is usually `index.php`.  To [remove index.php from your urls](tutorials/clean-urls), set this to `FALSE`. | `"index.php"`
     * `string`  | charset    | Character set used for all input and output    | `"utf-8"`
     * `string`  | cache_dir  | Elixir's cache directory.  Used by [Elixir::cache] for simple internal caching, like [Fragments](Elixir/fragments) and **\[caching database queries](this should link somewhere)**.  This has nothing to do with the [Cache module](cache). | `APPPATH."cache"`
     * `integer` | cache_life | Lifetime, in seconds, of items cached by [Elixir::cache]         | `60`
     *
     * @throws  Elixir_Exception
     * @param   array $settings Array of settings.  See above.
     * @return  void
     * @uses    Elixir::globals
     * @uses    Elixir::sanitize
     * @uses    Elixir::cache
     * @uses    Profiler
     */
    public static function init(array $settings = NULL)
    {
        if (Elixir::$_init) {
            // Do not allow execution twice
            return;
        }

        // Elixir is now initialized
        Elixir::$_init = TRUE;

        // Start an output buffer
        ob_start();

        /**
         * Enable xdebug parameter collection in development mode to improve fatal stack traces.
         */
        if (Elixir::$environment == Elixir::DEVELOPMENT AND extension_loaded('xdebug')) {
            ini_set('xdebug.collect_params', 3);
        }

        if (ini_get('register_globals')) {
            // Reverse the effects of register_globals
            Elixir::globals();
        }

        if (isset($settings['cache_dir'])) {
            if (!is_dir($settings['cache_dir'])) {
                try {
                    // Create the cache directory
                    mkdir($settings['cache_dir'], 0755, TRUE);

                    // Set permissions (must be manually set to fix umask issues)
                    chmod($settings['cache_dir'], 0755);
                } catch (Exception $e) {
                    throw new Elixir_Exception('Could not create cache directory :dir',
                        array(':dir' => Debug::path($settings['cache_dir'])));
                }
            }

            // Set the cache directory path
            Elixir::$cache_dir = realpath($settings['cache_dir']);
        } else {
            // Use the default cache directory
            Elixir::$cache_dir = STORAGEPATH . '/cache';
        }

        if (!is_writable(Elixir::$cache_dir)) {
            throw new Elixir_Exception('Directory :dir must be writable',
                array(':dir' => Debug::path(Elixir::$cache_dir)));
        }

        if (isset($settings['cache_life'])) {
            // Set the default cache lifetime
            Elixir::$cache_life = (int)$settings['cache_life'];
        }

        if (isset($settings['charset'])) {
            // Set the system character set
            Elixir::$charset = strtolower($settings['charset']);
        }

        if (function_exists('mb_internal_encoding')) {
            // Set the MB extension encoding to the same character set
            mb_internal_encoding(Elixir::$charset);
        }

        if (isset($settings['base_url'])) {
            // Set the base URL
            Elixir::$base_url = rtrim($settings['base_url'], '/') . '/';
        }

        if (isset($settings['index_file'])) {
            // Set the index file
            Elixir::$index_file = trim($settings['index_file'], '/');
        }

        // Determine if the extremely evil magic quotes are enabled
        Elixir::$magic_quotes = (bool)get_magic_quotes_gpc();

        // Sanitize all request variables
        $_GET = Elixir::sanitize($_GET);
        $_POST = Elixir::sanitize($_POST);
        $_COOKIE = Elixir::sanitize($_COOKIE);

        // Load the logger if one doesn't already exist
        if (!Elixir::$log instanceof Log) {
            Elixir::$log = Log::instance();
            Elixir_Exception::$log = Log::instance();
            Elixir_Exception::$error_view = __DIR__ . '/Error.php';
            /**
             * Attach the file write to logging. Multiple writers are supported.
             */
            Elixir::$log->attach(new Log_File(STORAGEPATH . '/logs'));
        }
    }

    /**
     * Reverts the effects of the `register_globals` PHP setting by unsetting
     * all global variables except for the default super globals (GPCS, etc),
     * which is a [potential security hole.][ref-wikibooks]
     *
     * This is called automatically by [Elixir::init] if `register_globals` is
     * on.
     *
     *
     * [ref-wikibooks]: http://en.wikibooks.org/wiki/PHP_Programming/Register_Globals
     *
     * @return  void
     */
    public static function globals()
    {
        if (isset($_REQUEST['GLOBALS']) OR isset($_FILES['GLOBALS'])) {
            // Prevent malicious GLOBALS overload attack
            echo "Global variable overload attack detected! Request aborted.\n";

            // Exit with an error status
            return;
//            exit(1);
        }

        // Get the variable names of all globals
        $global_variables = array_keys($GLOBALS);

        // Remove the standard global variables from the list
        $global_variables = array_diff($global_variables, array(
            '_COOKIE',
            '_ENV',
            '_GET',
            '_FILES',
            '_POST',
            '_REQUEST',
            '_SERVER',
            '_SESSION',
            'GLOBALS',
        ));

        foreach ($global_variables as $name) {
            // Unset the global variable, effectively disabling register_globals
            unset($GLOBALS[$name]);
        }
    }

    /**
     * Recursively sanitizes an input variable:
     *
     * - Strips slashes if magic quotes are enabled
     * - Normalizes all newlines to LF
     *
     * @param   mixed $value any variable
     * @return  mixed   sanitized variable
     */
    public static function sanitize($value)
    {
        if (is_array($value) OR is_object($value)) {
            foreach ($value as $key => $val) {
                // Recursively clean each value
                $value[$key] = Elixir::sanitize($val);
            }
        } elseif (is_string($value)) {
            if (Elixir::$magic_quotes === TRUE) {
                // Remove slashes added by magic quotes
                $value = stripslashes($value);
            }

            if (strpos($value, "\r") !== FALSE) {
                // Standardize newlines
                $value = str_replace(array("\r\n", "\r"), "\n", $value);
            }
        }

        return $value;
    }


    /**
     * Provides simple file-based caching for strings and arrays:
     *
     *     // Set the "foo" cache
     *     Elixir::cache('foo', 'hello, world');
     *
     *     // Get the "foo" cache
     *     $foo = Elixir::cache('foo');
     *
     * All caches are stored as PHP code, generated with [var_export][ref-var].
     * Caching objects may not work as expected. Storing references or an
     * object or array that has recursion will cause an E_FATAL.
     *
     * The cache directory and default cache lifetime is set by [Elixir::init]
     *
     * [ref-var]: http://php.net/var_export
     *
     * @throws  Elixir_Exception
     * @param   string $name name of the cache
     * @param   mixed $data data to cache
     * @param   integer $lifetime number of seconds the cache is valid for
     * @return  mixed    for getting
     * @return  boolean  for setting
     */
    public static function cache($name, $data = NULL, $lifetime = NULL)
    {
        // Cache file is a hash of the name
        $file = sha1($name) . '.txt';

        // Cache directories are split by keys to prevent filesystem overload
        $dir = Elixir::$cache_dir . DIRECTORY_SEPARATOR . $file[0] . $file[1] . DIRECTORY_SEPARATOR;

        if ($lifetime === NULL) {
            // Use the default lifetime
            $lifetime = Elixir::$cache_life;
        }

        if ($data === NULL) {
            if (is_file($dir . $file)) {
                if ((time() - filemtime($dir . $file)) < $lifetime) {
                    // Return the cache
                    try {
                        return unserialize(file_get_contents($dir . $file));
                    } catch (Exception $e) {
                        // Cache is corrupt, let return happen normally.
                    }
                } else {
                    try {
                        // Cache has expired
                        unlink($dir . $file);
                    } catch (Exception $e) {
                        // Cache has mostly likely already been deleted,
                        // let return happen normally.
                    }
                }
            }

            // Cache not found
            return NULL;
        }

        if (!is_dir($dir)) {
            // Create the cache directory
            mkdir($dir, 0644, TRUE);

            // Set permissions (must be manually set to fix umask issues)
            chmod($dir, 0644);
        }

        // Force the data to be a string
        $data = serialize($data);

        try {
            // Write the cache
            return (bool)file_put_contents($dir . $file, $data, LOCK_EX);
        } catch (Exception $e) {
            // Failed to write cache
            return FALSE;
        }
    }


    /**
     * Generates a version string based on the variables defined above.
     *
     * @return string
     */
    public static function version()
    {
        return 'Elixir Framework ' . Elixir::VERSION;
    }

}
