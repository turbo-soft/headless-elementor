=== Headless Elementor ===
Contributors: miroslavpantos
Tags: elementor, headless, rest-api, decoupled, jamstack
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

REST API extension for headless Elementor content delivery. Render Elementor pages on any frontend.

== Description ==

Headless Elementor extends the WordPress REST API to include all the CSS, JavaScript, and configuration data needed to render Elementor-built pages on external frontends like React, Vue, Next.js, or any other framework.

= Key Features =

* **Full Asset Export** - Automatically collects all CSS and JavaScript files required by Elementor
* **Inline CSS Included** - Post-specific inline styles are exported for pixel-perfect rendering
* **Widget Support** - Detects used widgets and includes their script dependencies
* **Elementor Pro Compatible** - Full support for Elementor Pro features and configurations
* **CORS Configuration** - Built-in cross-origin settings for secure API access
* **Flexible Output** - Choose between script tags or raw JSON format

= Use Cases =

* Headless WordPress with React/Vue/Angular frontend
* JAMstack architecture with Elementor as CMS
* Mobile apps fetching content from WordPress
* Static site generators (Next.js, Gatsby, Nuxt)
* Multi-site content syndication

= How It Works =

1. Create your pages using Elementor in WordPress
2. Fetch the page via REST API: `GET /wp-json/wp/v2/pages/{id}`
3. The response includes an `elementor_data` field with all assets
4. Load the CSS, inject the configs, render the HTML
5. Load the scripts and initialize Elementor frontend

= Requirements =

* WordPress 5.8 or higher
* PHP 7.4 or higher
* Elementor (free version)
* Elementor Pro (optional, for Pro features)

== Installation ==

1. Upload the `headless-elementor` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Headless Elementor to configure
4. Select which post types should include Elementor data
5. Configure CORS origins if needed

== Frequently Asked Questions ==

= Does this work with Elementor Free? =

Yes! The plugin works with both Elementor Free and Elementor Pro. Pro-specific features will only be included when Elementor Pro is active.

= What post types are supported? =

Any post type that is public and exposed to the REST API can be enabled. This includes posts, pages, and custom post types.

= How do I handle CORS? =

Go to Settings > Headless Elementor and add your frontend domain(s) to the Allowed Origins field. Use `*` to allow all origins (not recommended for production).

= Can I use this with Next.js/Nuxt/Gatsby? =

Absolutely! The plugin provides all the data needed for server-side rendering. Use the "Raw JSON" output format for easier processing in SSR scenarios.

= Do I need to modify my Elementor pages? =

No. The plugin works with existing Elementor pages without any modifications.

= Are third-party Elementor addons supported? =

Yes, the plugin automatically detects widgets from third-party addons and includes their script dependencies.

== Screenshots ==

1. Settings page - Configure post types and CORS
2. REST API response with elementor_data field
3. Frontend rendering example

== Changelog ==

= 1.0.0 =
* Initial release
* REST API field registration for posts and pages
* CSS collection (external files and inline)
* JavaScript collection with dependency resolution
* Elementor and Elementor Pro configuration export
* Admin settings page
* CORS configuration
* Output format selection (script tags or JSON)

== Upgrade Notice ==

= 1.0.0 =
Initial release of Headless Elementor.

== Developer Documentation ==

= REST API Response Structure =

The `elementor_data` field contains:

* `isElementor` (boolean) - Whether the post is built with Elementor
* `styleLinks` (array) - URLs of CSS files to load
* `inlineCss` (string) - Post-specific inline CSS
* `scripts` (array) - URLs of JavaScript files to load (in order)
* `config` (string|object) - Elementor frontend configuration
* `proConfig` (string|object|null) - Elementor Pro configuration (if Pro is active)

= Frontend Integration Example =

`
async function loadElementorPage(postId) {
  const res = await fetch(\`/wp-json/wp/v2/pages/\${postId}\`);
  const data = await res.json();

  if (!data.elementor_data?.isElementor) return;

  // 1. Load CSS files
  data.elementor_data.styleLinks.forEach(href => {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
  });

  // 2. Add inline CSS
  const style = document.createElement('style');
  style.textContent = data.elementor_data.inlineCss;
  document.head.appendChild(style);

  // 3. Inject configs
  document.head.insertAdjacentHTML('beforeend', data.elementor_data.config);
  if (data.elementor_data.proConfig) {
    document.head.insertAdjacentHTML('beforeend', data.elementor_data.proConfig);
  }

  // 4. Render content
  document.getElementById('content').innerHTML = data.content.rendered;

  // 5. Load scripts in order
  for (const src of data.elementor_data.scripts) {
    await new Promise(resolve => {
      const script = document.createElement('script');
      script.src = src;
      script.onload = resolve;
      document.body.appendChild(script);
    });
  }

  // 6. Initialize Elementor
  if (window.elementorFrontend) {
    window.elementorFrontend.init();
  }
}
`

= Filters =

The plugin respects Elementor's built-in filters:

* `elementor_pro/frontend/assets_url` - Filter Pro assets URL
* `elementor_pro/frontend/localize_settings` - Filter Pro frontend settings

= Support =

For bug reports and feature requests, please use the GitHub repository:
https://github.com/your-username/headless-elementor
