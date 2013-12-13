
<?php

/**
 * Cache data source class.
 *
 * @package     Cache
 * @subpackage  Cache.Model.Datasource
 * @author      oanhnn <oanhnn.bk@gmail.com>
 */

App::uses('DataSource', 'Model/Datasource');
App::uses('Cache', 'Cache');

/**
 * CacheSource datasource
 *
 * Gets find results from cache instead of the original datasource.
 *
 * @package     Cache
 * @subpackage  Cache.Model.Datasource
 * @version       1.0
 */
class CachedSource extends DataSource {

    /**
     * @const string Cache key for map keys
     */
    const MAP_CACHE_KEY = 'map';
    
    /**
     * Stored original datasource for fallback methods
     *
     * @var DataSource
     */
    public $source = null;
    
    /**
     * Constructor
     *
     * Sets default options if none are passed when the datasource is created and
     * creates the cache configuration. If a `config` is passed and is a valid
     * Cache configuration, CacheSource uses its settings
     *
     * ### Extra config settings
     * - `original` The name of the original datasource, i.e., 'default' (required)
     * - `config` The name of the Cache configuration to use. Uses 'default' by default
     * - other settings required by DataSource...
     *
     * @param array $config Configure options
     */
    public function __construct($config = array()) {
        $config = array_merge(array('config' => 'default'), $config);
        parent::__construct($config);

        if (Configure::read('Cache.disable') === true) {
            return;
        }
        if (!isset($this->config['original'])) {
            throw new CakeException('Missing name of original datasource.');
        }
        if (!Cache::isInitialized($this->config['config'])) {
            throw new CacheException(sprintf('Missing cache configuration for "%s".', $this->config['config']));
        }
        if (!Cache::isInitialized($this->config['map'])) {
            throw new CacheException(sprintf('Missing cache configuration for "%s".', $this->config['map']));
        }
        $this->source = ConnectionManager::getDataSource($this->config['original']);
    }

    /**
     * Redirects calls to original datasource methods. Needed if the `Cached.Cache` 
     * behavior is attached before other behaviors that use the model's datasource methods.
     * 
     * @param string $name Original db source function name
     * @param array $arguments Arguments
     * @return mixed
     */
    public function __call($name, $arguments) {
        if (is_callable(array($this, $name))) {
            return call_user_func_array(array($this, $name), $arguments);
        }
        return call_user_func_array(array($this->source, $name), $arguments);
    }

    /**
     * Reads from cache if it exists. If not, it falls back to the original
     * datasource to retrieve the data and cache it for later
     *
     * @param Model $model
     * @param array $query
     * @param int $recursive
     * @return array Results
     * @see DataSource::read()
     */
    public function read(Model $model, $query = array(), $recursive = null) {
        // Resets the model's datasource to the original
        $model->setDataSource(ConnectionManager::getSourceName($this->source));
        
        $key = $this->_key($model, $query);
        $results = Cache::read($key, $this->config['config']);
        if ($results === false) {
            $results = $this->source->read($model, $query, $recursive);
            // compress before storing
            if ($this->_useGzip()) {
                Cache::write($key, gzcompress(serialize($results)), $this->config['config']);
            } else {
                Cache::write($key, $results, $this->config['config']);
            }
            $this->_map($model, $key);
        } else {
            // uncompress data from cache
            if ($this->_useGzip()) {
                $results = unserialize(gzuncompress($results));
            }
        }
        
        Cache::set(null, $this->config['config']);
        return $results;
    }

    /*
     * Clears the cache for a specific model and rewrites the map. Pass query to
     * clear a specific query's cached results
     *
     * @param array $query If null, clears all for this model
     * @param Model $model The model to clear the cache for
     */
    public function clearModelCache(Model $model, $query = null) {
        $map = Cache::read(self::MAP_CACHE_KEY, $this->config['map']);
        $sourceName = $this->config['original'];
        $keys = array();
        
        if ($query !== null) {
            $keys = array($this->_key($model, $query) => 1);
        } else {
            if (!empty($map[$sourceName]) && !empty($map[$sourceName][$model->alias])) {
                $keys = $map[$sourceName][$model->alias];
            }
        }

        if (empty($keys)) {
            return;
        }
        
        foreach ($keys as $cacheKey => $a) {
            Cache::delete($cacheKey, $this->config['config']);
            unset($map[$sourceName][$model->alias][$cacheKey]);
        }
        if (empty($map[$sourceName][$model->alias])) {
            unset($map[$sourceName][$model->alias]);
        }
        Cache::write(self::MAP_CACHE_KEY, $map, $this->config['map']);
    }

    /**
     * Since Datasource has the method `describe()`, it won't be caught `__call()`.
     * This ensures it is called on the original datasource properly.
     * 
     * @param Model|string $model
     * @return mixed 
     */
    public function describe($model) {
        if (method_exists($this->source, 'describe')) {
            return $this->source->describe($model);
        }
        return parent::describe($model);
    }

    /**
     * Check use Gzip before cache
     * 
     * @return boolean
     */
    protected function _useGzip() {
        return isset($this->config['gzip']) && $this->config['gzip'];
    }

    /**
     * Hashes a query into a unique string and creates a cache key
     *
     * @param Model $model The model
     * @param array $query The query
     * @return string
     */
    protected function _key(Model $model, $query) {
        if (isset($query['cache']['key'])) {
            $queryHash = (string) $query['cache']['key'];
        } else {
            unset($query['cache']);
            $queryHash = md5(serialize($query));
        }
        $gzip = $this->_useGzip() ? '_gz' : '';
        
        $sourceName = $this->config['original'];
        return Inflector::underscore($sourceName) . '_' . Inflector::underscore($model->alias) . '_' . $queryHash . $gzip;
    }

    /**
     * Creates a cache map (used for deleting cache keys or groups)
     * 
     * @param Model $model
     * @param string $key 
     */
    protected function _map(Model $model, $key) {
        $map = Cache::read(self::MAP_CACHE_KEY, $this->config['map']);
        $sourceName = $this->config['original'];
        
        if ($map === false) {
            $map = array();
        }
        $map = Set::merge($map, array(
                    $sourceName => array(
                        $model->alias => array(
                            $key => 1
                        )
                    )
                ));
        Cache::write(self::MAP_CACHE_KEY, $map, $this->config['map']);
    }
}
