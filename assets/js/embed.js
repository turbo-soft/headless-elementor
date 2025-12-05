/**
 * Headless Elementor - Embed Script
 *
 * Plug-and-play script that handles everything:
 * - Fetches page data from WordPress REST API
 * - Loads all required CSS files
 * - Loads all required JavaScript files
 * - Sets up Elementor configuration
 * - Renders content and initializes Elementor
 *
 * Usage:
 *   <div id="content"></div>
 *   <script src="https://your-site.com/wp-content/plugins/headless-elementor/assets/js/embed.js"></script>
 *   <script>
 *     HeadlessElementor.load('#content', 'https://your-site.com/wp-json/wp/v2/pages/123');
 *   </script>
 */

(function(global) {
  'use strict';

  console.log('HeadlessElementor: embed.js loaded v2');

  const HeadlessElementor = {
    loadedStyles: new Set(),
    loadedScripts: new Set(),

    /**
     * Load and render Elementor content (main entry point)
     * @param {string|Element} container - CSS selector or DOM element
     * @param {string} apiUrl - WordPress REST API URL for the page/post
     * @param {object} options - Optional settings
     */
    async load(container, apiUrl, options = {}) {
      console.log('HeadlessElementor: load() called', { container, apiUrl });
      const el = typeof container === 'string' ? document.querySelector(container) : container;

      if (!el) {
        console.error('HeadlessElementor: Container not found:', container);
        return;
      }

      // Show loading state
      el.innerHTML = '<div style="text-align:center;padding:40px;color:#666;">Loading...</div>';

      try {
        // Fetch page data
        const response = await fetch(apiUrl);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();

        // Render the content
        await this.render(el, data, options);
      } catch (error) {
        el.innerHTML = `<div style="text-align:center;padding:40px;color:#c00;">Error: ${error.message}</div>`;
        console.error('HeadlessElementor:', error);
      }
    },

    /**
     * Render from already-fetched data
     * @param {string|Element} container - CSS selector or DOM element
     * @param {object} data - Page/post data from REST API
     * @param {object} options - Optional settings
     */
    async render(container, data, options = {}) {
      const el = typeof container === 'string' ? document.querySelector(container) : container;

      if (!el) {
        console.error('HeadlessElementor: Container not found');
        return;
      }

      const elementorData = data.elementor_data;

      // Not an Elementor page - show regular content
      if (!elementorData || !elementorData.isElementor) {
        el.innerHTML = data.content?.rendered || '<p>No content available.</p>';
        return;
      }

      // 1. Load CSS files
      if (elementorData.styleLinks) {
        elementorData.styleLinks.forEach(url => this._loadCSS(url));
      }

      // 2. Inject inline CSS
      if (elementorData.inlineCss) {
        this._injectCSS(elementorData.inlineCss);
      }

      // 3. Setup Elementor config objects (must be before loading scripts)
      if (elementorData.config) {
        window.elementorFrontendConfig = elementorData.config;
      }
      if (elementorData.proConfig) {
        window.ElementorProFrontendConfig = elementorData.proConfig;
      }

      // 4. Render HTML content FIRST (before scripts, so elements exist)
      let html = '';
      if (options.showTitle && data.title?.rendered) {
        const titleTag = options.titleTag || 'h1';
        html += `<${titleTag} class="elementor-page-title">${data.title.rendered}</${titleTag}>`;
      }
      html += data.content.rendered;
      el.innerHTML = html;

      // 5. Load JavaScript files sequentially (order matters for dependencies)
      console.log('HeadlessElementor: Scripts to load:', elementorData.scripts);
      if (elementorData.scripts && elementorData.scripts.length > 0) {
        for (const url of elementorData.scripts) {
          console.log('HeadlessElementor: Loading script:', url);
          try {
            await this._loadScript(url);
            console.log('HeadlessElementor: Loaded:', url);
          } catch (e) {
            console.error('HeadlessElementor: Failed to load:', url, e);
          }
        }
      }
      console.log('HeadlessElementor: All scripts loaded, jQuery:', typeof jQuery, 'elementorFrontend:', typeof elementorFrontend);

      // 6. Initialize Elementor frontend
      this._initElementor(el);
    },

    /**
     * Load a CSS file
     */
    _loadCSS(url) {
      if (this.loadedStyles.has(url)) return;

      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = url;
      document.head.appendChild(link);
      this.loadedStyles.add(url);
    },

    /**
     * Inject inline CSS
     */
    _injectCSS(css) {
      const style = document.createElement('style');
      style.textContent = css;
      document.head.appendChild(style);
    },

    /**
     * Load a JavaScript file (returns promise)
     */
    _loadScript(url) {
      return new Promise((resolve, reject) => {
        if (this.loadedScripts.has(url)) {
          resolve();
          return;
        }

        const script = document.createElement('script');
        script.src = url;
        script.onload = () => {
          this.loadedScripts.add(url);
          resolve();
        };
        script.onerror = () => reject(new Error(`Failed to load script: ${url}`));
        document.head.appendChild(script);
      });
    },

    /**
     * Initialize Elementor frontend
     */
    _initElementor(container) {
      // Wait for scripts to fully initialize
      setTimeout(() => {
        if (window.elementorFrontend) {
          // Elementor frontend loaded
          if (typeof elementorFrontend.init === 'function') {
            try {
              elementorFrontend.init();
              console.log('HeadlessElementor: Elementor initialized');
            } catch (e) {
              console.log('HeadlessElementor: Elementor already initialized, triggering handlers');
            }
          }

          // Trigger element handlers on the container
          if (window.jQuery && elementorFrontend.elementsHandler) {
            const $container = jQuery(container);
            $container.find('[data-element_type]').each(function() {
              elementorFrontend.elementsHandler.runReadyTrigger(jQuery(this));
            });
          }
        } else {
          console.warn('HeadlessElementor: elementorFrontend not found');
        }
      }, 200);
    }
  };

  // Expose globally
  global.HeadlessElementor = HeadlessElementor;

})(typeof window !== 'undefined' ? window : this);
