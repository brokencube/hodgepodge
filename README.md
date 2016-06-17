New home of my personal microframework 

Current dependancies:
* PHP 5.5+

Soft dependancies for specific functionality
* Memcached or PEAR Cache_Lite (For caching)
* Imagick extension (For image processing class)
* brokencube/Automatorm (For database session functionality)
* Smarty (For default templating code)

Various things still missing from the library - it gets updated as I need new features, or find bugs that are actively affecting me.

I've tried to clean up the code to be (99%) compliant with [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md), [PSR-1](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md) and [PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md) coding standards. (See http://www.php-fig.org for more details.) There might be a few places that I haven't fixed up yet.

TBH, the plan eventually is to replace most parts of this with generic libraries from else where. Eventually, this library should just be the more esoteric functionality that I need.

Things removed so far:
* Autoloading (replaced with Composer)
* ORM (forked out as https://github.com/brokencube/automatorm)
* Database functions (migrated to Automatorm)
* Logging deintegrated - various classes now take PSR-3 objects instead (Core\Log is now PSR-3 compliant)
