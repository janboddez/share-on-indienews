# Share on IndieNews
Automatically submit WordPress posts to [IndieNews](https://news.indieweb.org/).

In your theme (or elsewhere), use `get_post_meta( $post->ID, '_share_on_indienews_url', true )` to retrieve the resulting syndication URL.

There's no options page, but you can change the language-specific endpoint using the `share_on_indienews_default_url` filter, e.g.:
```
add_filter( 'share_on_indienews_default_url' , function( $default_url ) {
  return 'https://news.indieweb.org/de';
} );
```
If you want to display the sharing option for post types other than `post` and `page`, there's `share_on_indienews_post_types`:
```
add_filter( 'share_on_indienews_post_types' , function( $post_types ) {
  $post_types[] = 'my_custom_post_type_slug';

  return $post_types;
} );
```
