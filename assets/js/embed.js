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
 * Usage (Client-Side Rendering):
 *   <div id="content"></div>
 *   <script src="https://your-site.com/wp-content/plugins/headless-elementor/assets/js/embed.js"></script>
 *   <script>
 *     HeadlessElementor.load('#content', 'https://your-site.com/wp-json/wp/v2/pages/123');
 *   </script>
 *
 * Usage (SSR Hydration - for Next.js, Nuxt, etc.):
 *   Server renders HTML + CSS, then client hydrates:
 *   <script>
 *     // elementorData fetched server-side and passed to client
 *     HeadlessElementor.hydrate('#content', { elementor_data: elementorData });
 *   </script>
 */

(function(global) {
  'use strict';

  const HeadlessElementor = {
    loadedStyles: new Set(),
    loadedScripts: new Set(),

    // Track page-specific resources for cleanup
    _pageStyles: [],        // Page-specific <link> elements
    _inlineStyles: [],      // Injected <style> elements
    _inlineCssHashes: new Set(),  // For deduplication

    /**
     * Load and render Elementor content (main entry point)
     * @param {string|Element} container - CSS selector or DOM element
     * @param {string} apiUrl - WordPress REST API URL for the page/post
     * @param {object} options - Optional settings
     */
    async load(container, apiUrl, options = {}) {
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

      // 1. Apply Kit wrapper class (for global styles to work)
      if (elementorData.kit?.id) {
        el.classList.add(`elementor-kit-${elementorData.kit.id}`);
      }

      // 2. Load Kit CSS (global colors, typography, theme styles)
      if (elementorData.kit?.cssUrl) {
        this._loadCSS(elementorData.kit.cssUrl);
      }
      if (elementorData.kit?.inlineCss) {
        this._injectCSS(elementorData.kit.inlineCss);
      }

      // 3. Load page CSS files
      if (elementorData.styleLinks) {
        elementorData.styleLinks.forEach(url => this._loadCSS(url));
      }

      // 4. Inject page inline CSS
      if (elementorData.inlineCss) {
        this._injectCSS(elementorData.inlineCss);
      }

      // 5. Setup Elementor config objects (must be before loading scripts)
      if (elementorData.config) {
        window.elementorFrontendConfig = elementorData.config;
      }
      if (elementorData.proConfig) {
        window.ElementorProFrontendConfig = elementorData.proConfig;
      }

      // 6. Render HTML content FIRST (before scripts, so elements exist)
      let html = '';
      if (options.showTitle && data.title?.rendered) {
        const titleTag = options.titleTag || 'h1';
        html += `<${titleTag} class="elementor-page-title">${data.title.rendered}</${titleTag}>`;
      }
      html += data.content.rendered;
      el.innerHTML = html;

      // 7. Load JavaScript files (foundation scripts in parallel, then rest sequentially)
      if (elementorData.scripts && elementorData.scripts.length > 0) {
        await this._loadScripts(elementorData.scripts);
      }

      // 8. Initialize Elementor frontend
      await this._initElementor(el);
    },

    /**
     * Hydrate server-rendered content (for SSR/SEO)
     *
     * Use this when HTML and CSS are already rendered server-side.
     * This method only loads JavaScript and initializes widget interactivity.
     *
     * @param {string|Element} container - CSS selector or DOM element with pre-rendered content
     * @param {object} data - Page/post data from REST API (must include elementor_data)
     * @param {object} options - Optional settings
     * @param {boolean} options.loadCss - Also load CSS (default: false, assumes server handled it)
     *
     * @example
     * // Server-side (Next.js getServerSideProps):
     * const res = await fetch('https://wp-site.com/wp-json/wp/v2/pages/123');
     * const pageData = await res.json();
     * // Render pageData.content.rendered as HTML
     * // Include pageData.elementor_data.styleLinks as <link> tags
     * // Include pageData.elementor_data.inlineCss in a <style> tag
     *
     * // Client-side:
     * HeadlessElementor.hydrate('#content', pageData);
     */
    async hydrate(container, data, options = {}) {
      const el = typeof container === 'string' ? document.querySelector(container) : container;

      if (!el) {
        console.error('HeadlessElementor: Container not found');
        return;
      }

      const elementorData = data.elementor_data;

      if (!elementorData) {
        console.warn('HeadlessElementor: No elementor_data found, nothing to hydrate');
        return;
      }

      // 1. Apply Kit wrapper class if not already present
      if (elementorData.kit?.id) {
        const kitClass = `elementor-kit-${elementorData.kit.id}`;
        if (!el.classList.contains(kitClass)) {
          el.classList.add(kitClass);
        }
      }

      // 2. Optionally load CSS (if server didn't handle it)
      if (options.loadCss) {
        if (elementorData.kit?.cssUrl) {
          this._loadCSS(elementorData.kit.cssUrl);
        }
        if (elementorData.kit?.inlineCss) {
          this._injectCSS(elementorData.kit.inlineCss);
        }
        if (elementorData.styleLinks) {
          elementorData.styleLinks.forEach(url => this._loadCSS(url));
        }
        if (elementorData.inlineCss) {
          this._injectCSS(elementorData.inlineCss);
        }
      }

      // 3. Setup Elementor config objects (must be before loading scripts)
      if (elementorData.config) {
        window.elementorFrontendConfig = elementorData.config;
      }
      if (elementorData.proConfig) {
        window.ElementorProFrontendConfig = elementorData.proConfig;
      }

      // 4. Load JavaScript files
      if (elementorData.scripts && elementorData.scripts.length > 0) {
        await this._loadScripts(elementorData.scripts);
      }

      // 5. Initialize Elementor frontend (makes widgets interactive)
      await this._initElementor(el);
    },

    /**
     * Clean up page-specific resources (for SPA navigation)
     * Call this before loading new content or when unmounting
     * @param {string|Element} container - CSS selector or DOM element (optional)
     */
    destroy(container) {
      // Remove page-specific external stylesheets
      this._pageStyles.forEach(link => {
        link.remove();
        this.loadedStyles.delete(link.href);
      });
      this._pageStyles = [];

      // Remove injected inline styles
      this._inlineStyles.forEach(style => style.remove());
      this._inlineStyles = [];
      this._inlineCssHashes.clear();

      // Clear container if provided
      if (container) {
        const el = typeof container === 'string' ? document.querySelector(container) : container;
        if (el) {
          // Remove kit wrapper class (matches elementor-kit-{id})
          el.classList.forEach(cls => {
            if (cls.startsWith('elementor-kit-')) {
              el.classList.remove(cls);
            }
          });
          el.innerHTML = '';
        }
      }
    },

    /**
     * djb2 hash - creates a short fingerprint from string content
     * Used to deduplicate inline CSS without storing full content
     */
    _hashString(str) {
      let hash = 5381;
      for (let i = 0; i < str.length; i++) {
        hash = ((hash << 5) + hash) + str.charCodeAt(i);
      }
      return (hash >>> 0).toString(36);
    },

    /**
     * Check if a CSS URL is page-specific (vs shared Elementor core)
     */
    _isPageSpecificCSS(url) {
      return /\/elementor\/css\/post-\d+\.css/.test(url) ||
             /\/uploads\/.*\/elementor\/css\//.test(url);
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

      // Track page-specific CSS for cleanup
      if (this._isPageSpecificCSS(url)) {
        this._pageStyles.push(link);
      }
    },

    /**
     * Inject inline CSS (with deduplication)
     */
    _injectCSS(css) {
      const hash = this._hashString(css);
      if (this._inlineCssHashes.has(hash)) return;

      const style = document.createElement('style');
      style.textContent = css;
      document.head.appendChild(style);

      this._inlineCssHashes.add(hash);
      this._inlineStyles.push(style);
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
     * Check if a script URL is a "foundation" script with no Elementor dependencies.
     * These can be loaded in parallel before dependent scripts.
     *
     * Only matches:
     * - jQuery core: jquery.min.js or jquery-3.x.x.min.js (NOT jquery-migrate, jquery-ui, etc.)
     * - Webpack runtime: webpack.runtime.min.js or webpack-runtime.min.js
     */
    _isFoundationScript(url) {
      // Match only jQuery core (ends with jquery.min.js or jquery-version.min.js)
      const isJQueryCore = /\/jquery(?:-\d+\.\d+\.\d+)?\.min\.js$/.test(url);
      // Match webpack runtime (both dot and hyphen variants)
      const isWebpackRuntime = /webpack[.-]runtime/.test(url);

      return isJQueryCore || isWebpackRuntime;
    },

    /**
     * Load scripts with parallel optimization.
     * Foundation scripts (jQuery, webpack-runtime) load in parallel,
     * then dependent scripts load sequentially.
     */
    async _loadScripts(urls) {
      if (!urls || urls.length === 0) return;

      // Separate foundation scripts from dependent scripts
      const foundation = [];
      const dependent = [];

      for (const url of urls) {
        if (this._isFoundationScript(url)) {
          foundation.push(url);
        } else {
          dependent.push(url);
        }
      }

      // Load foundation scripts in parallel
      if (foundation.length > 0) {
        await Promise.all(foundation.map(url => this._loadScript(url)));
      }

      // Load dependent scripts sequentially (order matters)
      for (const url of dependent) {
        await this._loadScript(url);
      }
    },

    /**
     * Wait for elementorFrontend to be ready
     * @param {number} maxWait - Maximum time to wait in ms
     * @param {number} interval - Polling interval in ms
     * @returns {Promise}
     */
    _waitForElementor(maxWait = 5000, interval = 50) {
      return new Promise((resolve, reject) => {
        // Check immediately first
        if (window.elementorFrontend?.init) {
          resolve();
          return;
        }

        const startTime = Date.now();
        const check = () => {
          if (window.elementorFrontend?.init) {
            resolve();
          } else if (Date.now() - startTime > maxWait) {
            reject(new Error('Timed out waiting for elementorFrontend'));
          } else {
            setTimeout(check, interval);
          }
        };
        check();
      });
    },

    /**
     * Initialize Elementor frontend
     */
    async _initElementor(container) {
      try {
        await this._waitForElementor();

        if (typeof elementorFrontend.init === 'function') {
          try {
            elementorFrontend.init();
          } catch (e) {
            // Already initialized, continue to trigger handlers
          }
        }

        // Trigger element handlers on the container
        if (window.jQuery && elementorFrontend.elementsHandler) {
          const $container = jQuery(container);
          $container.find('[data-element_type]').each(function() {
            elementorFrontend.elementsHandler.runReadyTrigger(jQuery(this));
          });
        }
      } catch (e) {
        console.error('HeadlessElementor:', e.message);
      }
    }
  };

  // Expose globally
  global.HeadlessElementor = HeadlessElementor;

})(typeof window !== 'undefined' ? window : this);
