/**
 * Headless Elementor Renderer
 *
 * A framework-agnostic JavaScript library for rendering Elementor content
 * from the REST API on any frontend.
 *
 * Usage:
 *   // Option 1: Auto-render (simplest)
 *   HeadlessElementor.render('#container', 'https://your-site.com/wp-json/wp/v2/pages/123');
 *
 *   // Option 2: Render from data
 *   HeadlessElementor.renderFromData('#container', elementorData);
 *
 *   // Option 3: Get HTML string (for SSR/custom rendering)
 *   const html = HeadlessElementor.toHTML(elementorData.widgets);
 */

(function (global, factory) {
  typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
  typeof define === 'function' && define.amd ? define(factory) :
  (global = global || self, global.HeadlessElementor = factory());
}(this, function () {
  'use strict';

  /**
   * Main renderer class
   */
  class ElementorRenderer {
    constructor(options = {}) {
      this.options = {
        loadStyles: true,
        loadScripts: false, // Disabled by default, we use native rendering
        aspectRatio: '16/9',
        ...options
      };

      this.stylesLoaded = new Set();
      this.widgetRenderers = this.getDefaultRenderers();
    }

    /**
     * Fetch and render Elementor content from a REST API URL
     */
    async render(container, url, options = {}) {
      const targetEl = typeof container === 'string'
        ? document.querySelector(container)
        : container;

      if (!targetEl) {
        throw new Error(`Container not found: ${container}`);
      }

      try {
        targetEl.innerHTML = '<div class="he-loading">Loading...</div>';

        const response = await fetch(url);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        const elementorData = data.elementor_data || data;

        return this.renderFromData(targetEl, elementorData, options);
      } catch (error) {
        targetEl.innerHTML = `<div class="he-error">Error loading content: ${error.message}</div>`;
        throw error;
      }
    }

    /**
     * Render from pre-fetched elementor_data
     */
    renderFromData(container, elementorData, options = {}) {
      const targetEl = typeof container === 'string'
        ? document.querySelector(container)
        : container;

      if (!targetEl) {
        throw new Error(`Container not found: ${container}`);
      }

      const opts = { ...this.options, ...options };

      if (!elementorData.isElementor) {
        targetEl.innerHTML = '<div class="he-notice">This content was not built with Elementor.</div>';
        return;
      }

      // Load styles
      if (opts.loadStyles) {
        this.loadStyles(elementorData.styleLinks || []);
        if (elementorData.inlineCss) {
          this.injectInlineCSS(elementorData.inlineCss);
        }
      }

      // Render widgets
      const html = this.toHTML(elementorData.widgets || []);
      targetEl.innerHTML = html;

      // Initialize interactive widgets
      this.initializeWidgets(targetEl);

      return targetEl;
    }

    /**
     * Convert widgets array to HTML string
     */
    toHTML(widgets) {
      return widgets.map(widget => this.renderWidget(widget)).join('');
    }

    /**
     * Render a single widget/element
     */
    renderWidget(element) {
      const { type, widgetType, id, children, data, settings } = element;

      // Container/section elements
      if (type === 'container' || type === 'section' || type === 'column') {
        const childrenHtml = children ? children.map(c => this.renderWidget(c)).join('') : '';
        return `<div class="he-${type}" data-he-id="${id}">${childrenHtml}</div>`;
      }

      // Widget elements
      if (type === 'widget' && widgetType) {
        const renderer = this.widgetRenderers[widgetType] || this.widgetRenderers.default;
        return `<div class="he-widget he-widget-${widgetType}" data-he-id="${id}" data-widget-type="${widgetType}">
          ${renderer.call(this, data || {}, element)}
        </div>`;
      }

      return '';
    }

    /**
     * Get default widget renderers
     */
    getDefaultRenderers() {
      return {
        // Heading
        'heading': (data) => {
          const tag = data.tag || 'h2';
          const title = this.escapeHtml(data.title || '');
          const link = data.link;

          if (link && link.url) {
            const attrs = this.getLinkAttributes(link);
            return `<${tag} class="he-heading"><a ${attrs}>${title}</a></${tag}>`;
          }
          return `<${tag} class="he-heading">${title}</${tag}>`;
        },

        // Text editor
        'text-editor': (data) => {
          return `<div class="he-text-editor">${data.content || ''}</div>`;
        },

        // Video
        'video': (data) => {
          const aspectRatio = this.getAspectRatio(data.aspectRatio);

          switch (data.videoType) {
            case 'youtube':
              if (data.videoId) {
                const params = this.getYouTubeParams(data);
                return `<div class="he-video-wrapper" style="aspect-ratio: ${aspectRatio}">
                  <iframe
                    src="https://www.youtube.com/embed/${data.videoId}?${params}"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                    style="width: 100%; height: 100%;"
                  ></iframe>
                </div>`;
              }
              break;

            case 'vimeo':
              if (data.videoId) {
                const params = this.getVimeoParams(data);
                return `<div class="he-video-wrapper" style="aspect-ratio: ${aspectRatio}">
                  <iframe
                    src="https://player.vimeo.com/video/${data.videoId}?${params}"
                    frameborder="0"
                    allow="autoplay; fullscreen; picture-in-picture"
                    allowfullscreen
                    style="width: 100%; height: 100%;"
                  ></iframe>
                </div>`;
              }
              break;

            case 'dailymotion':
              if (data.videoId) {
                return `<div class="he-video-wrapper" style="aspect-ratio: ${aspectRatio}">
                  <iframe
                    src="https://www.dailymotion.com/embed/video/${data.videoId}"
                    frameborder="0"
                    allow="autoplay; fullscreen"
                    allowfullscreen
                    style="width: 100%; height: 100%;"
                  ></iframe>
                </div>`;
              }
              break;

            case 'hosted':
              const videoUrl = data.hostedUrl || data.externalUrl;
              if (videoUrl) {
                const poster = data.poster?.url ? `poster="${data.poster.url}"` : '';
                const autoplay = data.autoplay ? 'autoplay' : '';
                const muted = data.mute ? 'muted' : '';
                const loop = data.loop ? 'loop' : '';
                const controls = data.controls ? 'controls' : '';
                return `<div class="he-video-wrapper" style="aspect-ratio: ${aspectRatio}">
                  <video ${poster} ${autoplay} ${muted} ${loop} ${controls} style="width: 100%; height: 100%;">
                    <source src="${videoUrl}" type="video/mp4">
                  </video>
                </div>`;
              }
              break;
          }

          return '<div class="he-video-placeholder">Video unavailable</div>';
        },

        // Image
        'image': (data) => {
          const image = data.image;
          if (!image || !image.url) {
            return '';
          }

          const alt = this.escapeHtml(image.alt || '');
          const caption = data.caption ? `<figcaption>${this.escapeHtml(data.caption)}</figcaption>` : '';
          const imgHtml = `<img src="${image.url}" alt="${alt}" loading="lazy" />`;

          if (data.link && data.link.url) {
            const attrs = this.getLinkAttributes(data.link);
            return `<figure class="he-image"><a ${attrs}>${imgHtml}</a>${caption}</figure>`;
          }

          return `<figure class="he-image">${imgHtml}${caption}</figure>`;
        },

        // Image gallery
        'image-gallery': (data) => {
          const images = data.images || [];
          const columns = data.columns || 4;

          const imagesHtml = images.map(img => {
            return `<div class="he-gallery-item">
              <img src="${img.url}" alt="" loading="lazy" />
            </div>`;
          }).join('');

          return `<div class="he-gallery" style="display: grid; grid-template-columns: repeat(${columns}, 1fr); gap: 10px;">
            ${imagesHtml}
          </div>`;
        },

        // Button
        'button': (data) => {
          const text = this.escapeHtml(data.text || 'Click here');
          const size = data.size || 'sm';

          if (data.link && data.link.url) {
            const attrs = this.getLinkAttributes(data.link);
            return `<a class="he-button he-button-${size}" ${attrs}>${text}</a>`;
          }

          return `<button class="he-button he-button-${size}">${text}</button>`;
        },

        // Icon box
        'icon-box': (data) => {
          const icon = this.renderIcon(data.icon);
          const titleTag = data.titleTag || 'h3';
          const title = data.title ? `<${titleTag} class="he-icon-box-title">${this.escapeHtml(data.title)}</${titleTag}>` : '';
          const description = data.description ? `<p class="he-icon-box-description">${this.escapeHtml(data.description)}</p>` : '';

          let content = `<div class="he-icon-box">
            ${icon ? `<div class="he-icon-box-icon">${icon}</div>` : ''}
            <div class="he-icon-box-content">${title}${description}</div>
          </div>`;

          if (data.link && data.link.url) {
            const attrs = this.getLinkAttributes(data.link);
            return `<a ${attrs} class="he-icon-box-link">${content}</a>`;
          }

          return content;
        },

        // Icon list
        'icon-list': (data) => {
          const items = data.items || [];
          const itemsHtml = items.map(item => {
            const icon = this.renderIcon(item.icon);
            const text = this.escapeHtml(item.text || '');

            if (item.link && item.link.url) {
              const attrs = this.getLinkAttributes(item.link);
              return `<li class="he-icon-list-item"><a ${attrs}>${icon}${text}</a></li>`;
            }
            return `<li class="he-icon-list-item">${icon}<span>${text}</span></li>`;
          }).join('');

          return `<ul class="he-icon-list">${itemsHtml}</ul>`;
        },

        // Counter
        'counter': (data) => {
          const prefix = data.prefix ? `<span class="he-counter-prefix">${this.escapeHtml(data.prefix)}</span>` : '';
          const suffix = data.suffix ? `<span class="he-counter-suffix">${this.escapeHtml(data.suffix)}</span>` : '';
          const title = data.title ? `<div class="he-counter-title">${this.escapeHtml(data.title)}</div>` : '';

          return `<div class="he-counter" data-start="${data.startingNumber || 0}" data-end="${data.endingNumber || 100}" data-duration="${data.duration || 2000}">
            <div class="he-counter-number">
              ${prefix}<span class="he-counter-value">${data.endingNumber || 100}</span>${suffix}
            </div>
            ${title}
          </div>`;
        },

        // Progress
        'progress': (data) => {
          const percent = data.percent || 0;
          const title = data.title ? `<div class="he-progress-title">${this.escapeHtml(data.title)}</div>` : '';
          const percentText = data.displayPercent ? `<span class="he-progress-percent">${percent}%</span>` : '';

          return `<div class="he-progress">
            ${title}
            <div class="he-progress-bar-wrapper">
              <div class="he-progress-bar" style="width: ${percent}%">
                ${percentText}
              </div>
            </div>
          </div>`;
        },

        // Testimonial
        'testimonial': (data) => {
          const image = data.image?.url ? `<img src="${data.image.url}" alt="" class="he-testimonial-image" />` : '';
          const content = data.content ? `<div class="he-testimonial-content">${this.escapeHtml(data.content)}</div>` : '';
          const name = data.name ? `<div class="he-testimonial-name">${this.escapeHtml(data.name)}</div>` : '';
          const job = data.job ? `<div class="he-testimonial-job">${this.escapeHtml(data.job)}</div>` : '';

          return `<div class="he-testimonial">
            ${image}
            ${content}
            <div class="he-testimonial-footer">${name}${job}</div>
          </div>`;
        },

        // Tabs
        'tabs': (data) => {
          const tabs = data.tabs || [];
          const tabsNav = tabs.map((tab, i) =>
            `<button class="he-tab-button${i === 0 ? ' active' : ''}" data-tab="${tab.id}">${this.escapeHtml(tab.title)}</button>`
          ).join('');

          const tabsContent = tabs.map((tab, i) =>
            `<div class="he-tab-content${i === 0 ? ' active' : ''}" data-tab="${tab.id}">${tab.content}</div>`
          ).join('');

          return `<div class="he-tabs" data-type="${data.type || 'horizontal'}">
            <div class="he-tabs-nav">${tabsNav}</div>
            <div class="he-tabs-content">${tabsContent}</div>
          </div>`;
        },

        // Accordion
        'accordion': (data) => {
          const items = data.items || [];
          const itemsHtml = items.map((item, i) =>
            `<div class="he-accordion-item${i === 0 ? ' active' : ''}">
              <button class="he-accordion-header">${this.escapeHtml(item.title)}</button>
              <div class="he-accordion-content">${item.content}</div>
            </div>`
          ).join('');

          return `<div class="he-accordion">${itemsHtml}</div>`;
        },

        // Toggle (similar to accordion but multiple can be open)
        'toggle': (data) => {
          const items = data.items || [];
          const itemsHtml = items.map(item =>
            `<div class="he-toggle-item">
              <button class="he-toggle-header">${this.escapeHtml(item.title)}</button>
              <div class="he-toggle-content">${item.content}</div>
            </div>`
          ).join('');

          return `<div class="he-toggle">${itemsHtml}</div>`;
        },

        // Social icons
        'social-icons': (data) => {
          const icons = data.icons || [];
          const iconsHtml = icons.map(item => {
            const icon = this.renderIcon(item.icon);
            if (item.link && item.link.url) {
              const attrs = this.getLinkAttributes(item.link);
              return `<a class="he-social-icon" ${attrs} aria-label="${this.escapeHtml(item.label || '')}">${icon}</a>`;
            }
            return `<span class="he-social-icon">${icon}</span>`;
          }).join('');

          return `<div class="he-social-icons">${iconsHtml}</div>`;
        },

        // Alert
        'alert': (data) => {
          const type = data.alertType || 'info';
          const title = data.title ? `<strong class="he-alert-title">${this.escapeHtml(data.title)}</strong>` : '';
          const description = data.description || '';
          const dismiss = data.showDismiss ? '<button class="he-alert-dismiss">&times;</button>' : '';

          return `<div class="he-alert he-alert-${type}">
            ${dismiss}
            ${title}
            <div class="he-alert-description">${this.escapeHtml(description)}</div>
          </div>`;
        },

        // HTML
        'html': (data) => {
          return `<div class="he-html">${data.html || ''}</div>`;
        },

        // Shortcode (rendered on server)
        'shortcode': (data) => {
          return `<div class="he-shortcode">${data.rendered || ''}</div>`;
        },

        // Divider
        'divider': (data) => {
          const style = data.style || 'solid';
          return `<hr class="he-divider he-divider-${style}" />`;
        },

        // Spacer
        'spacer': (data) => {
          const space = data.space?.size || 50;
          return `<div class="he-spacer" style="height: ${space}px;"></div>`;
        },

        // Google Maps
        'google_maps': (data) => {
          const address = encodeURIComponent(data.address || '');
          const zoom = data.zoom?.size || 10;
          const height = data.height?.size || 300;

          return `<div class="he-map" style="height: ${height}px;">
            <iframe
              src="https://maps.google.com/maps?q=${address}&z=${zoom}&output=embed"
              width="100%"
              height="100%"
              frameborder="0"
              style="border:0"
              allowfullscreen
              loading="lazy"
            ></iframe>
          </div>`;
        },

        // Form (basic rendering)
        'form': (data) => {
          const fields = data.fields || [];
          const fieldsHtml = fields.map(field => {
            const required = field.required ? 'required' : '';
            const placeholder = field.placeholder ? `placeholder="${this.escapeHtml(field.placeholder)}"` : '';
            const label = field.label ? `<label class="he-form-label">${this.escapeHtml(field.label)}</label>` : '';

            switch (field.type) {
              case 'textarea':
                return `<div class="he-form-field">${label}<textarea name="${field.id}" ${placeholder} ${required}></textarea></div>`;
              case 'select':
                const options = (field.options || '').split('\n').map(opt => `<option value="${this.escapeHtml(opt)}">${this.escapeHtml(opt)}</option>`).join('');
                return `<div class="he-form-field">${label}<select name="${field.id}" ${required}>${options}</select></div>`;
              case 'checkbox':
                return `<div class="he-form-field he-form-checkbox"><label><input type="checkbox" name="${field.id}" ${required} /> ${this.escapeHtml(field.label)}</label></div>`;
              case 'radio':
                const radios = (field.options || '').split('\n').map(opt => `<label><input type="radio" name="${field.id}" value="${this.escapeHtml(opt)}" ${required} /> ${this.escapeHtml(opt)}</label>`).join('');
                return `<div class="he-form-field">${label}<div class="he-form-radios">${radios}</div></div>`;
              default:
                return `<div class="he-form-field">${label}<input type="${field.type || 'text'}" name="${field.id}" ${placeholder} ${required} /></div>`;
            }
          }).join('');

          return `<form class="he-form" data-form-name="${this.escapeHtml(data.formName || '')}">
            ${fieldsHtml}
            <button type="submit" class="he-button he-button-${data.buttonSize || 'sm'}">${this.escapeHtml(data.buttonText || 'Submit')}</button>
          </form>`;
        },

        // Default/unknown widget
        'default': (data, element) => {
          return `<div class="he-unknown-widget">
            <p>Widget type "${element.widgetType}" is not yet supported.</p>
          </div>`;
        }
      };
    }

    /**
     * Register a custom widget renderer
     */
    registerWidget(widgetType, renderer) {
      this.widgetRenderers[widgetType] = renderer;
    }

    /**
     * Initialize interactive widgets after render
     */
    initializeWidgets(container) {
      // Initialize tabs
      container.querySelectorAll('.he-tabs').forEach(tabs => {
        const buttons = tabs.querySelectorAll('.he-tab-button');
        const contents = tabs.querySelectorAll('.he-tab-content');

        buttons.forEach(button => {
          button.addEventListener('click', () => {
            const tabId = button.dataset.tab;
            buttons.forEach(b => b.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            button.classList.add('active');
            tabs.querySelector(`.he-tab-content[data-tab="${tabId}"]`)?.classList.add('active');
          });
        });
      });

      // Initialize accordions
      container.querySelectorAll('.he-accordion').forEach(accordion => {
        accordion.querySelectorAll('.he-accordion-header').forEach(header => {
          header.addEventListener('click', () => {
            const item = header.parentElement;
            const wasActive = item.classList.contains('active');

            // Close all items
            accordion.querySelectorAll('.he-accordion-item').forEach(i => i.classList.remove('active'));

            // Open clicked if it wasn't active
            if (!wasActive) {
              item.classList.add('active');
            }
          });
        });
      });

      // Initialize toggles
      container.querySelectorAll('.he-toggle').forEach(toggle => {
        toggle.querySelectorAll('.he-toggle-header').forEach(header => {
          header.addEventListener('click', () => {
            header.parentElement.classList.toggle('active');
          });
        });
      });

      // Initialize alert dismiss
      container.querySelectorAll('.he-alert-dismiss').forEach(button => {
        button.addEventListener('click', () => {
          button.closest('.he-alert').remove();
        });
      });
    }

    /**
     * Load external stylesheets
     */
    loadStyles(urls) {
      urls.forEach(url => {
        if (this.stylesLoaded.has(url)) return;

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = url;
        document.head.appendChild(link);
        this.stylesLoaded.add(url);
      });
    }

    /**
     * Inject inline CSS
     */
    injectInlineCSS(css) {
      if (!css) return;

      const style = document.createElement('style');
      style.textContent = css;
      document.head.appendChild(style);
    }

    /**
     * Helper: Escape HTML
     */
    escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    /**
     * Helper: Get link attributes
     */
    getLinkAttributes(link) {
      const attrs = [`href="${link.url}"`];
      if (link.isExternal) attrs.push('target="_blank"');
      if (link.nofollow) attrs.push('rel="nofollow"');
      return attrs.join(' ');
    }

    /**
     * Helper: Render icon
     */
    renderIcon(icon) {
      if (!icon || !icon.value) return '';

      // Handle FontAwesome icons
      if (icon.library === 'fa-solid' || icon.library === 'fa-regular' || icon.library === 'fa-brands') {
        return `<i class="${icon.value}"></i>`;
      }

      // Handle SVG icons (value is the SVG code or URL)
      if (icon.library === 'svg') {
        return icon.value;
      }

      // Default: try as class
      return `<i class="${icon.value}"></i>`;
    }

    /**
     * Helper: Get aspect ratio string
     */
    getAspectRatio(ratio) {
      const ratios = {
        '169': '16/9',
        '219': '21/9',
        '43': '4/3',
        '32': '3/2',
        '11': '1/1',
        '916': '9/16'
      };
      return ratios[ratio] || '16/9';
    }

    /**
     * Helper: Get YouTube embed params
     */
    getYouTubeParams(data) {
      const params = [];
      if (data.autoplay) params.push('autoplay=1');
      if (data.mute) params.push('mute=1');
      if (data.loop) params.push(`loop=1&playlist=${data.videoId}`);
      if (!data.controls) params.push('controls=0');
      if (!data.showinfo) params.push('showinfo=0');
      if (data.modestbranding) params.push('modestbranding=1');
      if (!data.rel) params.push('rel=0');
      if (data.startTime) params.push(`start=${data.startTime}`);
      if (data.endTime) params.push(`end=${data.endTime}`);
      return params.join('&');
    }

    /**
     * Helper: Get Vimeo embed params
     */
    getVimeoParams(data) {
      const params = [];
      if (data.autoplay) params.push('autoplay=1');
      if (data.mute) params.push('muted=1');
      if (data.loop) params.push('loop=1');
      if (data.color) params.push(`color=${data.color.replace('#', '')}`);
      return params.join('&');
    }
  }

  // Create default instance
  const defaultRenderer = new ElementorRenderer();

  // Public API
  return {
    // Shorthand methods using default renderer
    render: (container, url, options) => defaultRenderer.render(container, url, options),
    renderFromData: (container, data, options) => defaultRenderer.renderFromData(container, data, options),
    toHTML: (widgets) => defaultRenderer.toHTML(widgets),

    // Create custom instance
    createRenderer: (options) => new ElementorRenderer(options),

    // Register custom widget
    registerWidget: (type, renderer) => defaultRenderer.registerWidget(type, renderer),

    // Access default renderer
    renderer: defaultRenderer
  };
}));
