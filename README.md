# WP-Static-File-Generator
A class to grab WordPress-Posts and other data via the API and inject it into static files.

## WordPress Static Site Generator

**Use case**: You want to use WordPress for storing your content, but entirely want to keep control of the layout without the hassle of creating a WordPress-Plugin.

This tool uses the WordPress API and generates a static file with WordPress-Content based on your template.

Just add placeholders (injection points) in the places where you want the content of a post to appear. This tool will automatically inject the respective content.

Add one or more of the following markers into your template (**Note**: These injection-points are freely definable, see Tutorial).

| Injection Point | Respective Content |
| --- | --- |
| ###title### | Title of post |
| ###content### | Content of post |
| ###author### | Author of post |
| ###date### | Date of post |
| ###yoast_head### | Add this marker to your HTML-Head if you use the Yoast-SEO Plugin and want the Yoast-Head in your &lt;head&gt; |

You can either create just one static file for a specific post, or a number of static files for all posts.

"Remove WordPress Classes" will get rid of all CSS-classes from WordPress.

An example for usage can be found in the sources.

index.html is an example of how to set up your template.

To check it out go to: [Working Example](https://wp-static-file-generator.die-wordpress-agentur.de/index.html)

**Important**
The class allows the free definition of injection-points and which data to inject. See Tutorial.

## Tutorial
Here is an example on how to use the class.

```php
$generator = new WPHeadlessStaticGenerator("http://localhost/wordpress/index.php/wp-json/wp/v2", "/tmp/WPHeadless/article_template2.html");
$generator->setSavePath("/tmp/WPHeadless/");
$generator->setFileOwner('butsch');

$generator->setDateFormat('d.m.Y');

$generator->setWrapArray(array('###tags###' => '|, '));

$markerArray = array(
    'title->rendered' => '###title###', 
    'content->rendered' => '###content###',
    'date' => '###date###',
    'author|users|name' => '###author###',
    'tags|tags|name' => '###tags###'
);

$generator->injectDataIntoTemplate('posts/', $markerArray);`
```

Let's explore the code.

```php 
$generator = new WPHeadlessStaticGenerator("http://localhost/wordpress/index.php/wp-json/wp/v2", "/tmp/WPHeadless/article_template2.html");`
```
This is the constructor defining the API-URL and the location of your template. The template may be located locally or somewhere online.

```php
$generator->setSavePath("/tmp/WPHeadless/");
```
This defines where you want to save the generated file(s).

```php
$generator->setFileOwner('butsch');
```
Only set this if you are working on a local machine and want to edit the files after they have been generated.

```php
$generator->setDateFormat('d.m.Y');
```
If you want to inject a date in some place of your template, you can define here the respective date-format. If it is empty, the standard date format will be used.

```php
$generator->setWrapArray(array('###tags###' => '|, '));
```
Now it is getting slightly more complicated. There are cases in which you read data that is just a series of strings. An example are the categories that a post belongs to. If a post belongs to more than one category, you would get something like: "Category1Category2Category3". If you wanted to have the Category-Strings be separated by a comma, you can use a **wrap**. 

A wrap is basically a pipe (|) having a pre- and a post-part. Everything left of the pipe is a pre-part, and everything on the right a post-part. In the example above, there is nothing on the left, but there is ", " on the right. The result is: "Category1, Category2, Category3". 

If you wanted to wrap the categories (or any other data) with "&lt;div&gt;&lt;/div&gt;" you would use "&lt;div&gt;|&lt;/div&gt;". You can assign a wrap to any injection-point you are using. In the example above, I use the wrap on "###tags###". The "setWrapArray"-method expects an array with "###injectionpoint" => "your|wrap".

```php
$markerArray = array(
    'title->rendered' => '###title###', 
    'content->rendered' => '###content###',
    'date' => '###date###',
    'author|users|name' => '###author###',
    'tags|tags|name' => '###tags###'
);
```
The marker-array defines the data injection-points. This array tells the generator to inject "title->rendered" into "###title###". In other words you define which class-properties of stdClass are injected into which injection-point.

Now, sometimes it may happen that your property is just a unique ID. This is e.g. the case with the "author" property of endpoint "posts". 

In such a case you tell the generator which API endpoint that ID belongs to and what property you want to inject. You do this by using e.g. "author|users|name". It basically says: "The ID in author belongs to the endpoint 'users' and I want the name of endpoint 'users'". 

Technically it's another API-call.

```php
$generator->injectDataIntoTemplate('posts/', $markerArray);`
```

This is the final call to inject all defined properties of the given endpoint to the respective injection-points of your template. 
In this case I call "posts/". 

But this could really be any endpoint of the API.

### Handling URLs
The class takes care of changing the URLs generated by WordPress. All you need to do is to define which part of the URLs you want to change.
This happens by defining a **pattern array** and a **replace array**.

**Example**:
```php
$patternArray = array(
    '/secret-siunimtao.com/',
    '/wp-content\/uploads\//'
);
$replaceArray = array(
    'die-wordpress-agentur.de',
    '/assets/img'
);
$generator->setReplaceUrlParts($patternArray, $replaceArray);
```
You just tell the generator which parts of the URLs to change in what way. This happens by preg_replace(), so you can basically use any regular expression.

The generator currently changes URLs in href, src and srcset.

### Filenames
Depending on your configuration of WordPress, WordPress generates certain which may be made up of several data like date, author or category, e.g.  	http://localhost/wordpress/2022/08/examplepost/

By setting

```php
$generator->setKeepOriginalFilename(false);
```

You tell the generator to create a file in the root-folder with name slug.html

If you set

```php
$generator->setKeepOriginalFilename(true);
```

the generator keeps the original filename, creates the respective paths in the root-folder and puts the html-file as index.html into that folder.


