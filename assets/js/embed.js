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

      // 7. Load JavaScript files sequentially (order matters for dependencies)
      if (elementorData.scripts && elementorData.scripts.length > 0) {
        for (const url of elementorData.scripts) {
          await this._loadScript(url);
        }
      }

      // 8. Initialize Elementor frontend
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
