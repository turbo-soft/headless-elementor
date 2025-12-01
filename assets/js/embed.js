/**
 * Headless Elementor - Simple Embed Script
 *
 * Usage:
 *   <div id="content"></div>
 *   <script src="https://your-site.com/.../embed.js"></script>
 *   <script>
 *     HeadlessElementor.load('#content', 'https://your-site.com/wp-json/wp/v2/pages/123');
 *   </script>
 */

(function(global) {
  'use strict';

  const HeadlessElementor = {
    loadedStyles: new Set(),

    /**
     * Load and render Elementor content
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

      el.innerHTML = '<div style="text-align:center;padding:40px;color:#666;">Loading...</div>';

      try {
        const response = await fetch(apiUrl);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        this.render(el, data, options);
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
     *   - showTitle: boolean (default: false) - Show page title
     *   - titleTag: string (default: 'h1') - HTML tag for title
     */
    render(container, data, options = {}) {
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

      // Load Elementor CSS
      if (elementorData.styleLinks) {
        elementorData.styleLinks.forEach(url => this._loadCSS(url));
      }

      // Inject inline CSS
      if (elementorData.inlineCss) {
        this._injectCSS(elementorData.inlineCss);
      }

      // Build HTML
      let html = '';

      // Add title if requested
      if (options.showTitle && data.title?.rendered) {
        const titleTag = options.titleTag || 'h1';
        html += `<${titleTag} class="elementor-page-title">${data.title.rendered}</${titleTag}>`;
      }

      // Add content
      html += data.content.rendered;

      el.innerHTML = html;

      // Initialize interactive widgets
      this._initWidgets(el);
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
     * Initialize interactive widgets
     */
    _initWidgets(container) {
      // Video widgets
      container.querySelectorAll('[data-widget_type="video.default"]').forEach(widget => {
        this._initVideo(widget);
      });

      // Tabs widgets
      container.querySelectorAll('.elementor-widget-tabs').forEach(widget => {
        this._initTabs(widget);
      });

      // Accordion widgets
      container.querySelectorAll('.elementor-widget-accordion').forEach(widget => {
        this._initAccordion(widget);
      });

      // Toggle widgets
      container.querySelectorAll('.elementor-widget-toggle').forEach(widget => {
        this._initToggle(widget);
      });

      // Alert close buttons
      container.querySelectorAll('.elementor-alert-dismiss').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('.elementor-alert').remove());
      });

      // Image lightbox (basic - opens in new tab)
      container.querySelectorAll('a[data-elementor-lightbox]').forEach(link => {
        link.addEventListener('click', e => {
          e.preventDefault();
          window.open(link.href, '_blank');
        });
      });
    },

    /**
     * Initialize video widget
     */
    _initVideo(widget) {
      const settings = this._getSettings(widget);
      if (!settings) return;

      const videoContainer = widget.querySelector('.elementor-video');
      if (!videoContainer || videoContainer.children.length > 0) return;

      let iframe = null;

      if (settings.video_type === 'youtube' && settings.youtube_url) {
        const videoId = this._extractYouTubeId(settings.youtube_url);
        if (videoId) {
          const params = new URLSearchParams();
          if (settings.autoplay === 'yes') params.set('autoplay', '1');
          if (settings.mute === 'yes') params.set('mute', '1');
          if (settings.loop === 'yes') { params.set('loop', '1'); params.set('playlist', videoId); }
          if (settings.controls !== 'yes') params.set('controls', '0');
          if (settings.rel !== 'yes') params.set('rel', '0');
          if (settings.modestbranding === 'yes') params.set('modestbranding', '1');
          if (settings.start) params.set('start', settings.start);
          if (settings.end) params.set('end', settings.end);

          iframe = document.createElement('iframe');
          iframe.src = `https://www.youtube.com/embed/${videoId}?${params}`;
          iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
          iframe.allowFullscreen = true;
        }
      } else if (settings.video_type === 'vimeo' && settings.vimeo_url) {
        const videoId = this._extractVimeoId(settings.vimeo_url);
        if (videoId) {
          const params = new URLSearchParams();
          if (settings.autoplay === 'yes') params.set('autoplay', '1');
          if (settings.mute === 'yes') params.set('muted', '1');
          if (settings.loop === 'yes') params.set('loop', '1');

          iframe = document.createElement('iframe');
          iframe.src = `https://player.vimeo.com/video/${videoId}?${params}`;
          iframe.allow = 'autoplay; fullscreen; picture-in-picture';
          iframe.allowFullscreen = true;
        }
      } else if (settings.video_type === 'dailymotion' && settings.dailymotion_url) {
        const videoId = this._extractDailymotionId(settings.dailymotion_url);
        if (videoId) {
          iframe = document.createElement('iframe');
          iframe.src = `https://www.dailymotion.com/embed/video/${videoId}`;
          iframe.allow = 'autoplay; fullscreen';
          iframe.allowFullscreen = true;
        }
      }

      if (iframe) {
        iframe.frameBorder = '0';
        iframe.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;';

        // Create aspect ratio wrapper
        const wrapper = videoContainer.closest('.elementor-wrapper');
        if (wrapper) {
          wrapper.style.cssText = 'position:relative;padding-bottom:56.25%;height:0;overflow:hidden;';
        }

        videoContainer.appendChild(iframe);
      }
    },

    /**
     * Initialize tabs widget
     */
    _initTabs(widget) {
      const tabs = widget.querySelectorAll('.elementor-tab-title');
      const contents = widget.querySelectorAll('.elementor-tab-content');

      tabs.forEach(tab => {
        tab.addEventListener('click', () => {
          const tabId = tab.getAttribute('data-tab');

          tabs.forEach(t => t.classList.remove('elementor-active'));
          contents.forEach(c => c.classList.remove('elementor-active'));

          tab.classList.add('elementor-active');
          const content = widget.querySelector(`.elementor-tab-content[data-tab="${tabId}"]`);
          if (content) content.classList.add('elementor-active');
        });
      });
    },

    /**
     * Initialize accordion widget
     */
    _initAccordion(widget) {
      const items = widget.querySelectorAll('.elementor-accordion-item');

      items.forEach(item => {
        const title = item.querySelector('.elementor-accordion-title');
        const content = item.querySelector('.elementor-tab-content');

        if (title && content) {
          title.addEventListener('click', () => {
            const isActive = item.classList.contains('elementor-active');

            // Close all
            items.forEach(i => {
              i.classList.remove('elementor-active');
              const c = i.querySelector('.elementor-tab-content');
              if (c) c.style.display = 'none';
            });

            // Open clicked if it wasn't active
            if (!isActive) {
              item.classList.add('elementor-active');
              content.style.display = 'block';
            }
          });
        }
      });
    },

    /**
     * Initialize toggle widget
     */
    _initToggle(widget) {
      const items = widget.querySelectorAll('.elementor-toggle-item');

      items.forEach(item => {
        const title = item.querySelector('.elementor-toggle-title');
        const content = item.querySelector('.elementor-tab-content');

        if (title && content) {
          title.addEventListener('click', () => {
            const isActive = item.classList.contains('elementor-active');
            item.classList.toggle('elementor-active');
            content.style.display = isActive ? 'none' : 'block';
          });
        }
      });
    },

    /**
     * Get widget settings from data attribute
     */
    _getSettings(widget) {
      const settingsAttr = widget.getAttribute('data-settings');
      if (!settingsAttr) return null;

      try {
        return JSON.parse(settingsAttr);
      } catch (e) {
        return null;
      }
    },

    /**
     * Extract YouTube video ID
     */
    _extractYouTubeId(url) {
      const match = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
      return match ? match[1] : null;
    },

    /**
     * Extract Vimeo video ID
     */
    _extractVimeoId(url) {
      const match = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
      return match ? match[1] : null;
    },

    /**
     * Extract Dailymotion video ID
     */
    _extractDailymotionId(url) {
      const match = url.match(/dailymotion\.com\/(?:video|embed\/video)\/([a-zA-Z0-9]+)/);
      return match ? match[1] : null;
    }
  };

  // Expose globally
  global.HeadlessElementor = HeadlessElementor;

})(typeof window !== 'undefined' ? window : this);
