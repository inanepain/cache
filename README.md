version: $Id$ ($Date$)

Some simple caching tools implementing
[`PSR-6`](https://www.php-fig.org/psr/psr-6) and
[`PSR-16`](https://www.php-fig.org/psr/psr-16).

# Goals

There is a lot to cover here so best I keep a bit of a record so as not
to let bits fall between the cracks. As a bonus, a record will help keep
me focused on the goal as well as track progress.  
Man can I talk/type crap oir what?

## Primary Goal

To reduce remote access when using internet or other non-local sourced
data.

# Example

Basic example, creating a `RemoteFileCache` object and using it to only
return a file once. Using the defaults any subsequent requests, within a
one day period for the same url, retrieves content from cache.

Basic RemoteFileCache Example

    $rfc = new \Inane\Cache\RemoteFileCache();
    $html = $rfc->get('http://example.com/files/example.html');

*"No way!"*, I hear you say. *"That is so simple!"*  
Yes, it is. But it is also very powerful.  
The `RemoteFileCache` class implements the `PSR-6` and `PSR-16`
interfaces so you can use it with any other library that implements
those interfaces. It also has a few extra features that make it even
more useful.
