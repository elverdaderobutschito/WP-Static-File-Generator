# WP-Static-File-Generator
A class to grab WordPress-Posts and other data via the API and inject it into static files.

## WordPress Static Site Generator

**Use case**: You want to use WordPress for storing your content, but entirely want to keep control of the layout without the hassle of creating a WordPress-Plugin.

This tool uses the WordPress API and generates a static file with WordPress-Content based on your template.

Just add placeholders in the places where you want the content of a post to appear. This tool will automatically inject the respective content.

Add one or more of the following markers into your template.

| Marker | Respective Content |
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
