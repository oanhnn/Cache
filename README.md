# Cache

Cache is a plugin for CakePHP that allows you to easily cache find results.
While most solutions for caching queries force you to overwrite `Model::find()`
in your AppModel, Cache only requires adding a behavior to your model.

Have settings that hardly change? Have a database list of states or something
that never change but you still want them in the db? Just like caching your
results? Use Cache!

## Requirements

* CakePHP >= 2.0.x (check tags for older versions of CakePHP)

## Install



## Usage

```php
    var $actsAs = array(
        'Cache.Cached'
    );
```
By default, Cache uses the 'default' cache configuration in your core.php file.
If you want to use a different configuration, just pass it in the 'config' key.

```php
    /**
     * @var array Using Behavior
     */
    public $actsAs = array(
        'Cache.Cached' => array(
            'config' => '_cake_queries_',
            'map' => '_cake_queries_map_',
            'clearOnDelete' => true,
            'clearOnSave' => true,
            'auto' => true,
            'gzip' => false,
        ),
    );
```

> It's best to place Cache last on your list of behaviors so the query Cacher
> looks for reflects the changes the previous behaviors might have made.

### Options that you can pass:

* `config` The name of an existing Cache configuration to duplicate (default 'default')
* `map` The name of an existing Cache configuration to duplicate (default 'default'), 
    for 'map of cache keys'
* `clearOnSave` Whether or not to delete the cache on saves (default `true`)
* `clearOnDelete` Whether or not to delete the cache on deletes (default `true`)
* `auto` Automatically cache (default `false`)
* `gzip` Automatically compress/decompress cached data (default `false`)

### Using Cache with `Model::find()`, `Controller::paginate()`, etc.

If you set auto to false, you can pass a `'cache'` key in your query that is
either `true` to cache the results, `false` to not cache it, or array options to 
overwrite default settings for that specific call.

```php
    // cache the results of this query for a day
    $this->Post->find('all', array(
		  'conditions' => array('Post.name LIKE' => '%awesome%'),
		  'cache' => array(
                'config'   => '_cake_queries_',
                'gzip'     => false,
                'key'      => 'key_of_cache',
                'duration' => '+1 days'
       ),
    ));
    // don't cache the results of this query at all
    $this->Post->find('all', array(
		  'conditions' => array('Post.name LIKE' => '%lame%'),
		  'cache' => false
    ));
    // cache using the default settings even if auto = false
    $this->Post->find('all', array(
		  'conditions' => array('Post.name LIKE' => '%okay i guess%'),
		  'cache' => true
    ));
```

## How it works

Cache intercepts any find query and temporarily changes the datasource to one 
that handle's checking the cache..

You can always disable Cache by using `Behavior::detach()` or `Behavior::disable()`.

## Features

* Quick and easy caching by just attaching the behavior to a model
* Clear cache for a specific model on the fly using `$this->Post->clearCache()`
* Clear a specific query by passing the conditions to `clearCache()`

## Task lists

- [ ] Write test scripts
- [ ] Write how to install

## Thank for

* [Jeremy Harris](mailto:jeremy@someguyjeremy.com) with [Cacher](https://github.com/jeremyharris/cacher)