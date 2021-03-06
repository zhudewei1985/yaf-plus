<?php

/**
 * Database query wrapper.  See [Parameterized Statements](database/query/parameterized) for usage and examples.
 *
 * @package    Elixir/Database
 * @category   Query
 * @author    Not well-known man
 * @copyright  (c) 2016-2017 Elixir Team
 * @license
 */
class Database_Query
{

    // Query type
    protected $_type;

    // Execute the query during a cache hit
    protected $_force_execute = FALSE;
    // Cache lifetime
    protected $_lifetime = NULL;
    // SQL statement
    protected $_sql;

    // Quoted query parameters
    protected $_parameters = array();

    // Return results as associative arrays or objects
    protected $_as_object = FALSE;

    // Parameters for __construct when using object results
    protected $_object_params = array();

    /**
     * Creates a new SQL query of the specified type.
     *
     * @param integer $type query type: Database::SELECT, Database::INSERT, etc
     * @param string $sql query string
     * @return  void
     */
    public function __construct(int $type, string $sql)
    {
        $this->_type = $type;
        $this->_sql = $sql;
    }

    /**
     * Return the SQL query string.
     *
     * @return  string
     */
    public function __toString(): string
    {
        try {
            // Return the SQL string
            return $this->compile(Database::instance());
        } catch (Exception $e) {
            return Elixir_Exception::text($e);
        }
    }

    /**
     * Get the type of the query.
     *
     * @return  integer
     */
    public function type(): int
    {
        return $this->_type;
    }

    /**
     * Enables the query to be cached for a specified amount of time.
     *
     * @param integer $lifetime number of seconds to cache, 0 deletes it from the cache
     * @param boolean $force whether or not to execute the query during a cache hit
     * @return  $this
     */
    public function cached(int $lifetime = 0, bool $force = FALSE)
    {
        //仅在生产环境使用cache
        if (\Yaf\ENVIRON === 'product') {
            if ($lifetime === 0) {
                $lifetime = \Yaf\Application::app()->getConfig()->get('redis.lifetime') ?: Cache::DEFAULT_EXPIRE;
            }
            $this->_force_execute = $force;
            $this->_lifetime = $lifetime;
        }
        return $this;
    }

    /**
     * Returns results as associative arrays
     *
     * @return  $this
     */
    public function as_assoc()
    {
        $this->_as_object = FALSE;

        $this->_object_params = array();

        return $this;
    }

    /**
     * Returns results as objects
     *
     * @param bool $class classname or TRUE for stdClass
     * @param array $params
     * @return  $this
     */
    public function as_object(bool $class = TRUE, array $params = NULL)
    {
        $this->_as_object = $class;

        if ($params) {
            // Add object parameters
            $this->_object_params = $params;
        }

        return $this;
    }

    /**
     * Set the value of a parameter in the query.
     *
     * @param string $param parameter key to replace
     * @param mixed $value value to use
     * @return  $this
     */
    public function param(string $param, $value)
    {
        // Add or overload a new parameter
        $this->_parameters[$param] = $value;

        return $this;
    }

    /**
     * Bind a variable to a parameter in the query.
     *
     * @param string $param parameter key to replace
     * @param mixed $var variable to use
     * @return  $this
     */
    public function bind(string $param, & $var)
    {
        // Bind a value to a variable
        $this->_parameters[$param] =& $var;

        return $this;
    }

    /**
     * Add multiple parameters to the query.
     *
     * @param array $params list of parameters
     * @return  $this
     */
    public function parameters(array $params)
    {
        // Merge the new parameters in
        $this->_parameters = $params + $this->_parameters;

        return $this;
    }

    /**
     * Compile the SQL query and return it. Replaces any parameters with their
     * given values.
     *
     * @param mixed $db Database instance or name of instance
     * @return  string
     */
    public function compile($db = NULL)
    {
        if (!is_object($db)) {
            // Get the database instance
            $db = Database::instance($db);
        }

        // Import the SQL locally
        $sql = $this->_sql;

        if (!empty($this->_parameters)) {
            // Quote all of the values
            $values = array_map(array($db, 'quote'), $this->_parameters);

            // Replace the values in the SQL
            $sql = strtr($sql, $values);
        }

        return $sql;
    }

    /**
     * Execute the current query on the given database.
     *
     * @param mixed $db Database instance or name of instance
     * @param bool   result object classname, TRUE for stdClass or FALSE for array
     * @param array    result object constructor arguments
     * @return  object   Database_Result for SELECT queries
     * @return  mixed    the insert id for INSERT queries
     * @return  integer  number of affected rows for all other queries
     */
    public function execute($db = NULL, bool $as_object = NULL, array $object_params = NULL)
    {
        if (!is_object($db)) {
            // Get the database instance
            if (is_null($db))
                $db = Database::instance();
            else
                $db = Database::instance($db);
        }

        if ($as_object === NULL) {
            $as_object = $this->_as_object;
        }

        if ($object_params === NULL) {
            $object_params = $this->_object_params;
        }

        // Compile the SQL query
        $sql = $this->compile($db);

        if ($this->_lifetime !== NULL AND $this->_type === Database::SELECT) {
            // Set the cache key based on the database instance name and SQL
            $cache_key = 'sql:' . sha1('Database::query("' . $db . '", "' . $sql . '")');
            // Read the cache first to delete a possible hit with lifetime <= 0
            if (($result = Cache::instance()->get($cache_key)) !== FALSE AND !$this->_force_execute
            ) {
                // Return a cached result
                return new Database_Result_Cached($result, $sql, $as_object, $object_params);
            }
        }

        // Execute the query
        $result = $db->query($this->_type, $sql, $as_object, $object_params);
        if (isset($cache_key) AND $this->_lifetime > 0) {
            // Cache the result array
            Cache::instance()->set($cache_key, $result->as_array(), $this->_lifetime);
        }
        return $result;
    }

    /**
     * 计算记录总数
     * @param null $db
     * @return int
     */
//    public function count_records($db = NULL): int
//    {
//        if (!is_object($db)) {
//            // Get the database instance
//            $db = Database::instance($db);
//        }
//
//        // Compile the SQL query
//        $sql = $this->compile($db);
//        preg_match('#FROM(.*)(LIMIT|ORDER)#isU', $sql, $matches);
//        $sql = trim($matches[1]);
//        return $db->query(Database::SELECT, 'SELECT COUNT(*) AS total_row_count FROM '.$sql, FALSE)
//            ->get('total_row_count', 0);
//    }

} // End Database_Query
