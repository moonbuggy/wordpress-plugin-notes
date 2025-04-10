# WordPress Plugin notes
A WordPress plugin to add notes to other pugins on the plugin administration
page.

This is an old plugin from [here](https://wordpress.org/plugins/plugin-notes/),
patched to work with WordPress 6.7.2 and PHP 8.0.30 (and hopefully newer). It
was easier to make this go that to switch to a newer plugin, when migrating an
old WordPres site to a newer server. So here it is..

The changes to [inc/markdown.php](inc/markdown.php) silence errors, largely by
setting default values for some parameters, but it no longer works. Having no
need for the markdown in my application, it's just disabled as a filter in the
plugin.

See the original plugin's [readme.txt](readme.txt) for more details.
