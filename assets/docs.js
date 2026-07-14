(function () {
    'use strict';

    var CFG = window.ZUNODocsConfig || {};
    var DEBOUNCE_MS = 150;
    var MIN_QUERY = 2;
    var MAX_SUGGESTIONS = 8;
    var ADMIN_BAR_H = 32;
    var _activeWrapper = null;

    /* ===================================================================
     * Utility helpers
     * =================================================================== */
    function debounce(fn, wait) {
        var timer;
        return function () {
            var ctx = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, wait);
        };
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }
    function qsa(sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); }

    function makeId(text, usedIds) {
        var base = text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'section';
        var id = base;
        var n = 1;
        while (usedIds && usedIds.has(id)) id = base + '-' + (n++);
        if (usedIds) usedIds.add(id);
        return id;
    }

    function getTopOffset(wrapper) {
        var offset = 20;
        if (wrapper && wrapper.classList.contains('zuno-docs-has-admin-bar')) {
            var bar = document.getElementById('wpadminbar');
            if (bar) offset += bar.getBoundingClientRect().height;
        }
        return offset;
    }

    /* ===================================================================
     * Client-side inverted-index search
     * =================================================================== */
    var SearchEngine = {
        _docs: {},

        init: function (docs) {
            this._docs = docs || {};
        },

        search: function (query) {
            var q = query.trim().toLowerCase();
            if (q.length < MIN_QUERY) return [];

            var terms = q.split(/\s+/).filter(function (t) { return t.length >= MIN_QUERY; });
            if (!terms.length) return [];

            var scores = {};
            var docIds = Object.keys(this._docs);

            docIds.forEach(function (id) {
                var doc = this._docs[id];
                if (!doc) return;
                var score = 0;
                var titleLower = doc.title.toLowerCase();
                var excerptLower = (doc.excerpt || '').toLowerCase();

                var allMatch = true;
                terms.forEach(function (term) {
                    var titleIdx = titleLower.indexOf(term);
                    var excerptIdx = excerptLower.indexOf(term);

                    if (titleIdx === 0) score += 10;
                    else if (titleIdx > 0) score += 6;
                    else if (excerptIdx >= 0) score += 3;

                    if (titleIdx < 0 && excerptIdx < 0) allMatch = false;
                });

                if (allMatch) score += 2;

                if (score > 0) {
                    scores[id] = score;
                }
            }, this);

            var sorted = Object.keys(scores).sort(function (a, b) {
                return scores[b] - scores[a];
            });

            return sorted.slice(0, MAX_SUGGESTIONS).map(function (id) {
                return { id: id, title: this._docs[id].title, excerpt: this._docs[id].excerpt, score: scores[id] };
            }, this);
        }
    };

    /* ===================================================================
     * Suggestions dropdown
     * =================================================================== */
    var Suggestions = {
        _el: null,
        _searchInput: null,
        _noResultsEl: null,

        init: function (el, searchInput, noResultsEl) {
            this._el = el;
            this._searchInput = searchInput;
            this._noResultsEl = noResultsEl;
        },

        show: function (results, query) {
            if (!this._el || !this._searchInput) return;
            this._el.innerHTML = '';
            this._noResultsEl.classList.add('zuno-docs-hidden');

            if (!results.length) {
                this._el.classList.add('zuno-docs-hidden');
                this._noResultsEl.classList.remove('zuno-docs-hidden');
                return;
            }

            var frag = document.createDocumentFragment();
            results.forEach(function (r) {
                var item = document.createElement('button');
                item.className = 'zuno-docs-suggestion-item';
                item.setAttribute('role', 'option');
                item.setAttribute('data-doc-id', r.id);

                var titleHtml = r.title;
                if (query) {
                    var re = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                    titleHtml = r.title.replace(re, '<mark class="zuno-docs-suggestion-mark">$1</mark>');
                }

                item.innerHTML = '<span class="zuno-docs-suggestion-title">' + titleHtml + '</span>' +
                    (r.excerpt ? '<span class="zuno-docs-suggestion-excerpt">' + escapeHtml(r.excerpt.slice(0, 80)) + '</span>' : '');

                item.addEventListener('click', this._onSelect.bind(this, r));
                frag.appendChild(item);
            }, this);

            this._el.appendChild(frag);
            this._el.classList.remove('zuno-docs-hidden');
        },

        hide: function () {
            if (this._el) {
                this._el.classList.add('zuno-docs-hidden');
                this._el.innerHTML = '';
            }
        },

        _onSelect: function (result) {
            this.hide();
            this._searchInput.value = result.title;
            this._searchInput.blur();
            var event = new CustomEvent('zuno-docs-navigate', { detail: { docId: result.id } });
            if (_activeWrapper) {
                _activeWrapper.dispatchEvent(event);
            } else {
                document.dispatchEvent(event);
            }
        }
    };

    /* ===================================================================
     * Chapter Engine — core chapter management
     * =================================================================== */
    var ChapterEngine = {
        _chapters: [],
        _activeId: null,
        _contentEl: null,
        _wrapper: null,
        _onChangeCallbacks: [],
        _onAfterChangeCallbacks: [],

        init: function (contentEl, wrapper) {
            this._contentEl = contentEl;
            this._wrapper = wrapper;
            this._chapters = [];
            this._activeId = null;

            var children = Array.from(contentEl.childNodes);
            var currentWrapper = null;
            var usedIds = new Set();

            children.forEach(function (child) {
                if (child.nodeType === 1 && child.tagName === 'H1') {
                    if (!child.id) {
                        child.id = makeId(child.textContent.trim(), usedIds);
                    } else {
                        usedIds.add(child.id);
                    }

                    currentWrapper = document.createElement('div');
                    currentWrapper.className = 'zuno-docs-chapter';
                    currentWrapper.id = 'zuno-chapter-' + child.id;
                    currentWrapper.dataset.chapterId = child.id;

                    this._chapters.push({
                        id: child.id,
                        title: child.textContent.trim(),
                        h1: child,
                        wrapper: currentWrapper,
                        sections: []
                    });
                }

                if (currentWrapper) {
                    currentWrapper.appendChild(child);
                }
            }, this);

            /* Fallback: no H1 found → treat all content as one chapter */
            if (this._chapters.length === 0 && children.length > 0) {
                var defaultWrapper = document.createElement('div');
                defaultWrapper.className = 'zuno-docs-chapter zuno-docs-chapter-active';
                defaultWrapper.id = 'zuno-chapter-documentation';
                defaultWrapper.dataset.chapterId = 'documentation';

                children.forEach(function (child) {
                    defaultWrapper.appendChild(child);
                });

                this._chapters.push({
                    id: 'documentation',
                    title: 'Documentation',
                    h1: null,
                    wrapper: defaultWrapper,
                    sections: []
                });
            }

            contentEl.innerHTML = '';
            this._chapters.forEach(function (ch) {
                contentEl.appendChild(ch.wrapper);

                var headingEls = ch.wrapper.querySelectorAll('h2, h3, h4, h5, h6');
                headingEls.forEach(function (h) {
                    if (!h.id) {
                        h.id = makeId(h.textContent.trim(), null);
                    }
                    var item = {
                        id: h.id,
                        el: h,
                        text: h.textContent.trim(),
                        tagLevel: parseInt(h.tagName[1], 10)
                    };
                    ch.sections.push(item);
                });

                /* Build hierarchical tree from flat sections */
                ch.tree = this._buildTree(ch.sections);
            }, this);

            return this._chapters;
        },

        _buildTree: function (headings) {
            var root = [];
            var stack = [];

            headings.forEach(function (h) {
                var node = {
                    id: h.id,
                    text: h.text,
                    tagLevel: h.tagLevel,
                    el: h.el,
                    children: []
                };

                while (stack.length && stack[stack.length - 1].tagLevel >= node.tagLevel) {
                    stack.pop();
                }

                if (stack.length) {
                    stack[stack.length - 1].children.push(node);
                } else {
                    root.push(node);
                }

                stack.push(node);
            });

            return root;
        },

        _flattenTree: function (nodes) {
            var result = [];
            nodes.forEach(function (node) {
                result.push({ id: node.id, el: node.el, text: node.text, tagLevel: node.tagLevel });
                if (node.children.length) {
                    result = result.concat(this._flattenTree(node.children));
                }
            }, this);
            return result;
        },

        getChapterSections: function (chapterId) {
            var ch = this.getChapter(chapterId);
            return ch ? ch.sections : [];
        },

        getChapterTree: function (chapterId) {
            var ch = this.getChapter(chapterId);
            return ch ? ch.tree : [];
        },

        activate: function (chapterId, options) {
            options = options || {};
            if (this._activeId === chapterId && !options.force) return;

            this._chapters.forEach(function (ch) {
                var isActive = ch.id === chapterId;
                ch.wrapper.style.display = isActive ? '' : 'none';
                ch.wrapper.classList.toggle('zuno-docs-chapter-active', isActive);
            });

            var prevId = this._activeId;
            this._activeId = chapterId;

            TocBuilder.setActive(chapterId);
            NavRail.rebuild(this.getActiveChapter());
            ReadingProgress.reset(chapterId);

            if (!options.silent) {
                this._updateUrl(chapterId);
            }

            this._onChangeCallbacks.forEach(function (cb) { cb(chapterId, prevId); });

            var self = this;
            setTimeout(function () {
                var activeCh = self.getActiveChapter();
                if (activeCh && !options.noScroll) {
                    var offset = getTopOffset(self._wrapper);
                    var top = activeCh.wrapper.getBoundingClientRect().top + window.pageYOffset - offset;
                    window.scrollTo({ top: Math.max(0, top), behavior: 'auto' });
                }
                self._onAfterChangeCallbacks.forEach(function (cb) { cb(chapterId, prevId); });
            }, 10);
        },

        getActiveChapter: function () {
            var self = this;
            return this._chapters.filter(function (ch) { return ch.id === self._activeId; })[0] || null;
        },

        getActiveChapterId: function () {
            return this._activeId;
        },

        getChapter: function (id) {
            return this._chapters.filter(function (ch) { return ch.id === id; })[0] || null;
        },

        getAllChapters: function () {
            return this._chapters.map(function (ch) {
                return { id: ch.id, title: ch.title };
            });
        },

        onChange: function (callback) {
            this._onChangeCallbacks.push(callback);
        },

        onAfterChange: function (callback) {
            this._onAfterChangeCallbacks.push(callback);
        },

        _updateUrl: function (chapterId) {
            if (history.pushState) {
                history.pushState(null, '', '#' + chapterId);
            }
        },

        getActiveChapterScrollPercent: function () {
            var ch = this.getActiveChapter();
            if (!ch) return 0;

            var wrapper = ch.wrapper;
            var totalHeight = wrapper.scrollHeight - window.innerHeight;
            if (totalHeight <= 0) return 100;

            var rect = wrapper.getBoundingClientRect();
            var offset = getTopOffset(this._wrapper);
            var passed = -(rect.top - offset);
            passed = Math.max(0, Math.min(passed, totalHeight));
            return (passed / totalHeight) * 100;
        }
    };

    /* ===================================================================
     * TOC Builder — flat H1 chapter list
     * =================================================================== */
    var TocBuilder = {
        _tocEl: null,
        _wrapper: null,
        _activeId: null,

        build: function (chapters, tocEl, wrapper) {
            this._tocEl = tocEl;
            this._wrapper = wrapper;
            this._activeId = null;

            tocEl.innerHTML = '';

            if (!chapters.length) {
                tocEl.style.display = 'none';
                return;
            }

            tocEl.style.display = '';

            var ul = document.createElement('ul');
            chapters.forEach(function (ch) {
                var li = document.createElement('li');
                li.dataset.depth = '1';
                li.dataset.id = ch.id;

                var a = document.createElement('a');
                a.href = '#' + ch.id;
                a.className = 'zuno-docs-toc-link';
                a.dataset.tocId = ch.id;
                a.textContent = ch.title;

                var self = this;
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    ChapterEngine.activate(ch.id);
                });

                li.appendChild(a);
                ul.appendChild(li);
            }, this);

            tocEl.appendChild(ul);
        },

        setActive: function (id) {
            this._activeId = id;
            var links = qsa('.zuno-docs-toc-link', this._tocEl);
            links.forEach(function (link) {
                var isActive = link.dataset.tocId === id;
                link.classList.toggle('is-active', isActive);
                link.setAttribute('aria-current', isActive ? 'true' : 'false');
            });

            var activeLink = qs('.zuno-docs-toc-link.is-active', this._tocEl);
            if (activeLink) {
                activeLink.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        },

        filterByQuery: function (query) {
            var q = query.trim().toLowerCase();
            if (q.length < MIN_QUERY) {
                this.resetFilter();
                return;
            }

            var lis = qsa('li', this._tocEl);
            lis.forEach(function (li) {
                li.classList.remove('zuno-docs-toc-match');
                li.classList.remove('zuno-docs-toc-hidden');
            });

            var matchCount = 0;
            lis.forEach(function (li) {
                var link = qs('.zuno-docs-toc-link', li);
                if (!link) return;
                var text = link.textContent.trim().toLowerCase();
                if (text.indexOf(q) !== -1) {
                    li.classList.add('zuno-docs-toc-match');
                    matchCount++;
                }
            });

            if (!matchCount) {
                lis.forEach(function (li) { li.classList.add('zuno-docs-toc-hidden'); });
                this._showNoResults(q);
                return;
            }

            lis.forEach(function (li) { li.classList.add('zuno-docs-toc-hidden'); });
            lis.forEach(function (li) {
                if (li.classList.contains('zuno-docs-toc-match')) {
                    li.classList.remove('zuno-docs-toc-hidden');
                }
            }, this);

            this._hideNoResults();
        },

        resetFilter: function () {
            var lis = qsa('li', this._tocEl);
            lis.forEach(function (li) {
                li.classList.remove('zuno-docs-toc-hidden');
                li.classList.remove('zuno-docs-toc-match');
            });
            this._hideNoResults();
        },

        _showNoResults: function (query) {
            var el = qs('.zuno-docs-toc-empty', this._tocEl);
            if (!el) {
                el = document.createElement('p');
                el.className = 'zuno-docs-toc-empty';
                this._tocEl.appendChild(el);
            }
            el.textContent = (CFG.i18n && CFG.i18n.tocNoResults) ? CFG.i18n.tocNoResults.replace('{query}', query) : 'No matching sections found for "' + query + '"';
            el.classList.remove('zuno-docs-hidden');
        },

        _hideNoResults: function () {
            var el = qs('.zuno-docs-toc-empty', this._tocEl);
            if (el) el.classList.add('zuno-docs-hidden');
        }
    };

    /* ===================================================================
     * Scroll Spy — within active chapter only
     * =================================================================== */
    var ScrollSpy = {
        _observer: null,
        _sections: [],
        _visibleIds: new Set(),
        _activeId: null,
        _scrollTimer: null,
        _wrapper: null,

        init: function (wrapper) {
            this._wrapper = wrapper;
        },

        rebuild: function (sections) {
            this.destroy();
            this._sections = sections;
            this._visibleIds = new Set();
            this._activeId = null;

            if (!sections.length || !('IntersectionObserver' in window)) return;

            var offset = getTopOffset(this._wrapper);
            this._observer = new IntersectionObserver(function (entries) {
                if (this._scrollTimer) return;
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        this._visibleIds.add(entry.target.id);
                    } else {
                        this._visibleIds.delete(entry.target.id);
                    }
                }.bind(this));
                this._updateActive();
            }.bind(this), {
                rootMargin: '-' + offset + 'px 0px -70% 0px',
                threshold: 0
            });

            sections.forEach(function (s) {
                if (s.el) this._observer.observe(s.el);
            }.bind(this));
        },

        activateHeading: function (id) {
            if (this._scrollTimer) clearTimeout(this._scrollTimer);
            this._activeId = id;
            this._updateNavLinks(id);
            var self = this;
            this._scrollTimer = setTimeout(function () {
                self._scrollTimer = null;
            }, 800);
        },

        _updateActive: function () {
            if (this._scrollTimer) return;
            var active = null;
            for (var i = 0; i < this._sections.length; i++) {
                if (this._visibleIds.has(this._sections[i].id)) {
                    active = this._sections[i].id;
                    break;
                }
            }
            if (!active || active === this._activeId) return;
            this._activeId = active;
            this._updateNavLinks(active);
            NavRail._activate(active, false);
        },

        _updateNavLinks: function (id) {
            NavRail._activate(id, false);
        },

        destroy: function () {
            if (this._observer) this._observer.disconnect();
            this._observer = null;
        }
    };

    /* ===================================================================
     * Navigation Rail — hierarchical per-chapter section navigator (H2–H6)
     * =================================================================== */
    var NavRail = {
        _el: null,
        _wrapper: null,
        _items: [],
        _itemMap: {},
        _observer: null,
        _boundaryObserver: null,
        _activeId: null,
        _suppressObserver: false,
        _topOffset: 20,

        init: function (wrapEl) {
            this._el = qs('.zuno-docs-nav-rail', wrapEl);
            this._wrapper = wrapEl;
            var display = CFG.display || {};
            if (!this._el || !display.show_navigation_rail) return;
            this._topOffset = getTopOffset(wrapEl);
        },

        rebuild: function (chapter) {
            if (!this._el) return;

            this._el.innerHTML = '';
            this._items = [];
            this._itemMap = {};
            this._activeId = null;
            this.destroy();

            var tree = chapter ? ChapterEngine.getChapterTree(chapter.id) : [];
            var flat = chapter ? chapter.sections : [];

            if (!tree.length) {
                this._el.style.display = 'none';
                return;
            }

            this._el.style.display = '';
            this._topOffset = getTopOffset(this._wrapper);

            this._buildIndicators(flat);
            this._buildPanel(tree, flat);
            this._initObserver(flat);
            this._initBoundary();
        },

        _buildIndicators: function (headings) {
            var wrap = document.createElement('div');
            wrap.className = 'zuno-docs-nav-indicators';
            wrap.setAttribute('aria-hidden', 'true');

            headings.forEach(function (h) {
                var dot = document.createElement('div');
                dot.className = 'zuno-docs-nav-indicator';
                dot.dataset.target = h.id;
                wrap.appendChild(dot);
            });

            this._el.appendChild(wrap);
        },

        _renderNode: function (node, container, depth) {
            var self = this;
            var li = document.createElement('li');
            li.className = 'zuno-docs-nav-item';
            li.dataset.tagLevel = node.tagLevel;
            li.dataset.id = node.id;

            var hasChildren = node.children && node.children.length > 0;
            var isH2 = node.tagLevel === 2;

            var link = document.createElement('a');
            link.className = 'zuno-docs-nav-link';
            link.href = '#' + node.id;
            link.textContent = node.text;
            link.dataset.target = node.id;

            link.addEventListener('click', function (e) {
                e.preventDefault();
                var target = document.getElementById(node.id);
                if (!target) return;
                var offset = getTopOffset(self._wrapper);
                var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
                window.scrollTo({ top: top, behavior: 'smooth' });
                self._activate(node.id, true);
                ScrollSpy.activateHeading(node.id);
                self._suppressObserver = true;
                setTimeout(function () {
                    self._suppressObserver = false;
                }, 500);
            });

            li.appendChild(link);

            /* Expand/collapse toggle — only for H2 */
            if (isH2 && hasChildren) {
                var toggle = document.createElement('span');
                toggle.className = 'zuno-docs-nav-toggle';
                toggle.setAttribute('role', 'button');
                toggle.setAttribute('tabindex', '0');
                toggle.setAttribute('aria-label', 'Toggle section');
                toggle.innerHTML = '<svg width="8" height="8" viewBox="0 0 8 8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 1l3 3-3 3"/></svg>';

                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    var isOpen = li.classList.toggle('zuno-docs-nav-expanded');
                    toggle.setAttribute('aria-expanded', String(isOpen));
                    var sublist = qs('.zuno-docs-nav-sublist', li);
                    if (sublist) {
                        sublist.style.maxHeight = isOpen ? sublist.scrollHeight + 'px' : '0';
                    }
                });

                toggle.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        var isOpen = li.classList.toggle('zuno-docs-nav-expanded');
                        this.setAttribute('aria-expanded', String(isOpen));
                        var sublist = qs('.zuno-docs-nav-sublist', li);
                        if (sublist) {
                            sublist.style.maxHeight = isOpen ? sublist.scrollHeight + 'px' : '0';
                        }
                    }
                });

                link.insertBefore(toggle, link.firstChild);
                li.classList.add('zuno-docs-nav-expanded');
                toggle.setAttribute('aria-expanded', 'true');
            }

            /* Register flat item for observer + activation */
            var itemEntry = { id: node.id, el: node.el, link: link, li: li };
            self._items.push(itemEntry);
            self._itemMap[node.id] = itemEntry;

            /* Render children recursively */
            if (hasChildren) {
                var sublist = document.createElement('ul');
                sublist.className = 'zuno-docs-nav-sublist';
                node.children.forEach(function (child) {
                    self._renderNode(child, sublist, depth + 1);
                });
                li.appendChild(sublist);
            }

            container.appendChild(li);
        },

        _buildPanel: function (tree, flat) {
            var panel = document.createElement('div');
            panel.className = 'zuno-docs-nav-panel';
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-label', 'Section navigation');

            var header = document.createElement('div');
            header.className = 'zuno-docs-nav-panel-header';

            var icon = document.createElement('span');
            icon.className = 'zuno-docs-nav-panel-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>';

            header.appendChild(icon);
            header.appendChild(document.createTextNode('On this page'));
            panel.appendChild(header);

            var list = document.createElement('ul');
            list.className = 'zuno-docs-nav-list';

            var self = this;
            tree.forEach(function (node) {
                self._renderNode(node, list, 1);
            });

            panel.appendChild(list);
            this._el.appendChild(panel);

            /* Sync indicator indices */
            var indicators = qsa('.zuno-docs-nav-indicator', this._el);
            self._items.forEach(function (item, i) {
                item.indicator = indicators[i] || null;
            });
        },

        _initObserver: function (headings) {
            if (!('IntersectionObserver' in window)) return;

            var visibleIds = new Set();
            var self = this;
            var rootMargin = '-' + this._topOffset + 'px 0px -65% 0px';

            this._observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        visibleIds.add(entry.target.id);
                    } else {
                        visibleIds.delete(entry.target.id);
                    }
                });

                if (self._suppressObserver) return;

                var active = null;
                for (var i = 0; i < self._items.length; i++) {
                    if (visibleIds.has(self._items[i].id)) {
                        active = self._items[i].id;
                        break;
                    }
                }

                if (active && active !== self._activeId) {
                    self._activate(active, false);
                }
            }, {
                rootMargin: rootMargin,
                threshold: 0
            });

            headings.forEach(function (h) {
                if (h.el) self._observer.observe(h.el);
            });
        },

        _initBoundary: function () {
            var contentEl = qs('.zuno-docs-content', this._wrapper);
            if (!contentEl) return;
            var sentinel = document.createElement('div');
            sentinel.className = 'zuno-docs-nav-sentinel';
            contentEl.appendChild(sentinel);

            var self = this;
            this._boundaryObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        var railRect = self._el.getBoundingClientRect();
                        var containerRect = self._el.parentElement.getBoundingClientRect();
                        var topPx = railRect.top - containerRect.top;
                        self._el.style.top = topPx + 'px';
                        self._el.classList.add('is-at-bottom');
                    } else {
                        self._el.style.top = '';
                        self._el.classList.remove('is-at-bottom');
                    }
                });
            }, {
                rootMargin: '0px 0px 300px 0px',
                threshold: 0
            });

            this._boundaryObserver.observe(sentinel);
        },

        /* Expand parent chain for a given node ID */
        _expandParents: function (id) {
            var el = qs('[data-id="' + id + '"]', this._el);
            if (!el) return;
            var cur = el.parentElement;
            while (cur && cur !== this._el) {
                if (cur.tagName === 'LI' && parseInt(cur.dataset.tagLevel, 10) === 2) {
                    cur.classList.add('zuno-docs-nav-expanded');
                    var toggle = qs('.zuno-docs-nav-toggle', cur);
                    if (toggle) toggle.setAttribute('aria-expanded', 'true');
                    var sublist = qs('.zuno-docs-nav-sublist', cur);
                    if (sublist) {
                        sublist.style.maxHeight = sublist.scrollHeight + 'px';
                    }
                }
                cur = cur.parentElement;
            }
        },

        _activate: function (id, fromClick) {
            if (this._activeId === id && !fromClick) return;
            this._activeId = id;

            this._items.forEach(function (item) {
                var isActive = item.id === id;
                if (item.indicator) {
                    item.indicator.classList.toggle('is-active', isActive);
                }
                item.link.classList.toggle('is-active', isActive);
                item.link.setAttribute('aria-current', isActive ? 'true' : 'false');
                var li = item.li;
                if (li) {
                    li.classList.toggle('zuno-docs-nav-active', isActive);
                    li.classList.remove('zuno-docs-nav-parent-active');
                }
            });

            /* Mark parents as expanded */
            if (id) {
                this._expandParents(id);
            }

            /* Scroll active item into view in the panel */
            var activeLink = qs('.zuno-docs-nav-link.is-active', this._el);
            if (activeLink) {
                activeLink.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        },

        destroy: function () {
            if (this._observer) {
                this._observer.disconnect();
                this._observer = null;
            }
            if (this._boundaryObserver) {
                this._boundaryObserver.disconnect();
                this._boundaryObserver = null;
            }
            var sentinel = qs('.zuno-docs-nav-sentinel', this._wrapper);
            if (sentinel) sentinel.remove();
        }
    };

    /* ===================================================================
     * Content Search — chapter-aware in-page search
     * =================================================================== */
    var ContentSearch = {
        _contentEl: null,

        init: function (contentEl) {
            this._contentEl = contentEl;
        },

        search: function (query) {
            this._clearHighlights();
            if (!query || query.trim().length < MIN_QUERY) return [];
            var q = query.trim();
            return this._searchAndHighlight(q);
        },

        clear: function () {
            this._clearHighlights();
        },

        _findMatchesInElement: function (el, ql, chapterId) {
            var results = [];
            var walker = document.createTreeWalker(el, NodeFilter.SHOW_ELEMENT, {
                acceptNode: function (node) {
                    if (node.tagName === 'SCRIPT' || node.tagName === 'STYLE' || node.tagName === 'MARK') {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                }
            });

            while (walker.nextNode()) {
                var node = walker.currentNode;
                var text = (node.textContent || '').trim();
                if (text.toLowerCase().indexOf(ql) !== -1) {
                    var tagName = node.tagName;
                    var type = 'paragraph';
                    var score = 1;
                    if (tagName === 'H1') { type = 'h1'; score = 10; }
                    else if (tagName === 'H2') { type = 'h2'; score = 9; }
                    else if (tagName === 'H3') { type = 'h3'; score = 8; }
                    else if (tagName === 'H4') { type = 'h4'; score = 7; }
                    else if (tagName === 'H5' || tagName === 'H6') { type = 'heading'; score = 6; }
                    else if (tagName === 'LI') { type = 'list-item'; score = 3; }
                    else if (tagName === 'TD' || tagName === 'TH') { type = 'table-cell'; score = 2; }

                    results.push({
                        el: node,
                        type: type,
                        text: text,
                        score: score,
                        chapterId: chapterId
                    });
                }
            }
            return results;
        },

        _searchAndHighlight: function (query) {
            var allResults = [];
            var ql = query.toLowerCase();
            var chapters = ChapterEngine.getAllChapters();

            chapters.forEach(function (ch) {
                var chapterObj = ChapterEngine.getChapter(ch.id);
                if (!chapterObj || !chapterObj.wrapper) return;
                var matches = this._findMatchesInElement(chapterObj.wrapper, ql, ch.id);
                allResults = allResults.concat(matches);
            }, this);

            allResults.sort(function (a, b) { return b.score - a.score; });

            if (allResults.length) {
                var bestResult = allResults[0];
                var currentId = ChapterEngine.getActiveChapterId();

                if (bestResult.chapterId !== currentId) {
                    ChapterEngine.activate(bestResult.chapterId, { silent: true, noScroll: true });
                }

                var self = this;
                setTimeout(function () {
                    self._highlightAllMatches(query);
                    self._scrollToFirst();
                }, 20);
            }

            return allResults;
        },

        _highlightAllMatches: function (query) {
            if (!query || query.length < MIN_QUERY) return;

            var activeCh = ChapterEngine.getActiveChapter();
            if (!activeCh || !activeCh.wrapper) return;

            var textNodes = [];
            var walker = document.createTreeWalker(
                activeCh.wrapper,
                NodeFilter.SHOW_TEXT,
                null,
                false
            );
            while (walker.nextNode()) {
                var node = walker.currentNode;
                var parent = node.parentNode;
                if (parent && parent.nodeName !== 'MARK' && parent.nodeName !== 'SCRIPT' && parent.nodeName !== 'STYLE' && parent.nodeName !== 'TEXTAREA') {
                    textNodes.push(node);
                }
            }

            var isFirst = true;
            textNodes.forEach(function (textNode) {
                var text = textNode.textContent;
                var lowerText = text.toLowerCase();
                var idx = lowerText.indexOf(query.toLowerCase());
                if (idx === -1) return;

                var re = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                var frag = document.createDocumentFragment();
                var lastIdx = 0;

                text.replace(re, function (match, group, offset) {
                    if (offset > lastIdx) {
                        frag.appendChild(document.createTextNode(text.slice(lastIdx, offset)));
                    }

                    var mark = document.createElement('mark');
                    mark.className = 'zuno-docs-highlight';
                    if (isFirst) {
                        mark.classList.add('zuno-docs-highlight-first');
                        isFirst = false;
                    }
                    mark.textContent = match;
                    frag.appendChild(mark);

                    lastIdx = offset + match.length;
                });

                if (lastIdx < text.length) {
                    frag.appendChild(document.createTextNode(text.slice(lastIdx)));
                }

                textNode.parentNode.replaceChild(frag, textNode);
            });
        },

        _clearHighlights: function () {
            if (!this._contentEl) return;
            var marks = qsa('mark.zuno-docs-highlight, mark.zuno-docs-highlight-first', this._contentEl);
            marks.forEach(function (mark) {
                var parent = mark.parentNode;
                parent.replaceChild(document.createTextNode(mark.textContent), mark);
                parent.normalize();
            });
        },

        _scrollToFirst: function () {
            var activeCh = ChapterEngine.getActiveChapter();
            if (!activeCh || !activeCh.wrapper) return;
            var first = qs('.zuno-docs-highlight-first', activeCh.wrapper);
            if (!first) first = qs('.zuno-docs-highlight', activeCh.wrapper);
            if (!first) return;
            var offset = getTopOffset(_activeWrapper);
            var top = first.getBoundingClientRect().top + window.pageYOffset - offset - 10;
            window.scrollTo({ top: top, behavior: 'smooth' });
        }
    };

    /* ===================================================================
     * Reading Progress Bar — per-chapter
     * =================================================================== */
    function initReadingProgress(wrapEl) {
        var bar = qs('.zuno-docs-progress-bar-fill', wrapEl);
        if (!bar) return;

        var update = function () {
            var pct = ChapterEngine.getActiveChapterScrollPercent();
            bar.style.width = Math.min(100, Math.max(0, pct)) + '%';
        };

        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update, { passive: true });
        ChapterEngine.onAfterChange(update);
        update();
    }

    var ReadingProgress = {
        reset: function () {
            var bar = _activeWrapper ? qs('.zuno-docs-progress-bar-fill', _activeWrapper) : null;
            if (bar) {
                bar.style.width = '0%';
            }
        }
    };

    /* ===================================================================
     * Breadcrumbs
     * =================================================================== */
    function renderBreadcrumbs(wrapEl) {
        var display = CFG.display || {};
        if (!display.show_breadcrumbs) {
            var hideEl = qs('.zuno-docs-breadcrumbs', wrapEl);
            if (hideEl) hideEl.style.display = 'none';
            return;
        }

        var crumbs = CFG.breadcrumbs || [];
        var el = qs('.zuno-docs-breadcrumbs', wrapEl);
        if (!el) return;

        if (!crumbs.length) {
            el.style.display = 'none';
            return;
        }

        el.innerHTML = '';
        crumbs.forEach(function (crumb, i) {
            var span = document.createElement('span');
            if (i > 0) {
                var sep = document.createElement('span');
                sep.className = 'zuno-docs-breadcrumb-sep';
                sep.setAttribute('aria-hidden', 'true');
                sep.textContent = '/';
                el.appendChild(sep);
            }
            span.className = 'zuno-docs-breadcrumb' + (i === crumbs.length - 1 ? ' is-current' : '');
            span.textContent = crumb.label;
            el.appendChild(span);
        });
        el.style.display = '';
    }

    /* ===================================================================
     * Prev / Next navigation
     * =================================================================== */
    function renderPageNav(wrapEl) {
        var el = qs('.zuno-docs-page-nav', wrapEl);
        if (!el) return;

        var display = CFG.display || {};
        var showPrev = display.show_previous !== false;
        var showNext = display.show_next !== false;

        var adjacent = CFG.adjacent || {};
        var prev = adjacent.prev;
        var next = adjacent.next;

        var hasPrev = prev && showPrev;
        var hasNext = next && showNext;

        if (!hasPrev && !hasNext) {
            el.style.display = 'none';
            return;
        }

        el.innerHTML = '';

        if (hasPrev) {
            var prevLink = document.createElement('a');
            prevLink.href = '?zuno_doc=' + prev.id;
            prevLink.className = 'zuno-docs-page-nav-link zuno-docs-page-nav-prev';
            prevLink.innerHTML = '<span class="zuno-docs-nav-direction">' + (CFG.i18n.prev || 'Previous') + '</span>' +
                '<span class="zuno-docs-nav-title">' + escapeHtml(prev.title) + '</span>';
            prevLink.addEventListener('click', function (e) {
                e.preventDefault();
                var event = new CustomEvent('zuno-docs-navigate', { detail: { docId: prev.id } });
                wrapEl.dispatchEvent(event);
            });
            el.appendChild(prevLink);
        }

        if (hasNext) {
            var nextLink = document.createElement('a');
            nextLink.href = '?zuno_doc=' + next.id;
            nextLink.className = 'zuno-docs-page-nav-link zuno-docs-page-nav-next';
            nextLink.innerHTML = '<span class="zuno-docs-nav-direction">' + (CFG.i18n.next || 'Next') + '</span>' +
                '<span class="zuno-docs-nav-title">' + escapeHtml(next.title) + '</span>';
            nextLink.addEventListener('click', function (e) {
                e.preventDefault();
                var event = new CustomEvent('zuno-docs-navigate', { detail: { docId: next.id } });
                wrapEl.dispatchEvent(event);
            });
            el.appendChild(nextLink);
        }

        el.style.display = '';
    }

    /* ===================================================================
     * Related articles
     * =================================================================== */
    function renderRelated(wrapEl) {
        var wrap = qs('.zuno-docs-related-wrap', wrapEl);
        if (!wrap) return;

        var display = CFG.display || {};
        if (!display.show_related) {
            wrap.style.display = 'none';
            return;
        }

        var related = CFG.related || [];

        if (!related.length) {
            wrap.style.display = 'none';
            return;
        }

        var list = qs('.zuno-docs-related-list', wrap);
        if (!list) return;

        list.innerHTML = '';
        related.forEach(function (r) {
            var li = document.createElement('li');
            var a = document.createElement('a');
            a.href = '?zuno_doc=' + r.id;
            a.textContent = r.title;
            a.addEventListener('click', function (e) {
                e.preventDefault();
                var event = new CustomEvent('zuno-docs-navigate', { detail: { docId: r.id } });
                wrapEl.dispatchEvent(event);
            });
            li.appendChild(a);
            list.appendChild(li);
        });

        wrap.style.display = '';
    }

    /* ===================================================================
     * Search UI
     * =================================================================== */
    function initSearch(wrapEl) {
        var input = qs('.zuno-docs-search-input', wrapEl);
        var clear = qs('.zuno-docs-search-clear', wrapEl);
        var noResults = qs('.zuno-docs-no-results', wrapEl);
        var suggestionsEl = qs('.zuno-docs-suggestions', wrapEl);
        var tocEl = qs('.zuno-docs-toc', wrapEl);
        var contentEl = qs('.zuno-docs-content', wrapEl);

        if (!input) return;

        var searchData = CFG.searchIndex || {};
        SearchEngine.init(searchData.docs);
        Suggestions.init(suggestionsEl, input, noResults);
        ContentSearch.init(contentEl);

        function clearAll() {
            input.value = '';
            Suggestions.hide();
            noResults.classList.add('zuno-docs-hidden');
            clear.classList.add('zuno-docs-hidden');
            ContentSearch.clear();
            TocBuilder.resetFilter();
        }

        function performSearch(value) {
            var q = value.trim();

            if (q.length < MIN_QUERY) {
                Suggestions.hide();
                noResults.classList.add('zuno-docs-hidden');
                clear.classList.toggle('zuno-docs-hidden', !q);
                ContentSearch.clear();
                TocBuilder.resetFilter();
                return;
            }

            if (searchData.docs && Object.keys(searchData.docs).length > 0) {
                var results = SearchEngine.search(q);
                Suggestions.show(results, q);
            } else {
                ajaxSearch(q, function (results) {
                    Suggestions.show(results, q);
                });
            }

            ContentSearch.search(q);
            TocBuilder.filterByQuery(q);

            clear.classList.remove('zuno-docs-hidden');
        }

        var debouncedSearch = debounce(performSearch, DEBOUNCE_MS);

        input.addEventListener('input', function (e) {
            debouncedSearch(e.target.value);
        });

        input.addEventListener('focus', function () {
            if (input.value.trim().length >= MIN_QUERY) {
                performSearch(input.value);
            }
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                clearAll();
                input.blur();
            } else if (e.key === 'ArrowDown') {
                var first = qs('.zuno-docs-suggestion-item', suggestionsEl);
                if (first) first.focus();
                e.preventDefault();
            }
        });

        suggestionsEl.addEventListener('keydown', function (e) {
            var items = qsa('.zuno-docs-suggestion-item', suggestionsEl);
            var current = qs('.zuno-docs-suggestion-item:focus', suggestionsEl);
            var idx = items.indexOf(current);

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                var next = items[idx + 1];
                if (next) next.focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                var prev = items[idx - 1];
                if (prev) prev.focus();
                else input.focus();
            } else if (e.key === 'Escape') {
                clearAll();
                input.focus();
            }
        });

        if (clear) {
            clear.addEventListener('click', function () {
                input.focus();
                clearAll();
            });
        }

        wrapEl.addEventListener('click', function (e) {
            if (e.target === input) return;
            if (!e.target.closest('.zuno-docs-suggestions')) {
                setTimeout(function () { Suggestions.hide(); }, 200);
            }
        });
    }

    /* ===================================================================
     * AJAX search fallback
     * =================================================================== */
    function ajaxSearch(query, callback) {
        var restUrl = CFG.restUrl;
        if (!restUrl) {
            callback([]);
            return;
        }

        var url = restUrl + '?q=' + encodeURIComponent(query);
        if (CFG.product) url += '&product=' + encodeURIComponent(CFG.product);

        fetch(url, {
            headers: { 'X-WP-Nonce': CFG.restNonce || '' }
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            callback(data.results || []);
        })
        .catch(function () {
            callback([]);
        });
    }

    /* ===================================================================
     * Navigation event handling
     * =================================================================== */
    function initNavigation(wrapEl) {
        wrapEl.addEventListener('zuno-docs-navigate', function (e) {
            var docId = e.detail && e.detail.docId;
            if (!docId) return;
            var url = new URL(window.location.href);
            url.searchParams.set('zuno_doc', docId);
            window.location.href = url.toString();
        });

        qsa('.zuno-docs-content a[href*="zuno_doc="]', wrapEl).forEach(function (a) {
            a.addEventListener('click', function (e) {
                var url = new URL(a.href);
                var docId = url.searchParams.get('zuno_doc');
                if (docId) {
                    e.preventDefault();
                    window.location.href = a.href;
                }
            });
        });
    }

    /* ===================================================================
     * Admin bar offset
     * =================================================================== */
    function initAdminBarOffset(wrapEl) {
        var bar = document.getElementById('wpadminbar');
        if (!bar) return;

        wrapEl.classList.add('zuno-docs-has-admin-bar');

        var update = function () {
            var h = bar.getBoundingClientRect().height;
            wrapEl.style.setProperty('--zuno-adminbar-h', h + 'px');
            wrapEl.style.setProperty('--zuno-offset', h + 'px');
            wrapEl.style.setProperty('--zuno-docs-sidebar-top', h + 'px');
        };

        update();
        window.addEventListener('resize', debounce(update, 100), { passive: true });
    }

    /* ===================================================================
     * Hash handler — chapter-aware
     * =================================================================== */
    function handleInitialHash(wrapEl) {
        var hash = window.location.hash.slice(1);
        if (!hash) {
            var chapters = ChapterEngine.getAllChapters();
            if (chapters.length) {
                ChapterEngine.activate(chapters[0].id);
            }
            return;
        }

        var chapter = ChapterEngine.getChapter(hash);
        if (chapter) {
            ChapterEngine.activate(chapter.id);
            setTimeout(function () {
                var offset = getTopOffset(wrapEl);
                var top = chapter.wrapper.getBoundingClientRect().top + window.pageYOffset - offset;
                window.scrollTo({ top: top > 0 ? top : 0, behavior: 'smooth' });
            }, 150);
        } else {
            var allChapters = ChapterEngine.getAllChapters();
            if (allChapters.length) {
                ChapterEngine.activate(allChapters[0].id);
            }
        }
    }

    /* ===================================================================
     * Mobile TOC — floating card with overlay
     * =================================================================== */
    function initMobileToc(wrapEl) {
        var mobileToc = qs('.zuno-docs-mobile-toc', wrapEl);
        var trigger = qs('.zuno-docs-mobile-toc-trigger', wrapEl);
        var closeBtn = qs('.zuno-docs-mobile-toc-close', wrapEl);
        var backdrop = qs('.zuno-docs-mobile-toc-backdrop', wrapEl);
        var panel = qs('.zuno-docs-mobile-toc-panel', wrapEl);
        var panelBody = qs('.zuno-docs-mobile-toc-panel-body', wrapEl);
        var sidebar = qs('.zuno-docs-sidebar', wrapEl);

        if (!trigger || !mobileToc) return;

        var position = mobileToc.dataset.mobileTocPosition || 'top';

        if (position !== 'top') {
            var boundaryBottom;

            function calcBoundary() {
                if (position === 'bottom') {
                    boundaryBottom = window.innerHeight;
                } else {
                    boundaryBottom = window.innerHeight / 2 + trigger.offsetHeight / 2;
                }
            }

            calcBoundary();

            function updateBoundary() {
                mobileToc.classList.toggle('is-at-boundary', wrapEl.getBoundingClientRect().bottom <= boundaryBottom);
            }

            updateBoundary();

            var scrollTick;
            window.addEventListener('scroll', function () {
                if (!isMobile()) return;
                cancelAnimationFrame(scrollTick);
                scrollTick = requestAnimationFrame(updateBoundary);
            }, { passive: true });

            window.addEventListener('resize', function () {
                if (!isMobile()) return;
                calcBoundary();
                updateBoundary();
            }, { passive: true });
        }

        function isMobile() {
            return window.innerWidth < 768;
        }

        function getScrollOffset() {
            if (position === 'top') {
                return getTopOffset(wrapEl);
            }
            return 0;
        }

        function openToc() {
            if (!isMobile()) return;
            mobileToc.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            if (position !== 'bottom') {
                document.body.classList.add('zuno-docs-toc-open');
            }

            if (panelBody && sidebar && !panelBody.querySelector('.zuno-docs-toc') && !panelBody.querySelector('.zuno-docs-search-wrap')) {
                var searchWrap = qs('.zuno-docs-search-wrap', sidebar);
                var tocNav = qs('.zuno-docs-toc', sidebar);
                var suggestions = qs('.zuno-docs-suggestions', sidebar);
                var noResults = qs('.zuno-docs-no-results', sidebar);

                if (searchWrap) {
                    var searchClone = searchWrap.cloneNode(true);
                    panelBody.insertBefore(searchClone, panelBody.firstChild);
                    var newInput = qs('.zuno-docs-search-input', panelBody);
                    if (newInput) {
                        newInput.addEventListener('input', function (e) {
                            var sidebarInput = qs('.zuno-docs-search-input', sidebar);
                            if (sidebarInput) {
                                sidebarInput.value = e.target.value;
                                sidebarInput.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        });
                        newInput.addEventListener('focus', function () {
                            var sidebarInput = qs('.zuno-docs-search-input', sidebar);
                            if (sidebarInput) {
                                sidebarInput.focus();
                            }
                        });
                    }
                }

                if (suggestions) {
                    var suggClone = suggestions.cloneNode(true);
                    panelBody.appendChild(suggClone);
                }

                if (noResults) {
                    var nrClone = noResults.cloneNode(true);
                    panelBody.appendChild(nrClone);
                }

                if (tocNav) {
                    var tocClone = tocNav.cloneNode(true);
                    panelBody.appendChild(tocClone);
                    qsa('.zuno-docs-toc-link', panelBody).forEach(function (link) {
                        link.addEventListener('click', function (e) {
                            e.preventDefault();
                            var id = link.dataset.tocId;
                            if (id) {
                                closeToc();
                                ChapterEngine.activate(id);
                            }
                        });
                    });
                }
            }

            var sidebarInput = qs('.zuno-docs-search-input', sidebar);
            var panelInput = qs('.zuno-docs-search-input', panelBody);
            if (sidebarInput && panelInput) {
                panelInput.value = sidebarInput.value;
            }
        }

        function closeToc() {
            mobileToc.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('zuno-docs-toc-open');
        }

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            if (mobileToc.classList.contains('is-open')) {
                closeToc();
            } else {
                openToc();
            }
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                closeToc();
            });
        }

        if (backdrop) {
            backdrop.addEventListener('click', function (e) {
                e.stopPropagation();
                closeToc();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && mobileToc.classList.contains('is-open')) {
                closeToc();
            }
        });

        if (panelBody) {
            panelBody.addEventListener('click', function (e) {
                var link = e.target.closest('.zuno-docs-toc-link');
                if (link && isMobile()) {
                    var id = link.dataset.tocId;
                    if (id) {
                        closeToc();
                        ChapterEngine.activate(id);
                    }
                }
            });
        }

        var origSetActive = TocBuilder.setActive;
        TocBuilder.setActive = function (id) {
            origSetActive.call(TocBuilder, id);
            if (isMobile() && panelBody && id) {
                var activeLink = qs('.zuno-docs-toc-link.is-active', panelBody);
                if (activeLink) {
                    activeLink.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            }
        };

        window.addEventListener('resize', function () {
            if (!isMobile() && mobileToc.classList.contains('is-open')) {
                closeToc();
            }
        }, { passive: true });
    }

    /* ===================================================================
     * Initialiser
     * =================================================================== */
    function initInstance(wrapEl) {
        _activeWrapper = wrapEl;

        var contentEl = qs('.zuno-docs-content', wrapEl);
        var tocEl = qs('.zuno-docs-toc', wrapEl);
        if (!contentEl || !tocEl) return;

        /* 1. Build chapter structure */
        var chapters = ChapterEngine.init(contentEl, wrapEl);

        /* 2. Build TOC (flat H1 list) */
        TocBuilder.build(chapters, tocEl, wrapEl);

        /* 3. Init scroll spy */
        ScrollSpy.init(wrapEl);

        /* 4. Init NavRail */
        NavRail.init(wrapEl);

        /* 5. Breadcrumbs */
        renderBreadcrumbs(wrapEl);

        /* 6. Page nav */
        renderPageNav(wrapEl);

        /* 7. Related articles */
        renderRelated(wrapEl);

        /* 8. Reading progress bar */
        initReadingProgress(wrapEl);

        /* 9. Search */
        initSearch(wrapEl);

        /* 10. Navigation */
        initNavigation(wrapEl);

        /* 11. Admin bar offset */
        initAdminBarOffset(wrapEl);

        /* 12. Mobile TOC */
        initMobileToc(wrapEl);

        /* 13. Register chapter change handler BEFORE activating */
        ChapterEngine.onChange(function (chapterId) {
            var ch = ChapterEngine.getChapter(chapterId);
            ScrollSpy.rebuild(ch ? ch.sections : []);
        });

        /* 14. Activate initial chapter (from hash or first) */
        handleInitialHash(wrapEl);

        /* 15. Hash change listener */
        window.addEventListener('hashchange', function () {
            var hash = window.location.hash.slice(1);
            if (hash) {
                var ch = ChapterEngine.getChapter(hash);
                if (ch) {
                    ChapterEngine.activate(ch.id);
                }
            }
        });
    }

    /* ===================================================================
     * Entry Point
     * =================================================================== */
    function boot() {
        var instances = qsa('.zuno-docs');
        instances.forEach(function (wrap) { initInstance(wrap); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
