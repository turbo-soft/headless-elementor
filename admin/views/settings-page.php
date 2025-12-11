<?php
/**
 * Settings page template.
 *
 * @package Headless_Elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php settings_errors(); ?>

    <form action="options.php" method="post">
        <?php
        settings_fields( 'headless_elementor_settings_group' );
        do_settings_sections( 'headless-elementor' );
        submit_button( __( 'Save Settings', 'headless-elementor' ) );
        ?>
    </form>

    <hr>

    <h2><?php esc_html_e( 'Usage Guide', 'headless-elementor' ); ?></h2>

    <p><?php esc_html_e( 'Once configured, the plugin adds an elementor_data field to your REST API responses. Access it via:', 'headless-elementor' ); ?></p>

    <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;"><code>GET /wp-json/wp/v2/posts/{id}
GET /wp-json/wp/v2/pages/{id}</code></pre>

    <h3><?php esc_html_e( 'Response Structure', 'headless-elementor' ); ?></h3>

    <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;"><code>{
  "id": 123,
  "title": { "rendered": "My Page" },
  "content": { "rendered": "&lt;div class=\"elementor...\"&gt;" },
  "elementor_data": {
    "isElementor": true,
    "styleLinks": ["https://...frontend.min.css"],
    "inlineCss": ".elementor-123 {...}",
    "scripts": ["https://...frontend.min.js"],
    "config": { "environmentMode": {...}, "kit": {...}, ... },
    "proConfig": { "ajaxurl": "...", "nonce": "...", ... }
  }
}</code></pre>

    <h3><?php esc_html_e( 'Frontend Integration', 'headless-elementor' ); ?></h3>

    <ol>
        <li><?php esc_html_e( 'Fetch the post/page via REST API', 'headless-elementor' ); ?></li>
        <li><?php esc_html_e( 'Inject styleLinks as <link> tags in <head>', 'headless-elementor' ); ?></li>
        <li><?php esc_html_e( 'Inject inlineCss as a <style> tag', 'headless-elementor' ); ?></li>
        <li><?php esc_html_e( 'Set window.elementorFrontendConfig and window.ElementorProFrontendConfig', 'headless-elementor' ); ?></li>
        <li><?php esc_html_e( 'Render content.rendered in your DOM', 'headless-elementor' ); ?></li>
        <li><?php esc_html_e( 'Load scripts in order', 'headless-elementor' ); ?></li>
        <li><?php esc_html_e( 'Initialize Elementor: elementorFrontend.init()', 'headless-elementor' ); ?></li>
    </ol>

    <h3><?php esc_html_e( 'Easy Integration (Recommended)', 'headless-elementor' ); ?></h3>

    <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;"><code>&lt;div id="content"&gt;&lt;/div&gt;
&lt;script src="<?php echo esc_url( HEADLESS_ELEMENTOR_URL . 'assets/js/embed.js' ); ?>"&gt;&lt;/script&gt;
&lt;script&gt;
  HeadlessElementor.load('#content', '<?php echo esc_url( rest_url( 'wp/v2/pages/123' ) ); ?>');
&lt;/script&gt;</code></pre>

    <h3><?php esc_html_e( 'Manual Integration (Advanced)', 'headless-elementor' ); ?></h3>

    <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;"><code>async function renderElementorPage(postId) {
  const response = await fetch(`/wp-json/wp/v2/pages/${postId}`);
  const data = await response.json();

  if (!data.elementor_data?.isElementor) return;

  // Load CSS
  data.elementor_data.styleLinks.forEach(href => {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
  });

  // Add inline CSS
  const style = document.createElement('style');
  style.textContent = data.elementor_data.inlineCss;
  document.head.appendChild(style);

  // Set up configs (must be before scripts load)
  window.elementorFrontendConfig = data.elementor_data.config;
  if (data.elementor_data.proConfig) {
    window.ElementorProFrontendConfig = data.elementor_data.proConfig;
  }

  // Render content
  document.getElementById('content').innerHTML = data.content.rendered;

  // Load scripts sequentially
  for (const src of data.elementor_data.scripts) {
    await new Promise(resolve => {
      const script = document.createElement('script');
      script.src = src;
      script.onload = resolve;
      document.body.appendChild(script);
    });
  }

  // Initialize Elementor
  if (window.elementorFrontend) {
    window.elementorFrontend.init();
  }
}</code></pre>

</div>
