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
     * TOC Builder — hierarchical tree with collapsible sections
     * =================================================================== */
    var TocBuilder = {
        _headings: [],
        _tocEl: null,
        _tree: [],
        _maxDepth: 6,
        _wrapper: null,

        build: function (contentEl, tocEl, maxDepth, wrapper) {
            this._tocEl = tocEl;
            this._maxDepth = maxDepth || 6;
            this._headings = [];
            this._tree = [];
            this._wrapper = wrapper;

            tocEl.innerHTML = '';

            var levelRange = [];
            for (var i = 1; i <= this._maxDepth; i++) levelRange.push('h' + i);

            var headingEls = qsa(levelRange.join(','), contentEl);
            if (!headingEls.length) {
                tocEl.style.display = 'none';
                return this._headings;
            }

            tocEl.style.display = '';
            var label = document.createElement('p');
            label.className = 'zuno-docs-toc-label';
            label.setAttribute('aria-hidden', 'true');
            label.textContent = CFG.i18n && CFG.i18n.tocLabel ? CFG.i18n.tocLabel : 'On this page';
            tocEl.appendChild(label);

            var usedIds = new Set();

            headingEls.forEach(function (el) {
                if (!el.id) {
                    var base = el.textContent.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'section';
                    var id = base;
                    var n = 1;
                    while (usedIds.has(id)) id = base + '-' + (n++);
                    el.id = id;
                }
                usedIds.add(el.id);

                var tagLevel = parseInt(el.tagName[1], 10);
                var depth;
                if (tagLevel <= 2) depth = 1;
                else if (tagLevel <= 4) depth = 2;
                else depth = 3;

                this._headings.push({
                    el: el,
                    id: el.id,
                    text: el.textContent.trim(),
                    tagLevel: tagLevel,
                    depth: depth
                });
            }, this);

            this._tree = this._buildTree(this._headings);
            this._renderTree(this._tree, tocEl);
            return this._headings;
        },

        _buildTree: function (headings) {
            var root = [];
            var stack = [];

            headings.forEach(function (h) {
                var node = {
                    id: h.id,
                    text: h.text,
                    depth: h.depth,
                    treeDepth: 0,
                    el: h.el,
                    children: []
                };

                while (stack.length && stack[stack.length - 1].depth >= node.depth) {
                    stack.pop();
                }

                if (stack.length) {
                    var parent = stack[stack.length - 1];
                    parent.children.push(node);
                    node.treeDepth = parent.treeDepth + 1;
                } else {
                    root.push(node);
                    node.treeDepth = 1;
                }

                stack.push(node);
            });

            return root;
        },

        _renderTree: function (nodes, container) {
            var ul = document.createElement('ul');

            nodes.forEach(function (node) {
                var li = document.createElement('li');
                li.dataset.depth = node.treeDepth;
                li.dataset.id = node.id;

                var hasChildren = node.children.length > 0;

                var a = document.createElement('a');
                a.href = '#' + node.id;
                a.className = 'zuno-docs-toc-link';
                a.dataset.tocId = node.id;
                a.textContent = node.text;

                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    var target = qs('#' + CSS.escape(node.id), this._wrapper);
                    if (target) {
                        var offset = this._wrapper.classList.contains('zuno-docs-has-admin-bar') ? ADMIN_BAR_H + 20 : 20;
                        var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
                        window.scrollTo({ top: top, behavior: 'smooth' });
                        ScrollSpy.activateHeading(node.id);
                    }
                }.bind(this));

                if (hasChildren) {
                    var toggle = document.createElement('span');
                    toggle.className = 'zuno-docs-toc-toggle';
                    toggle.setAttribute('role', 'button');
                    toggle.setAttribute('tabindex', '0');
                    toggle.setAttribute('aria-label', 'Toggle section');
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.innerHTML = '<svg width="8" height="8" viewBox="0 0 8 8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 1l3 3-3 3"/></svg>';

                    toggle.addEventListener('click', function (e) {
                        e.stopPropagation();
                        e.preventDefault();
                        var isOpen = li.classList.toggle('is-open');
                        this.setAttribute('aria-expanded', String(isOpen));
                    });

                    toggle.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            var isOpen = li.classList.toggle('is-open');
                            this.setAttribute('aria-expanded', String(isOpen));
                        }
                    });

                    a.insertBefore(toggle, a.firstChild);

                    if (node.treeDepth === 1) {
                        li.classList.add('is-open');
                        toggle.setAttribute('aria-expanded', 'true');
                    }
                }

                li.appendChild(a);

                if (hasChildren) {
                    this._renderTree(node.children, li);
                }

                ul.appendChild(li);
            }, this);

            container.appendChild(ul);
        },

        getAllHeadings: function () {
            var result = [];
            this._flatten(this._tree, result);
            return result;
        },

        _flatten: function (nodes, result) {
            nodes.forEach(function (node) {
                result.push(node);
                if (node.children.length) {
                    this._flatten(node.children, result);
                }
            }, this);
        },

        expandToItem: function (id) {
            var li = qs('li[data-id="' + id + '"]', this._tocEl);
            if (!li) return;
            var cur = li.parentElement;
            while (cur && cur !== this._tocEl) {
                if (cur.tagName === 'LI') {
                    cur.classList.add('is-open');
                    var toggle = qs('.zuno-docs-toc-toggle', cur);
                    if (toggle) toggle.setAttribute('aria-expanded', 'true');
                }
                cur = cur.parentElement;
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
            this._tocEl.classList.remove('zuno-docs-toc-searching');
            this._hideNoResults();

            var matchCount = 0;

            lis.forEach(function (li) {
                var link = qs('.zuno-docs-toc-link', li);
                if (!link) return;
                var text = link.textContent.replace('\u25B6', '').trim().toLowerCase();
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
                    var cur = li.parentElement;
                    while (cur && cur !== this._tocEl) {
                        if (cur.tagName === 'LI') {
                            cur.classList.remove('zuno-docs-toc-hidden');
                            cur.classList.add('is-open');
                        }
                        cur = cur.parentElement;
                    }
                }
            }, this);

            this._tocEl.classList.add('zuno-docs-toc-searching');
        },

        resetFilter: function () {
            var lis = qsa('li', this._tocEl);
            lis.forEach(function (li) {
                li.classList.remove('zuno-docs-toc-hidden');
                li.classList.remove('zuno-docs-toc-match');
                var depth = parseInt(li.dataset.depth, 10);
                li.classList.toggle('is-open', depth === 1);
            });
            this._tocEl.classList.remove('zuno-docs-toc-searching');
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
     * Scroll Spy — IntersectionObserver + hierarchy expansion
     * =================================================================== */
    var ScrollSpy = {
        _observer: null,
        _headings: [],
        _visibleIds: new Set(),
        _activeId: null,
        _scrollTimer: null,
        _wrapper: null,

        init: function (headings, wrapper) {
            this._headings = headings;
            this._wrapper = wrapper;
            if (!headings.length || !('IntersectionObserver' in window)) return;

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
                rootMargin: '-' + (ADMIN_BAR_H + 30) + 'px 0px -70% 0px',
                threshold: 0
            });

            headings.forEach(function (h) { this._observer.observe(h.el); }.bind(this));
        },

        activateHeading: function (id) {
            if (this._scrollTimer) clearTimeout(this._scrollTimer);
            this._activeId = id;
            this._updateActiveLinks(id);
            TocBuilder.expandToItem(id);
            var self = this;
            this._scrollTimer = setTimeout(function () {
                self._scrollTimer = null;
            }, 800);
        },

        _updateActive: function () {
            if (this._scrollTimer) return;
            var active = null;
            for (var i = 0; i < this._headings.length; i++) {
                if (this._visibleIds.has(this._headings[i].id)) {
                    active = this._headings[i].id;
                    break;
                }
            }

            if (!active || active === this._activeId) return;
            this._activeId = active;
            this._updateActiveLinks(active);
            TocBuilder.expandToItem(active);
        },

        _updateActiveLinks: function (id) {
            var links = qsa('.zuno-docs-toc-link', this._wrapper);
            links.forEach(function (link) {
                var isActive = link.dataset.tocId === id;
                link.classList.toggle('is-active', isActive);
                link.setAttribute('aria-current', isActive ? 'true' : 'false');
            });
        },

        destroy: function () {
            if (this._observer) this._observer.disconnect();
        }
    };

    /* ===================================================================
     * Content Search — in-page search with highlights
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

        _searchAndHighlight: function (query) {
            var results = [];
            var ql = query.toLowerCase();

            var headings = qsa('h1,h2,h3,h4,h5,h6', this._contentEl);
            headings.forEach(function (h) {
                var text = h.textContent.trim();
                if (text.toLowerCase().indexOf(ql) !== -1) {
                    results.push({ el: h, type: 'heading', text: text, score: text.toLowerCase() === ql ? 10 : 8 });
                }
            });

            var paras = qsa('p', this._contentEl);
            paras.forEach(function (p) {
                var text = p.textContent.trim();
                if (text.toLowerCase().indexOf(ql) !== -1) {
                    results.push({ el: p, type: 'paragraph', text: text, score: 3 });
                }
            });

            var listItems = qsa('li', this._contentEl);
            listItems.forEach(function (li) {
                var text = li.textContent.trim();
                if (text.toLowerCase().indexOf(ql) !== -1) {
                    results.push({ el: li, type: 'list-item', text: text, score: 2 });
                }
            });

            var cells = qsa('td, th', this._contentEl);
            cells.forEach(function (cell) {
                var text = cell.textContent.trim();
                if (text.toLowerCase().indexOf(ql) !== -1) {
                    results.push({ el: cell, type: 'table-cell', text: text, score: 1 });
                }
            });

            results.sort(function (a, b) { return b.score - a.score; });

            this._highlightAllMatches(query);

            if (results.length) {
                this._scrollToFirst();
            }

            return results;
        },

        _highlightAllMatches: function (query) {
            if (!query || query.length < MIN_QUERY || !this._contentEl) return;

            var textNodes = [];
            var walker = document.createTreeWalker(
                this._contentEl,
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
            var first = qs('.zuno-docs-highlight-first', this._contentEl);
            if (!first) return;
            var offset = _activeWrapper && _activeWrapper.classList.contains('zuno-docs-has-admin-bar') ? ADMIN_BAR_H + 20 : 20;
            var top = first.getBoundingClientRect().top + window.pageYOffset - offset - 10;
            window.scrollTo({ top: top, behavior: 'smooth' });
        }
    };

    /* ===================================================================
     * Reading Progress Bar
     * =================================================================== */
    function initReadingProgress(wrapEl) {
        var bar = qs('.zuno-docs-progress-bar-fill', wrapEl);
        if (!bar) return;

        var update = function () {
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            var docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            var progress = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
            bar.style.width = Math.min(100, Math.max(0, progress)) + '%';
        };

        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update, { passive: true });
        update();
    }

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
     * AJAX search fallback (for large indexes)
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
     * Admin bar offset + class
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
     * Hash handler — scoped to wrapper
     * =================================================================== */
    function handleInitialHash(wrapEl) {
        var hash = window.location.hash.slice(1);
        if (!hash) return;
        var target = qs('#' + CSS.escape(hash), wrapEl);
        if (!target) {
            target = document.getElementById(hash);
        }
        if (target) {
            setTimeout(function () {
                var offset = wrapEl.classList.contains('zuno-docs-has-admin-bar') ? ADMIN_BAR_H + 20 : 20;
                var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
                window.scrollTo({ top: top, behavior: 'smooth' });
                ScrollSpy.activateHeading(hash);
            }, 150);
        }
    }

    /* ===================================================================
     * Mobile TOC — floating card with overlay behavior
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

        function isMobile() {
            return window.innerWidth < 768;
        }

        function openToc() {
            if (!isMobile()) return;
            mobileToc.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            document.body.classList.add('zuno-docs-toc-open');

            /* If the panel is empty, clone the sidebar TOC + search into it */
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
                    /* Wire up the cloned TOC links to navigate */
                    qsa('.zuno-docs-toc-link', panelBody).forEach(function (link) {
                        link.addEventListener('click', function (e) {
                            e.preventDefault();
                            var id = link.dataset.tocId;
                            if (id) {
                                closeToc();
                                var target = qs('#' + CSS.escape(id), wrapEl);
                                if (target) {
                                    var offset = wrapEl.classList.contains('zuno-docs-has-admin-bar') ? ADMIN_BAR_H + 20 : 20;
                                    var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
                                    window.scrollTo({ top: top, behavior: 'smooth' });
                                    ScrollSpy.activateHeading(id);
                                }
                            }
                        });
                    });
                }
            }

            /* Sync search input from sidebar to panel */
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

        /* Close on Escape key */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && mobileToc.classList.contains('is-open')) {
                closeToc();
            }
        });

        /* Close TOC on TOC link click (smooth) */
        if (panelBody) {
            panelBody.addEventListener('click', function (e) {
                var link = e.target.closest('.zuno-docs-toc-link');
                if (link && isMobile()) {
                    var id = link.dataset.tocId;
                    if (id) {
                        closeToc();
                        var target = qs('#' + CSS.escape(id), wrapEl);
                        if (target) {
                            var offset = wrapEl.classList.contains('zuno-docs-has-admin-bar') ? ADMIN_BAR_H + 20 : 20;
                            setTimeout(function () {
                                var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
                                window.scrollTo({ top: top, behavior: 'smooth' });
                                ScrollSpy.activateHeading(id);
                            }, 250);
                        }
                    }
                }
            });
        }

        /* Scroll active heading into view within the TOC panel */
        var origUpdateActiveLinks = ScrollSpy._updateActiveLinks;
        ScrollSpy._updateActiveLinks = function (id) {
            origUpdateActiveLinks.call(ScrollSpy, id);
            if (isMobile() && panelBody && id) {
                var activeLink = qs('.zuno-docs-toc-link.is-active', panelBody);
                if (activeLink) {
                    activeLink.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            }
        };

        /* Close on resize to desktop */
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

        var maxDepth = parseInt(wrapEl.dataset.tocDepth, 10) || 6;

        /* 1. Build TOC */
        var headings = TocBuilder.build(contentEl, tocEl, maxDepth, wrapEl);

        /* 2. Scroll spy */
        ScrollSpy.init(headings, wrapEl);

        /* 3. Breadcrumbs */
        renderBreadcrumbs(wrapEl);

        /* 4. Page nav */
        renderPageNav(wrapEl);

        /* 5. Related articles */
        renderRelated(wrapEl);

        /* 6. Reading progress bar */
        initReadingProgress(wrapEl);

        /* 7. Search */
        initSearch(wrapEl);

        /* 8. Navigation */
        initNavigation(wrapEl);

        /* 9. Admin bar offset */
        initAdminBarOffset(wrapEl);

        /* 10. Mobile TOC */
        initMobileToc(wrapEl);

        /* 11. Handle initial hash */
        handleInitialHash(wrapEl);
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
