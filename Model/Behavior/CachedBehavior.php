<?php

/**
 * Cache behavior class.
 *
 * @package     Cache
 * @subpackage  Cache.Model.Behavior
 * @author      oanhnn <oanhnn.bk@gmail.com>
 */
App::uses('ModelBehavior', 'Model/Behavior');
App::uses('Cache', 'Cache');

/**
 * CakePHP CachedBehavior
 * 
 * Example config:
 * <pre><code>
 * <?php
 * public $actsAs = array(
 *      'Cached' => array(
 *          'config'        => '_cake_queries_',
 *          'map'           => '_cake_queries_map_',
 *          'clearOnDelete' => true,
 *          'clearOnSave'   => true,
 *          'auto'          => true,
 *          'gzip'          => false,
 *      ),
 * );
 * ?>
 * </code></pre>
 * 
 * Example query:
 * <pre><code>
 * <?php
 * $query = array(
 *      'cache' => array(
 *          'config'   => '_cake_queries_',
 *          'gzip'     => false,
 *          'key'      => 'key_of_cache',
 *          'duration' => '+999 days'
 *      ),
 * );
 * ?>
 * </code></pre>
 * 
 * @package     Cache
 * @subpackage  Cache.Model.Behavior
 * @author      oanhnn <oanhnn.bk@gmail.com>
 * @version     1.0
 */
class CachedBehavior extends ModelBehavior
{
    /**
     * Whether or not to cache this call's results
     *
     * @var boolean
     */
    protected $_cachedResults = false;

    /**
     * Deault Settings
     *
     * @var array
     */
    protected $_defaults = array(
        'config' => 'default',
        'map' => 'default',
        'clearOnDelete' => true,
        'clearOnSave' => true,
        'auto' => false,
        'gzip' => false
    );

    /**
     * Sets up a connection using passed settings
     *
     * ### Config
     * - `config` The name of an existing Cache configuration to use. Default is 'default'
     * - `clearOnSave` Whether or not to delete the cache on saves
     * - `clearOnDelete` Whether or not to delete the cache on deletes
     * - `auto` Automatically cache or look for `'cache'` in the find conditions
     * 		where the key is `true` or a duration
     *
     * @param Model $model The calling model
     * @param array $config Configuration settings
     * @return void
     * @see Cache::config()
     */
    public function setup(Model $model, $config = array())
    {
        $settings = array_merge($this->_defaults, $config);

        // Modified and set config for CachedSoure by behavior setting
        $ds = ConnectionManager::getDataSource($model->useDbConfig);
        $dsConfig = array_merge($ds->config, array(
            'original' => $model->useDbConfig,
            'datasource' => 'Cache.CachedSource',
            'config' => $settings['config'],
            'map' => $settings['map'],
            'gzip' => $settings['gzip']
        ));
        if (!in_array('cached', ConnectionManager::sourceList())) {
            ConnectionManager::create('cached', $dsConfig);
        } else {
            $ds = ConnectionManager::getDataSource('cached');
            $ds->setConfig($dsConfig);
        }
        // Merge behavior setting
        if (!isset($this->settings[$model->alias])) {
            $this->settings[$model->alias] = $settings;
        } else {
            $this->settings[$model->alias] = array_merge($this->settings[$model->alias], $settings);
        }

        return parent::setup($model, $config);
    }

    /**
     * Intercepts find to use the caching datasource instead
     *
     * If `$queryData['cache']` is true, it will cache based on the setup settings
     * If `$queryData['cache']` is a duration, it will cache using the setup settings
     * and the new duration.
     *
     * @param Model $model The calling model
     * @param array $query The query
     * @return array The modified query
     * @see ModelBehavior::beforeFind()
     */
    public function beforeFind(Model $model, $query)
    {
        // Get setting auto cache
        $this->_cachedResults = $this->settings[$model->alias]['auto'];
        if (isset($query['cache'])) {
            if ($query['cache'] === false) {
                $this->_cachedResults = false;
            } else {
                $this->_cachedResults = true;
                // Get cache Config Name
                if (isset($query['cache']['config']) && !empty($query['cache']['config'])) {
                    // Modified CachedSource config by query
                    $ds = ConnectionManager::getDataSource('cached');
                    $dsConfig = array(
                        'config' => $query['cache']['config'],
                    );
                    if (isset($query['cache']['gzip'])) {
                        $dsConfig['gzip'] = $query['cache']['gzip'];
                        unset($query['cache']['gzip']);
                    }
                    $ds->setConfig($dsConfig);
                } else {
                    $query['cache']['config'] = $this->settings[$model->alias]['config'];
                }
                // Modified Cache Config by query
                $cacheConfig = array();
                if (isset($query['cache']['duration'])) {
                    $cacheConfig['duration'] = $query['cache']['duration'];
                    unset($query['cache']['duration']);
                }
                Cache::config($query['cache']['config'], $cacheConfig);
                unset($query['cache']['config']);
            }
        }

        if ($this->_cachedResults) {
            $model->setDataSource('cached');
        }

        return $query;
    }

    /**
     * After delete is called after any delete occurs on the attached model.
     * Intercepts delete to use the caching datasource instead
     * 
     * @param Model $model Model using this behavior
     * @return void
     */
    public function afterDelete(Model $model)
    {
        if ($this->settings[$model->alias]['clearOnDelete']) {
            $this->clearCache($model);
        }
        return parent::afterDelete($model);
    }

    /**
     * After save is called after a model is saved.
     * Intercepts save to use the caching datasource instead
     *
     * @param Model $model Model using this behavior
     * @param boolean $created True if this save created a new record
     * @return boolean
     */
    public function afterSave(Model $model, $created)
    {
        if ($this->settings[$model->alias]['clearOnSave']) {
            $this->clearCache($model);
        }
        return parent::afterSave($model, $created);
    }

    /**
     * Clears all of the cache for this model's find queries. Optionally, pass
     * `$queryData` to just clear a specific query
     *
     * @param Model $model The calling model
     * @param array $query
     * @return boolean
     */
    public function clearCache(Model $model, $query = null)
    {
        $ds = ConnectionManager::getDataSource('cached');
        return $ds->clearModelCache($model, $query);
    }

}
