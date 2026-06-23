(function () {
  "use strict";

  /* ===================================================================
   * Constants & config
   * ================================================================= */
  const CFG = window.ZUNODocsConfig || {};
  const TOC_DEPTH = parseInt(CFG.tocDepth, 10) || 6;
  const DEBOUNCE = 250;
  const SPY_OFFSET = 80;
  const MIN_QUERY = 3;

  /* ===================================================================
   * Utility helpers
   * ================================================================= */
  function slugify(text, usedIds) {
    let base = text
      .toLowerCase()
      .trim()
      .replace(/[^\w\s-]/g, "")
      .replace(/[\s_]+/g, "-")
      .replace(/^-+|-+$/g, "");
    if (!base) base = "section";
    let slug = base;
    let n = 1;
    while (usedIds.has(slug)) slug = base + "-" + n++;
    usedIds.add(slug);
    return slug;
  }

  function debounce(fn, wait) {
    let timer;
    return function (...args) {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), wait);
    };
  }

  function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }

  /* ===================================================================
   * Module: TOC Builder
   * ================================================================= */
  const TocBuilder = {
    build(contentEl, tocEl, maxDepth) {
      const levelRange = [];
      for (let i = 1; i <= maxDepth; i++) levelRange.push("h" + i);

      const headings = Array.from(
        contentEl.querySelectorAll(levelRange.join(",")),
      );
      const usedIds = new Set();
      const flatList = [];

      headings.forEach((el) => {
        if (!el.id) {
          el.id = slugify(el.textContent, usedIds);
        } else {
          usedIds.add(el.id);
        }
        flatList.push({
          el,
          id: el.id,
          text: el.textContent.trim(),
          level: parseInt(el.tagName[1], 10),
        });
      });

      if (!flatList.length) return flatList;

      const tree = this._buildTree(flatList);
      const ul = this._renderTree(tree, 1);
      tocEl.appendChild(ul);

      return flatList;
    },

    _buildTree(flatList) {
      const root = [];
      const stack = [];

      flatList.forEach((item) => {
        const node = { item, children: [] };
        while (
          stack.length &&
          stack[stack.length - 1].item.level >= item.level
        ) {
          stack.pop();
        }
        if (stack.length === 0) {
          root.push(node);
        } else {
          stack[stack.length - 1].children.push(node);
        }
        stack.push(node);
      });

      return root;
    },

    _renderTree(nodes, depth) {
      const ul = document.createElement("ul");

      nodes.forEach((node) => {
        const li = document.createElement("li");
        const hasKids = node.children.length > 0;

        li.dataset.depth = depth;
        li.dataset.id = node.item.id;

        const a = document.createElement("a");
        a.href = "#" + node.item.id;
        a.className = "zuno-docs-toc-link";
        a.dataset.tocId = node.item.id;
        a.setAttribute("aria-label", node.item.text);

        if (hasKids) {
          const toggle = document.createElement("span");
          toggle.className = "zuno-docs-toc-toggle";
          toggle.setAttribute("aria-hidden", "true");
          toggle.innerHTML =
            '<svg width="10" height="10" viewBox="0 0 10 10" fill="none"' +
            ' stroke="currentColor" stroke-width="1.8" stroke-linecap="round">' +
            '<polyline points="3,1 7,5 3,9"/></svg>';
          a.appendChild(toggle);
        }

        a.appendChild(document.createTextNode(node.item.text));

        a.addEventListener("click", (e) => {
          e.preventDefault();
          const target = document.getElementById(node.item.id);
          if (target) {
            target.scrollIntoView({ behavior: "smooth", block: "start" });
            history.pushState(null, "", "#" + node.item.id);
          }
          if (hasKids) {
            li.classList.toggle("is-open");
          }
        });

        li.appendChild(a);

        if (hasKids) {
          li.classList.add("has-children");
          li.appendChild(this._renderTree(node.children, depth + 1));
        }

        ul.appendChild(li);
      });

      return ul;
    },
  };

  /* ===================================================================
   * Module: Scroll Spy
   * ================================================================= */
  const ScrollSpy = {
    _headings: [],
    _activeId: null,
    _observer: null,
    _visibleSet: new Set(),

    init(flatHeadings, tocEl) {
      if (!flatHeadings.length || !("IntersectionObserver" in window)) return;

      this._headings = flatHeadings;
      this._tocEl = tocEl;

      const options = {
        root: null,
        rootMargin: "-" + SPY_OFFSET + "px 0px -70% 0px",
        threshold: 0,
      };

      this._observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            this._visibleSet.add(entry.target.id);
          } else {
            this._visibleSet.delete(entry.target.id);
          }
        });
        this._updateActive();
      }, options);

      flatHeadings.forEach(({ el }) => this._observer.observe(el));
    },

    _updateActive() {
      const activeId = this._headings.find((h) =>
        this._visibleSet.has(h.id),
      )?.id;
      if (!activeId || activeId === this._activeId) return;

      this._activeId = activeId;

      this._tocEl.querySelectorAll(".zuno-docs-toc-link").forEach((link) => {
        const isCurrent = link.dataset.tocId === activeId;
        link.classList.toggle("is-active", isCurrent);
        link.setAttribute("aria-current", isCurrent ? "true" : "false");

        if (isCurrent) {
          let parent = link.closest("li")?.parentElement?.closest("li");
          while (parent) {
            parent.classList.add("is-open");
            parent = parent.parentElement?.closest("li");
          }
        }
      });
    },

    destroy() {
      this._observer?.disconnect();
    },
  };

  /* ===================================================================
   * Module: Search Engine — text-node–safe highlight engine
   * ================================================================= */
  const SearchEngine = {
    _contentEl: null,
    _headings: [],

    init(contentEl, flatHeadings) {
      this._contentEl = contentEl;
      this._headings = flatHeadings;
    },

    _removeHighlights() {
      const marks = this._contentEl.querySelectorAll(
        "mark.zuno-docs-highlight",
      );
      marks.forEach((m) => {
        const parent = m.parentNode;
        while (m.firstChild) {
          parent.insertBefore(m.firstChild, m);
        }
        parent.removeChild(m);
      });
      this._contentEl.normalize();
      this._contentEl
        .querySelectorAll(".zuno-docs-highlight-first")
        .forEach((el) => {
          el.classList.remove("zuno-docs-highlight-first");
        });
    },

    _applyHighlights(regex) {
      const walker = document.createTreeWalker(
        this._contentEl,
        NodeFilter.SHOW_TEXT,
        {
          acceptNode(node) {
            const parent = node.parentElement;
            if (!parent) return NodeFilter.FILTER_REJECT;
            const tag = parent.tagName;
            if (
              tag === "SCRIPT" ||
              tag === "STYLE" ||
              tag === "PRE" ||
              tag === "CODE" ||
              tag === "MARK"
            ) {
              return NodeFilter.FILTER_REJECT;
            }
            return NodeFilter.FILTER_ACCEPT;
          },
        },
      );

      const nodesToReplace = [];

      while (walker.nextNode()) {
        const node = walker.currentNode;
        if (regex.test(node.textContent)) {
          nodesToReplace.push(node);
        }
        regex.lastIndex = 0;
      }

      nodesToReplace.forEach((textNode) => {
        const frag = document.createDocumentFragment();
        const text = textNode.textContent;
        let last = 0;
        let match;

        regex.lastIndex = 0;

        while ((match = regex.exec(text)) !== null) {
          if (match.index > last) {
            frag.appendChild(
              document.createTextNode(text.slice(last, match.index)),
            );
          }
          const mark = document.createElement("mark");
          mark.className = "zuno-docs-highlight";
          mark.appendChild(document.createTextNode(match[0]));
          frag.appendChild(mark);
          last = regex.lastIndex;
        }

        if (last < text.length) {
          frag.appendChild(document.createTextNode(text.slice(last)));
        }

        textNode.parentNode.replaceChild(frag, textNode);
      });
    },

    _scrollToFirst() {
      const first = this._contentEl.querySelector(".zuno-docs-highlight");
      if (first) {
        first.classList.add("zuno-docs-highlight-first");
        first.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    },

    _syncToc(tocEl) {
      const first = this._contentEl.querySelector(".zuno-docs-highlight");
      if (!first) return;

      let el = first;
      while (el && el !== this._contentEl) {
        if (/^H[1-6]$/.test(el.tagName) && el.id) {
          tocEl.querySelectorAll(".zuno-docs-toc-link").forEach((link) => {
            const isMatch = link.dataset.tocId === el.id;
            link.classList.toggle("is-active", isMatch);
            link.setAttribute("aria-current", isMatch ? "true" : "false");
            if (isMatch) {
              let parent = link.closest("li")?.parentElement?.closest("li");
              while (parent) {
                parent.classList.add("is-open");
                parent = parent.parentElement?.closest("li");
              }
            }
          });
          break;
        }
        el = el.parentElement;
      }
    },

    query(query, tocEl, noResultsEl) {
      const q = query.trim().toLowerCase();
      const hasQ = q.length >= MIN_QUERY;

      this._removeHighlights();

      this._contentEl
        .querySelectorAll(".zuno-docs-section-hidden")
        .forEach((el) => {
          el.classList.remove("zuno-docs-section-hidden");
        });
      tocEl.querySelectorAll("li").forEach((li) => {
        li.classList.remove("zuno-docs-section-hidden");
      });

      if (!hasQ) {
        noResultsEl.classList.add("zuno-docs-hidden");
        if (this._headings.length) {
          ScrollSpy._updateActive();
        }
        return;
      }

      const regex = new RegExp("(" + escapeRegex(q) + ")", "gi");

      this._applyHighlights(regex);

      let hits = 0;
      this._headings.forEach((h) => {
        const headingText = h.el.textContent.toLowerCase();
        let sectionText = headingText;
        let node = h.el.nextElementSibling;
        while (node && !/^H[1-6]$/.test(node.tagName)) {
          sectionText += " " + node.textContent.toLowerCase();
          node = node.nextElementSibling;
        }
        const matches = sectionText.includes(q);
        if (matches) hits++;

        const tocLink = tocEl.querySelector('[data-toc-id="' + h.id + '"]');
        if (tocLink) {
          tocLink
            .closest("li")
            .classList.toggle("zuno-docs-section-hidden", !matches);
        }
      });

      noResultsEl.classList.toggle("zuno-docs-hidden", hits > 0);

      if (hits) {
        this._scrollToFirst();
        this._syncToc(tocEl);
      }
    },

    clear(tocEl, noResultsEl) {
      this._removeHighlights();
      this._contentEl
        .querySelectorAll(".zuno-docs-section-hidden")
        .forEach((el) => {
          el.classList.remove("zuno-docs-section-hidden");
        });
      tocEl.querySelectorAll("li").forEach((li) => {
        li.classList.remove("zuno-docs-section-hidden");
      });
      noResultsEl.classList.add("zuno-docs-hidden");

      if (this._headings.length) {
        ScrollSpy._updateActive();
      }
    },
  };

  /* ===================================================================
   * Module: Mobile Sidebar
   * ================================================================= */
  const MobileSidebar = {
    init(sidebarEl, toggleBtn) {
      if (!toggleBtn) return;

      toggleBtn.addEventListener("click", () => {
        const isOpen = sidebarEl.classList.toggle("is-open");
        toggleBtn.setAttribute("aria-expanded", String(isOpen));
      });

      sidebarEl.addEventListener("click", (e) => {
        if (
          e.target.closest(".zuno-docs-toc-link") &&
          window.innerWidth < 768
        ) {
          sidebarEl.classList.remove("is-open");
          toggleBtn.setAttribute("aria-expanded", "false");
        }
      });
    },
  };

  /* ===================================================================
   * Module: Admin Bar Offset
   * ================================================================= */
  function applyAdminBarOffset(wrapEl) {
    const bar = document.getElementById("wpadminbar");
    if (!bar) return;

    const update = () => {
      const h = bar.getBoundingClientRect().height;
      wrapEl.style.setProperty("--zuno-docs-adminbar-h", h + "px");
      wrapEl.style.setProperty("--zuno-docs-sidebar-top", h + "px");
    };

    update();
    window.addEventListener("resize", debounce(update, 100));
  }

  /* ===================================================================
   * Initialiser
   * ================================================================= */
  function initInstance(wrapEl) {
    const sidebarEl = wrapEl.querySelector(".zuno-docs-sidebar");
    const toggleBtn = wrapEl.querySelector(".zuno-docs-sidebar-toggle");
    const tocEl = wrapEl.querySelector(".zuno-docs-toc");
    const contentEl = wrapEl.querySelector(".zuno-docs-content");
    const searchInput = wrapEl.querySelector(".zuno-docs-search-input");
    const searchClear = wrapEl.querySelector(".zuno-docs-search-clear");
    const noResultsEl = wrapEl.querySelector(".zuno-docs-no-results");

    if (!tocEl || !contentEl) return;

    const maxDepth = parseInt(wrapEl.dataset.tocDepth, 10) || TOC_DEPTH;

    // 1. Build TOC
    const flatHeadings = TocBuilder.build(contentEl, tocEl, maxDepth);

    // 2. Scroll spy
    ScrollSpy.init(flatHeadings, tocEl);

    // 3. Init search engine
    SearchEngine.init(contentEl, flatHeadings);

    // 4. Search input events
    if (searchInput) {
      const debouncedSearch = debounce((q) => {
        SearchEngine.query(q, tocEl, noResultsEl);
        searchClear?.classList.toggle("zuno-docs-hidden", !q);
      }, DEBOUNCE);

      searchInput.addEventListener("input", (e) => {
        debouncedSearch(e.target.value);
      });

      searchInput.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
          searchInput.value = "";
          SearchEngine.clear(tocEl, noResultsEl);
          searchClear?.classList.add("zuno-docs-hidden");
        }
      });

      searchClear?.addEventListener("click", () => {
        searchInput.value = "";
        searchInput.focus();
        SearchEngine.clear(tocEl, noResultsEl);
        searchClear.classList.add("zuno-docs-hidden");
      });
    }

    // 5. Mobile sidebar
    MobileSidebar.init(sidebarEl, toggleBtn);

    // 6. Admin bar offset
    applyAdminBarOffset(wrapEl);

    // 7. Handle initial hash
    const hash = window.location.hash.slice(1);
    if (hash) {
      const target = document.getElementById(hash);
      if (target) {
        setTimeout(
          () => target.scrollIntoView({ behavior: "smooth", block: "start" }),
          150,
        );
      }
    }

    // 8. Expand top-level TOC items
    tocEl.querySelectorAll("li.has-children").forEach((li) => {
      if (li.closest("ul") === tocEl.querySelector("ul")) {
        li.classList.add("is-open");
      }
    });
  }

  /* ===================================================================
   * Entry Point
   * ================================================================= */
  function boot() {
    const instances = document.querySelectorAll(".zuno-docs-wrap");
    instances.forEach((wrap) => initInstance(wrap));
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
