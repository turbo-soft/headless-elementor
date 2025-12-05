=== Headless Elementor ===
Contributors: miroslavpantos
Tags: elementor, headless, rest-api, decoupled, react
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display Elementor-designed content on any external frontend with a simple embed script. True plug-and-play headless Elementor.

== Description ==

Headless Elementor enables you to design content in WordPress using Elementor's visual editor, then display it on any external website (React, Vue, Next.js, vanilla HTML) with full styling and interactivity.

= How It Works =

1. Design your content in Elementor as usual
2. Add 3 lines of code to your frontend
3. Your content appears exactly as designed, with all widgets working

= Features =

* **Plug-and-Play** - Single embed.js script handles everything
* **Full Fidelity** - Content looks and works exactly like in WordPress
* **All Widgets Work** - Accordions, tabs, videos, sliders, and more
* **Elementor Pro Support** - Pro widgets work out of the box
* **Framework Agnostic** - Works with React, Vue, Next.js, or plain HTML
* **CORS Support** - Built-in cross-origin configuration

= Simple Frontend Integration =

`<div id="content"></div>
<script src="https://your-site.com/wp-content/plugins/headless-elementor/assets/js/embed.js"></script>
<script>
  HeadlessElementor.load('#content', 'https://your-site.com/wp-json/wp/v2/pages/123');
</script>`

= Requirements =

* WordPress 5.8 or higher
* PHP 7.4 or higher
* Elementor plugin (free version works, Pro supported)

== Installation ==

1. Upload the `headless-elementor` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Headless Elementor
4. Select which post types to enable
5. Add your frontend URL(s) to the CORS allowed origins
6. Add the embed code to your frontend application

== Frequently Asked Questions ==

= Does this work with Elementor Pro? =

Yes! Pro widgets work because the plugin loads Elementor's actual JavaScript files.

= Do I need jQuery on my frontend? =

No. The embed.js script automatically loads jQuery and all other required dependencies.

= Can I style the content differently on my frontend? =

Yes. Elementor's CSS loads first, but you can override it with your own styles.

= Does it work with React/Vue/Next.js? =

Yes. The embed script works anywhere JavaScript runs. See the documentation for framework-specific examples.

= Is SEO affected? =

Content is loaded via JavaScript, so for SEO you may want to implement server-side rendering or use a prerendering service.

= What about caching? =

The plugin works with standard WordPress caching. For the embed.js script, consider adding a version parameter for cache busting after updates.

== Screenshots ==

1. Settings page - Configure post types and CORS origins
2. Content designed in Elementor
3. Same content rendered on external React app

== Changelog ==

= 1.0.0 =
* Initial release
* REST API extension for Elementor data
* embed.js for plug-and-play frontend integration
* Full widget support via Elementor's frontend JavaScript
* CORS configuration
* Admin settings page

== Upgrade Notice ==

= 1.0.0 =
Initial release of Headless Elementor.
