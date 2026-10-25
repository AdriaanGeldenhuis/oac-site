/* =====================================================================
   /admin/js/elders.js - Teaching of the Month editor
   ---------------------------------------------------------------------
   - One contenteditable per language, saved together
   - Paste: "with formatting" (keeps the look of Word / Google Docs /
     web pages, cleaned up) or "plain text" - chosen in the toolbar, and
     switchable right after a paste
   - Own undo/redo history per language, so every action (paste, AI,
     verse, sizes, spacing...) can be undone, not only typing
   - Local draft backup, so a crash / closed app never loses work
   ===================================================================== */
(function () {
    'use strict';

    // =================================================================
    // CONFIG + STATE
    // =================================================================
    let CONFIG = {};
    try {
        const dataEl = document.getElementById('eldersData');
        CONFIG = JSON.parse(dataEl ? dataEl.textContent : '{}') || {};
    } catch (e) {
        console.error('elders.js: invalid config', e);
    }
    const STR = CONFIG.str || {};
    const LANGS = CONFIG.supportedLangs || [];
    const CSRF = CONFIG.csrf || '';

    const $ = id => document.getElementById(id);

    const editors = {};
    LANGS.forEach(code => { editors[code] = $('editor-' + code); });

    let currentLang = editors.af ? 'af' : LANGS[0];
    const getCurrentEditor = () => editors[currentLang];

    const bibles = {};
    const savedHtml = {};      // per language: what the server has
    const lastRanges = {};     // per language: last selection inside the editor
    let hasChanges = false;
    let isSaving = false;
    let savedThisSession = false;
    let pasteMode = 'rich';
    let forcePlainUntil = 0;
    let lastPaste = null;
    let statusHold = 0;        // a flashed message stays until this time
    let statusTimer = 0;
    let draftTimer = 0;
    const DRAFT_KEY = CONFIG.draftKey || '';

    const store = {
        get(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } },
        set(k, v) { try { window.localStorage.setItem(k, v); } catch (e) { /* full / private mode */ } },
        del(k) { try { window.localStorage.removeItem(k); } catch (e) { /* ignore */ } }
    };

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    // =================================================================
    // DOM HELPERS
    // =================================================================

    // Blocks the formatting commands act on
    const BLOCK_TAGS = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'LI', 'BLOCKQUOTE', 'PRE', 'DIV', 'TD', 'TH'];

    // Everything that starts a new line (used for paste + structure)
    const BLOCK_LEVEL = new Set([
        'P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'PRE', 'DIV', 'UL', 'OL', 'LI',
        'HR', 'TABLE', 'THEAD', 'TBODY', 'TFOOT', 'TR', 'TD', 'TH', 'CAPTION', 'DL', 'DT', 'DD',
        'SECTION', 'ARTICLE', 'HEADER', 'FOOTER', 'MAIN', 'ASIDE', 'NAV', 'FIGURE', 'FIGCAPTION',
        'ADDRESS', 'CENTER', 'FIELDSET', 'DETAILS', 'SUMMARY'
    ]);

    const isBlockEl = n => !!n && n.nodeType === 1 && BLOCK_LEVEL.has(n.nodeName.toUpperCase());

    const INVISIBLE = /[\s\u00a0\u200b\ufeff]/g;

    // Anything a reader would actually see?
    function hasRealContent(node) {
        if (!node) return false;
        if (node.nodeType === 3) return node.nodeValue.replace(INVISIBLE, '') !== '';
        if (node.nodeType !== 1 && node.nodeType !== 11) return false;
        if (node.nodeType === 1 && /^(IMG|HR|TABLE)$/.test(node.nodeName)) return true;
        if (node.querySelector('img, hr, table')) return true;
        return node.textContent.replace(INVISIBLE, '') !== '';
    }

    // Text a reader would see - what counts as "this language has a
    // teaching" (same rule as teaching_has_content() on the server)
    function hasText(node) {
        return !!node && node.textContent.replace(INVISIBLE, '') !== '';
    }

    function unwrap(el) {
        const parent = el.parentNode;
        if (!parent) return;
        while (el.firstChild) parent.insertBefore(el.firstChild, el);
        parent.removeChild(el);
    }

    function closestBlock(node) {
        const editor = getCurrentEditor();
        while (node && node !== editor) {
            if (node.nodeType === 1 && BLOCK_TAGS.includes(node.tagName)) return node;
            node = node.parentNode;
        }
        return null;
    }

    function closestTag(node, tags, root) {
        while (node && node !== root) {
            if (node.nodeType === 1 && tags.includes(node.nodeName)) return node;
            node = node.parentNode;
        }
        return null;
    }

    function placeCaretAtEnd(node) {
        const sel = window.getSelection();
        const r = document.createRange();
        if (node.nodeType === 3) {
            r.setStart(node, node.length);
        } else if (/^(HR|IMG|BR|TABLE)$/.test(node.nodeName)) {
            r.setStartAfter(node);
        } else {
            let target = node;
            while (target.lastChild && target.lastChild.nodeType === 1 && !/^(BR|HR|IMG|TABLE)$/.test(target.lastChild.nodeName)) {
                target = target.lastChild;
            }
            if (target.lastChild && target.lastChild.nodeName === 'BR' && !hasRealContent(target)) {
                r.setStart(target, 0); // empty line: caret before the placeholder <br>
            } else if (target.lastChild && target.lastChild.nodeType === 3) {
                r.setStart(target.lastChild, target.lastChild.length);
            } else {
                r.selectNodeContents(target);
                r.collapse(false);
            }
        }
        r.collapse(true);
        sel.removeAllRanges();
        sel.addRange(r);
    }

    function placeCaretAtStart(node) {
        const sel = window.getSelection();
        const r = document.createRange();
        r.setStart(node, 0);
        r.collapse(true);
        sel.removeAllRanges();
        sel.addRange(r);
    }

    // =================================================================
    // EDITOR CONTENT: normalize, empty state, serialize
    // =================================================================

    // White ink (dark theme) and near-black ink (light theme) ARE the
    // theme defaults - storing them literally makes the text invisible
    // on the opposite theme. Strip them so the text adapts to any theme.
    const THEME_INK = [
        'rgb(255,255,255)', '#ffffff', '#fff', 'white', 'rgb(26,24,22)', '#1a1816',
        // the editor's own text colours (normal, bold, dim - both themes),
        // which Chrome copies into spans when blocks are merged or when you
        // type where formatted text was just deleted
        'rgb(245,235,230)', 'rgb(250,232,224)', '#fae8e0', 'rgb(200,192,186)',
        'rgb(17,24,39)', 'rgb(31,41,55)', 'rgb(90,100,116)', 'rgb(74,83,97)'
    ];

    function stripThemeInkColors(root) {
        root.querySelectorAll('[style*="color" i]').forEach(el => {
            const c = (el.style.color || '').toLowerCase().replace(/\s+/g, '');
            if (c && THEME_INK.includes(c)) {
                el.style.color = '';
                if (!el.getAttribute('style')) el.removeAttribute('style');
                if (el.tagName === 'SPAN' && !el.attributes.length) unwrap(el);
            }
        });
        root.querySelectorAll('font[color]').forEach(f => {
            const c = (f.getAttribute('color') || '').toLowerCase().replace(/\s+/g, '');
            if (THEME_INK.includes(c)) f.removeAttribute('color');
        });
    }

    // Chrome adds inline styles that only repeat the editor's defaults
    // (when merging blocks, after copy/paste inside the editor...). They
    // mean nothing, but would be saved - remove them.
    const JUNK_CSS = [
        'font-family', 'font-variant', 'font-variant-ligatures', 'font-variant-caps', 'font-variant-numeric',
        'font-variant-east-asian', 'font-feature-settings', 'font-kerning', 'font-stretch', 'letter-spacing',
        'word-spacing', 'orphans', 'widows', 'text-indent', 'text-transform', 'white-space', 'display',
        'float', 'caret-color', 'outline', 'box-sizing', 'text-decoration-thickness', 'text-decoration-style',
        'text-decoration-color', '-webkit-text-stroke-width', 'text-wrap', 'text-wrap-mode', 'text-wrap-style'
    ];

    function stripStyleJunk(root) {
        root.querySelectorAll('[style]').forEach(el => {
            const st = el.style;
            JUNK_CSS.forEach(prop => st.removeProperty(prop));
            if (el.tagName === 'SPAN' || el.tagName === 'FONT') {
                if (/^(1em|100%|16px|medium)$/.test(st.fontSize)) st.removeProperty('font-size');
                if (/^(300|400|normal)$/.test(st.fontWeight)) st.removeProperty('font-weight');
                if (st.fontStyle === 'normal') st.removeProperty('font-style');
                if (st.verticalAlign === 'baseline') st.removeProperty('vertical-align');
                if (/^(transparent|rgba\(0, 0, 0, 0\))$/.test(st.backgroundColor)) st.removeProperty('background-color');
                if (st.textDecorationLine === 'none' || st.textDecoration === 'none') {
                    st.removeProperty('text-decoration');
                    st.removeProperty('text-decoration-line');
                }
                st.removeProperty('line-height');
            }
            if (!el.getAttribute('style') || !el.getAttribute('style').trim()) el.removeAttribute('style');
            if (el.tagName === 'SPAN' && !el.attributes.length) unwrap(el);
        });
        root.querySelectorAll('font').forEach(f => { if (!f.attributes.length) unwrap(f); });
    }

    // Unwrap an indent wrapper, moving its left margin onto the blocks inside
    function liftIndent(wrapper, marginPx, align) {
        const parent = wrapper.parentNode;
        let run = null;
        Array.from(wrapper.childNodes).forEach(node => {
            if (isBlockEl(node)) {
                run = null;
                if (marginPx && node.nodeName !== 'HR') {
                    const cur = parseFloat(node.style.marginLeft) || 0;
                    node.style.marginLeft = Math.min(200, cur + marginPx) + 'px';
                }
                if (align && node.style && !node.style.textAlign) node.style.textAlign = align;
                parent.insertBefore(node, wrapper);
                return;
            }
            if (node.nodeType === 3 && !node.nodeValue.trim()) {
                node.remove();
                return;
            }
            if (!run) {
                run = document.createElement('p');
                if (marginPx) run.style.marginLeft = Math.min(200, marginPx) + 'px';
                if (align) run.style.textAlign = align;
                parent.insertBefore(run, wrapper);
            }
            run.appendChild(node);
        });
        wrapper.remove();
    }

    function ensureParagraph(ed) {
        const meaningful = Array.from(ed.childNodes).some(n =>
            (n.nodeType === 1 && n.nodeName !== 'BR') || (n.nodeType === 3 && n.nodeValue.trim()));
        if (!meaningful) {
            ed.innerHTML = '<p><br></p>';
        }
    }

    // After deleting everything the browser can leave an empty list /
    // heading / quote behind, so the next text lands in it. Start clean.
    // Also drops Chrome's "typing style": after select-all + delete it
    // would give the new text the colour of the deleted (bold) text.
    function resetIfEmptied(ed) {
        if (hasRealContent(ed)) return false;
        if (ed.innerHTML !== '<p><br></p>') ed.innerHTML = '<p><br></p>';
        placeCaretAtStart(ed.firstChild);
        return true;
    }

    // Make stored / restored content predictable to edit
    function normalizeEditor(ed) {
        stripThemeInkColors(ed);
        stripStyleJunk(ed);

        // Chrome's old "indent" made <blockquote style="margin:0 0 0 40px;
        // border:none; padding:0">, which shows as a quote box on the
        // welcome page. Turn it back into a real left margin.
        Array.from(ed.querySelectorAll('blockquote')).reverse().forEach(bq => {
            if (!/border\s*:\s*none/i.test(bq.getAttribute('style') || '')) return;
            liftIndent(bq, parseFloat(bq.style.marginLeft) || 40, '');
        });

        // Top-level <div>: a wrapper around blocks is unwrapped, a plain
        // line becomes a <p>
        Array.from(ed.children).forEach(child => {
            if (child.tagName !== 'DIV') return;
            if (Array.from(child.childNodes).some(isBlockEl)) {
                liftIndent(child, parseFloat(child.style.marginLeft) || 0, child.style.textAlign || '');
            } else {
                const p = document.createElement('p');
                while (child.firstChild) p.appendChild(child.firstChild);
                const style = child.getAttribute('style');
                if (style) p.setAttribute('style', style);
                child.replaceWith(p);
            }
        });

        // Stray inline nodes directly in the editor get a paragraph
        wrapLooseInline(ed);
        ensureParagraph(ed);

        // A teaching that ends with a divider / table: a line below it to
        // type on (not saved while it stays empty)
        if (ed.lastElementChild && /^(HR|TABLE)$/.test(ed.lastElementChild.nodeName)) {
            const p = document.createElement('p');
            p.appendChild(document.createElement('br'));
            ed.appendChild(p);
        }
    }

    // Placeholder (CSS) only while the editor is really empty
    function updateEmptyState(ed) {
        if (!ed) return;
        const kids = ed.children;
        const empty = !hasRealContent(ed) && kids.length <= 1 && (!kids[0] || kids[0].tagName === 'P');
        ed.classList.toggle('is-empty', empty);
    }

    // The HTML that gets saved / translated / compared
    function serializeEditor(ed) {
        if (!ed) return '';
        const clone = ed.cloneNode(true);
        stripThemeInkColors(clone);
        stripStyleJunk(clone);
        clone.querySelectorAll('p, h1, h2, h3, h4, h5, h6, li, blockquote, td, th').forEach(b => {
            const last = b.lastChild;
            if (last && last.nodeName === 'BR' && b.childNodes.length > 1) last.remove();
        });
        const isJunk = n => n.nodeType === 3
            ? !n.nodeValue.trim()
            : (n.nodeType !== 1 || (n.nodeName !== 'HR' && !hasRealContent(n)));
        while (clone.firstChild && isJunk(clone.firstChild)) clone.firstChild.remove();
        while (clone.lastChild && isJunk(clone.lastChild)) clone.lastChild.remove();
        fixStructure(clone);
        wrapLooseInline(clone);
        // <p></p> without a <br> has no height in the editor - don't let it
        // turn into a gap on the welcome page (<p><br></p> = a real blank line)
        clone.querySelectorAll('p').forEach(p => { if (p.innerHTML.trim() === '') p.remove(); });
        return hasText(clone) ? clone.innerHTML.trim() : '';
    }

    // Text sitting directly in the editor (not in a block) gets a paragraph
    function wrapLooseInline(root) {
        let wrap = null;
        Array.from(root.childNodes).forEach(node => {
            if (isBlockEl(node)) {
                wrap = null;
                return;
            }
            if (node.nodeType === 8) {
                node.remove();
                return;
            }
            if (node.nodeType === 3 && !node.nodeValue.trim()) return;
            if (!wrap) {
                wrap = document.createElement('p');
                root.insertBefore(wrap, node);
            }
            wrap.appendChild(node);
        });
    }

    // The server parses with libxml (HTML4 rules), which tears apart some
    // structures the browser happily builds while editing:
    //   <ol><li>a</li><ul>..</ul></ol>  (Chrome's list indent)
    //   <p>..<ol>..</ol>..</p>          (list commands inside a paragraph)
    //   <b><p>..</p></b>                (formatting around a block)
    // Rewrite them into the equivalent valid HTML before saving.
    const STRUCT_BLOCKS = /^(P|H[1-6]|UL|OL|LI|BLOCKQUOTE|PRE|DIV|TABLE|HR)$/;
    const STRUCT_INLINE = /^(B|STRONG|I|EM|U|S|STRIKE|DEL|INS|SUB|SUP|SPAN|FONT|A|SMALL|MARK|CODE)$/;

    function fixStructure(root) {
        // 1. Inline formatting wrapped around blocks: push it into the blocks
        Array.from(root.querySelectorAll('*')).reverse().forEach(el => {
            if (!STRUCT_INLINE.test(el.nodeName) || !el.parentNode) return;
            if (!Array.from(el.childNodes).some(n => n.nodeType === 1 && STRUCT_BLOCKS.test(n.nodeName))) return;
            const parent = el.parentNode;
            let run = null;
            Array.from(el.childNodes).forEach(n => {
                if (n.nodeType === 1 && STRUCT_BLOCKS.test(n.nodeName)) {
                    run = null;
                    const target = /^(UL|OL)$/.test(n.nodeName) ? Array.from(n.querySelectorAll('li')) : [n];
                    target.forEach(t => {
                        if (t.nodeName === 'HR' || t.nodeName === 'TABLE') return;
                        const w = el.cloneNode(false);
                        while (t.firstChild) w.appendChild(t.firstChild);
                        t.appendChild(w);
                    });
                    parent.insertBefore(n, el);
                } else {
                    if (!run) {
                        run = el.cloneNode(false);
                        parent.insertBefore(run, el);
                    }
                    run.appendChild(n);
                }
            });
            el.remove();
        });

        // 2. Blocks inside a paragraph / heading: split the paragraph around them
        Array.from(root.querySelectorAll('p, h1, h2, h3, h4, h5, h6')).reverse().forEach(p => {
            if (!p.parentNode) return;
            if (!Array.from(p.childNodes).some(n => n.nodeType === 1 && STRUCT_BLOCKS.test(n.nodeName))) return;
            const parent = p.parentNode;
            const runs = [];
            let run = null;
            Array.from(p.childNodes).forEach(n => {
                if (n.nodeType === 1 && STRUCT_BLOCKS.test(n.nodeName)) {
                    run = null;
                    // a list made inside an indented / centred paragraph keeps that look
                    if (/^(UL|OL|BLOCKQUOTE|TABLE)$/.test(n.nodeName)) {
                        ['marginLeft', 'textAlign', 'lineHeight', 'fontSize'].forEach(prop => {
                            if (p.style[prop] && !n.style[prop]) n.style[prop] = p.style[prop];
                        });
                    }
                    parent.insertBefore(n, p);
                } else {
                    if (!run) {
                        run = p.cloneNode(false);
                        parent.insertBefore(run, p);
                        runs.push(run);
                    }
                    run.appendChild(n);
                }
            });
            p.remove();
            runs.forEach(r => { if (!hasRealContent(r)) r.remove(); });
        });

        // 3. A list directly inside a list belongs to the item before it
        Array.from(root.querySelectorAll('ul, ol')).forEach(list => {
            const parent = list.parentNode;
            if (!parent || !/^(UL|OL)$/.test(parent.nodeName)) return;
            let prev = list.previousElementSibling;
            if (!prev || prev.nodeName !== 'LI') {
                prev = document.createElement('li');
                parent.insertBefore(prev, list);
            }
            prev.appendChild(list);
        });

        // 4. Anything else loose in a list goes into an item
        Array.from(root.querySelectorAll('ul, ol')).forEach(list => {
            Array.from(list.childNodes).forEach(n => {
                if (n.nodeType === 1 && n.nodeName === 'LI') return;
                if (n.nodeType === 3 && !n.nodeValue.trim()) return;
                const li = document.createElement('li');
                list.insertBefore(li, n);
                li.appendChild(n);
            });
        });

        // 5. Items outside any list get one
        Array.from(root.querySelectorAll('li')).forEach(li => {
            if (!li.parentNode || /^(UL|OL)$/.test(li.parentNode.nodeName)) return;
            const ul = document.createElement('ul');
            li.parentNode.insertBefore(ul, li);
            while (ul.nextSibling && ul.nextSibling.nodeName === 'LI') ul.appendChild(ul.nextSibling);
        });
    }

    // ---------- caret kept by text position ----------
    // DOM paths break when nodes move; the text before the caret does not.
    function textOffset(ed, container, offset) {
        const r = document.createRange();
        r.setStart(ed, 0);
        try { r.setEnd(container, offset); } catch (e) { return 0; }
        return r.toString().length;
    }

    function saveCaret(ed) {
        const sel = window.getSelection();
        if (!sel || !sel.rangeCount) return null;
        const r = sel.getRangeAt(0);
        if (!ed.contains(r.startContainer) || !ed.contains(r.endContainer)) return null;
        const blk = closestTag(r.startContainer, BLOCK_TAGS, ed);
        return {
            s: textOffset(ed, r.startContainer, r.startOffset),
            e: textOffset(ed, r.endContainer, r.endOffset),
            collapsed: r.collapsed,
            emptyBlock: blk && !hasText(blk) ? blk : null,
            atEnd: r.collapsed && textOffset(ed, r.startContainer, r.startOffset) >= ed.textContent.length,
            // at a block boundary the caret belongs to the start of the next text
            preferLater: r.startOffset === 0
        };
    }

    function pointAt(ed, n, preferLater) {
        const w = document.createTreeWalker(ed, NodeFilter.SHOW_TEXT);
        let node, pos = 0, candidate = null;
        while ((node = w.nextNode())) {
            const len = node.nodeValue.length;
            if (candidate && n === pos) return [node, 0];
            if (n < pos + len) return [node, n - pos];
            if (n === pos + len) {
                if (!preferLater) return [node, len];
                candidate = [node, len];
            }
            pos += len;
        }
        return candidate;
    }

    function restoreCaret(ed, c) {
        if (!c) return;
        const sel = window.getSelection();
        const r = document.createRange();
        if (c.emptyBlock && c.emptyBlock.isConnected && ed.contains(c.emptyBlock)) {
            r.setStart(c.emptyBlock, 0);
            r.collapse(true);
        } else if (c.atEnd) {
            placeCaretAtEnd(ed);
            return;
        } else {
            const a = pointAt(ed, c.s, c.preferLater);
            if (!a) { placeCaretAtEnd(ed); return; }
            r.setStart(a[0], a[1]);
            const b = c.collapsed ? a : pointAt(ed, c.e, false);
            if (b) r.setEnd(b[0], b[1]); else r.collapse(true);
        }
        sel.removeAllRanges();
        sel.addRange(r);
    }

    // ---------- live tidy ----------
    // Native editing (Backspace/Delete merges, dragging text, typing where
    // formatted text was deleted) makes Chrome add inline styles that copy
    // the old look: background boxes, heading sizes, theme colours. Those
    // elements are new, so they are recognised by not having existed before
    // the edit, and cleaned - the elder sees exactly what will be saved.
    const KEEP_BLOCK_CSS = ['text-align', 'margin-left', 'line-height', 'font-size'];
    let elementsBefore = null;

    function rememberElements(ed) {
        elementsBefore = new WeakSet(ed.querySelectorAll('*'));
    }

    function tidyNewElements(ed, inputType) {
        const before = elementsBefore;
        elementsBefore = null;
        if (!before) return false;
        const fresh = Array.from(ed.querySelectorAll('*')).filter(el => !before.has(el));
        if (!fresh.length) return false;

        // Merges and drops copy the look of where the text came from: drop it
        const moved = /^(delete|insertFromDrop|insertReplacementText)/.test(inputType || '');
        const caret = saveCaret(ed);
        let moves = false;

        fresh.forEach(el => {
            if (!el.isConnected) return;
            if ((el.nodeName === 'SPAN' || el.nodeName === 'FONT') && moved) {
                unwrap(el);
                moves = true;
                return;
            }
            if (isBlockEl(el) && el.hasAttribute('style')) {
                const keep = KEEP_BLOCK_CSS.filter(prop => el.style.getPropertyValue(prop))
                    .map(prop => [prop, el.style.getPropertyValue(prop)]);
                el.removeAttribute('style');
                keep.forEach(([prop, val]) => {
                    if (prop === 'margin-left' && !parseFloat(val)) return;
                    el.style.setProperty(prop, val);
                });
                if (!el.getAttribute('style')) el.removeAttribute('style');
            }
        });

        // Typing-style leftovers (theme colours, default sizes) in new spans/fonts
        fresh.forEach(el => {
            if (!el.isConnected || (el.nodeName !== 'SPAN' && el.nodeName !== 'FONT')) return;
            const c = (el.style.color || el.getAttribute('color') || '').toLowerCase().replace(/\s+/g, '');
            if (c && THEME_INK.includes(c)) {
                el.style.color = '';
                el.removeAttribute('color');
            }
            JUNK_CSS.forEach(prop => el.style.removeProperty(prop));
            if (!el.getAttribute('style')) el.removeAttribute('style');
            if (!el.attributes.length) {
                unwrap(el);
                moves = true;
            }
        });
        if (moves) restoreCaret(ed, caret);
        return true;
    }

    // Structure the browser builds that the saved HTML would not keep
    // (lists inside paragraphs, a list directly in a list, loose text,
    // nothing to type into after a final divider) - fixed in the editor too
    function tidyStructure(ed) {
        const probe = ed.cloneNode(true);
        fixStructure(probe);
        wrapLooseInline(probe);
        const needsEnd = ed.lastElementChild && /^(HR|TABLE)$/.test(ed.lastElementChild.nodeName);
        if (probe.innerHTML === ed.innerHTML && !needsEnd) return false;
        const caret = saveCaret(ed);
        if (probe.innerHTML !== ed.innerHTML) {
            fixStructure(ed);
            wrapLooseInline(ed);
        }
        if (ed.lastElementChild && /^(HR|TABLE)$/.test(ed.lastElementChild.nodeName)) {
            const p = document.createElement('p');
            p.appendChild(document.createElement('br'));
            ed.appendChild(p);
        }
        restoreCaret(ed, caret);
        return true;
    }

    function editorHasContent(code) {
        return hasText(editors[code]);
    }

    // Green dot on tabs that already have real content
    function updateTabIndicators() {
        document.querySelectorAll('.lang-tab').forEach(tab => {
            tab.classList.toggle('filled', editorHasContent(tab.dataset.lang));
        });
    }

    // Our own / AI / draft HTML: keep everything except anything that runs
    const DROP_TAGS = new Set([
        'SCRIPT', 'STYLE', 'META', 'LINK', 'TITLE', 'HEAD', 'NOSCRIPT', 'TEMPLATE', 'IFRAME', 'FRAME',
        'OBJECT', 'EMBED', 'APPLET', 'SVG', 'MATH', 'CANVAS', 'VIDEO', 'AUDIO', 'SOURCE', 'TRACK',
        'INPUT', 'BUTTON', 'SELECT', 'TEXTAREA', 'OPTION', 'DATALIST', 'FORM', 'MAP', 'XML',
        'COLGROUP', 'COL', 'BASE'
    ]);

    function sanitizeOwnHtml(html) {
        const doc = new DOMParser().parseFromString('<!DOCTYPE html><html><body>' + String(html || '') + '</body></html>', 'text/html');
        doc.body.querySelectorAll(Array.from(DROP_TAGS).map(t => t.toLowerCase()).join(',') + ',img,picture').forEach(el => el.remove());
        doc.body.querySelectorAll('*').forEach(el => {
            Array.from(el.attributes).forEach(a => {
                const n = a.name.toLowerCase();
                if (n.indexOf('on') === 0 || n === 'srcdoc' || n === 'formaction') {
                    el.removeAttribute(a.name);
                } else if ((n === 'href' || n === 'src' || n === 'xlink:href') &&
                           /^(javascript|vbscript|data|file):/.test(a.value.replace(/[\x00-\x20]+/g, '').toLowerCase())) {
                    el.removeAttribute(a.name);
                }
            });
        });
        return doc.body.innerHTML;
    }

    // =================================================================
    // HISTORY - own undo/redo per language
    // The browser's native undo can't undo DOM changes made by script
    // (paste cleaning, AI, verse, sizes, spacing...), so every change is
    // snapshotted here instead.
    // =================================================================
    const HISTORY_MAX = 150;
    const histories = {};

    function hist(code) {
        if (!histories[code]) histories[code] = { stack: [], index: -1, timer: 0 };
        return histories[code];
    }

    function nodePath(root, node) {
        const path = [];
        while (node && node !== root) {
            const parent = node.parentNode;
            if (!parent) return null;
            path.unshift(Array.prototype.indexOf.call(parent.childNodes, node));
            node = parent;
        }
        return node === root ? path : null;
    }

    function nodeFromPath(root, path) {
        let n = root;
        for (let i = 0; i < path.length; i++) {
            n = n.childNodes[path[i]];
            if (!n) return null;
        }
        return n;
    }

    const maxOffset = n => (n.nodeType === 3 ? n.length : n.childNodes.length);

    function captureSelection(ed) {
        const sel = window.getSelection();
        if (!sel || !sel.rangeCount) return null;
        const r = sel.getRangeAt(0);
        if (!ed.contains(r.startContainer) || !ed.contains(r.endContainer)) return null;
        const sp = nodePath(ed, r.startContainer);
        const ep = nodePath(ed, r.endContainer);
        if (!sp || !ep) return null;
        return { sp, so: r.startOffset, ep, eo: r.endOffset };
    }

    function applySelection(ed, s) {
        const sel = window.getSelection();
        if (s) {
            try {
                const sn = nodeFromPath(ed, s.sp);
                const en = nodeFromPath(ed, s.ep);
                if (sn && en) {
                    const r = document.createRange();
                    r.setStart(sn, Math.min(s.so, maxOffset(sn)));
                    r.setEnd(en, Math.min(s.eo, maxOffset(en)));
                    sel.removeAllRanges();
                    sel.addRange(r);
                    return;
                }
            } catch (e) { /* fall through */ }
        }
        placeCaretAtEnd(ed);
    }

    // Store the current state as a step (no-op when nothing changed)
    function recordHistory(code) {
        const ed = editors[code];
        if (!ed) return false;
        const h = hist(code);
        clearTimeout(h.timer);
        h.timer = 0;
        const html = ed.innerHTML;
        const sel = captureSelection(ed);
        const cur = h.stack[h.index];
        if (cur && cur.html === html) {
            if (sel) cur.sel = sel;
            updateHistoryButtons();
            return false;
        }
        h.stack.length = h.index + 1;
        h.stack.push({ html, sel });
        if (h.stack.length > HISTORY_MAX) h.stack.shift();
        h.index = h.stack.length - 1;
        updateHistoryButtons();
        return true;
    }

    // Typing is grouped into one step per pause
    function scheduleHistory(code) {
        const h = hist(code);
        clearTimeout(h.timer);
        h.timer = setTimeout(() => recordHistory(code), 600);
        updateHistoryButtons();
    }

    function restoreSnapshot(code, snap, keepPastePop) {
        const ed = editors[code];
        ed.innerHTML = snap.html;
        try { ed.focus({ preventScroll: true }); } catch (e) { ed.focus(); }
        applySelection(ed, snap.sel);
        if (!keepPastePop) hidePastePop();
        afterContentChange(code);
    }

    function undo() {
        const code = currentLang;
        const h = hist(code);
        recordHistory(code); // pending typing becomes its own step first
        if (h.index <= 0) return;
        h.index--;
        restoreSnapshot(code, h.stack[h.index]);
        updateHistoryButtons();
    }

    function redo() {
        const code = currentLang;
        const h = hist(code);
        recordHistory(code);
        if (h.index >= h.stack.length - 1) return;
        h.index++;
        restoreSnapshot(code, h.stack[h.index]);
        updateHistoryButtons();
    }

    function updateHistoryButtons() {
        const h = hist(currentLang);
        const u = $('btn-undo');
        const r = $('btn-redo');
        if (u) u.disabled = !(h.index > 0 || h.timer);
        if (r) r.disabled = !(h.index < h.stack.length - 1) || !!h.timer;
    }

    // =================================================================
    // SELECTION
    // =================================================================

    // {sel, range, editor} when the selection lives inside the visible
    // editor, otherwise null
    function selectionInEditor() {
        const sel = window.getSelection();
        if (!sel || !sel.rangeCount) return null;
        const range = sel.getRangeAt(0);
        const editor = getCurrentEditor();
        if (!editor || !editor.contains(range.startContainer) || !editor.contains(range.endContainer)) return null;
        return { sel, range, editor };
    }

    // Focus the editor and bring back the elder's last selection - a
    // toolbar <select>, prompt or modal may have taken it away
    function focusEditor() {
        const ed = getCurrentEditor();
        if (!ed) return null;
        const saved = lastRanges[currentLang];
        if (document.activeElement !== ed) {
            try { ed.focus({ preventScroll: true }); } catch (e) { ed.focus(); }
            if (saved && ed.contains(saved.startContainer) && ed.contains(saved.endContainer)) {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(saved);
            }
        }
        if (!selectionInEditor()) placeCaretAtEnd(ed);
        return selectionInEditor();
    }

    // True only when the range actually overlaps the block's content,
    // not when it merely touches a boundary (e.g. triple-click selections
    // that end at the start of the next paragraph)
    function rangeOverlapsBlock(range, blk) {
        const r = document.createRange();
        r.selectNodeContents(blk);
        return range.compareBoundaryPoints(Range.START_TO_END, r) > 0 &&
               range.compareBoundaryPoints(Range.END_TO_START, r) < 0;
    }

    // All innermost block elements covered by the current selection
    function getSelectedBlocks() {
        const ctx = selectionInEditor();
        if (!ctx) return [];
        const candidates = [];
        ctx.editor.querySelectorAll(BLOCK_TAGS.join(',')).forEach(blk => {
            if (rangeOverlapsBlock(ctx.range, blk)) candidates.push(blk);
        });
        const blocks = candidates.filter(b => !candidates.some(o => o !== b && b.contains(o)));
        if (!blocks.length) {
            const blk = closestBlock(ctx.sel.anchorNode);
            if (blk) blocks.push(blk);
        }
        return blocks;
    }

    // Commands that need a selection get the whole current block when
    // the cursor is just sitting somewhere - so clicking a color or size
    // always visibly does something
    function expandToBlockIfCollapsed(sel) {
        if (!sel.isCollapsed) return;
        const blk = closestBlock(sel.anchorNode);
        if (!blk) return;
        const r = document.createRange();
        r.selectNodeContents(blk);
        sel.removeAllRanges();
        sel.addRange(r);
    }

    // =================================================================
    // CHANGE TRACKING
    // =================================================================
    let changeTimer = 0;

    function isDirty() {
        return LANGS.some(c => serializeEditor(editors[c]) !== (savedHtml[c] || ''));
    }

    function afterContentChange(code) {
        updateEmptyState(editors[code]);
        clearTimeout(changeTimer);
        changeTimer = setTimeout(() => {
            hasChanges = isDirty();
            refreshStatus();
            updateTabIndicators();
            scheduleDraft();
        }, 250);
        updateToolbarState();
        updateHistoryButtons();
    }

    // Wrap every scripted edit: one undo step, focus + selection restored
    function runEdit(fn) {
        const code = currentLang;
        const ctx = focusEditor();
        if (!ctx) return;
        recordHistory(code);
        hidePastePop();
        fn(ctx);
        tidyStructure(ctx.editor);
        recordHistory(code);
        afterContentChange(code);
    }

    function execCmd(cmd, val) {
        runEdit(() => { document.execCommand(cmd, false, val === undefined ? null : val); });
    }

    // =================================================================
    // PASTE - "with formatting" or "plain text"
    // =================================================================

    // ---------- colour helpers ----------
    const colorCtx = (() => {
        try { return document.createElement('canvas').getContext('2d'); } catch (e) { return null; }
    })();

    function parseColor(value) {
        let v = String(value || '').trim().toLowerCase();
        if (!v || /^(transparent|inherit|initial|unset|currentcolor|windowtext|auto|none)$/.test(v)) return null;
        if (colorCtx) {
            colorCtx.fillStyle = '#010203';
            colorCtx.fillStyle = v;
            const out = String(colorCtx.fillStyle);
            if (out === '#010203' && v !== '#010203') return null;
            v = out;
        }
        let m = v.match(/^#([0-9a-f]{6})$/);
        if (m) {
            return { r: parseInt(m[1].slice(0, 2), 16), g: parseInt(m[1].slice(2, 4), 16), b: parseInt(m[1].slice(4, 6), 16), a: 1 };
        }
        m = v.match(/^#([0-9a-f]{3})$/);
        if (m) {
            return { r: parseInt(m[1][0] + m[1][0], 16), g: parseInt(m[1][1] + m[1][1], 16), b: parseInt(m[1][2] + m[1][2], 16), a: 1 };
        }
        m = v.match(/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*(?:,\s*([\d.]+)\s*)?\)$/);
        if (m) {
            return { r: +m[1], g: +m[2], b: +m[3], a: m[4] === undefined ? 1 : +m[4] };
        }
        return null;
    }

    function colorHsl(c) {
        const r = c.r / 255, g = c.g / 255, b = c.b / 255;
        const max = Math.max(r, g, b), min = Math.min(r, g, b);
        const l = (max + min) / 2;
        const d = max - min;
        const s = d === 0 ? 0 : d / (1 - Math.abs(2 * l - 1));
        return { s, l };
    }

    function relLum(c) {
        const f = v => {
            v /= 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        };
        return 0.2126 * f(c.r) + 0.7152 * f(c.g) + 0.0722 * f(c.b);
    }

    // The app's own text colours: both pages remap them per theme, so
    // they always stay (e.g. copied from another teaching)
    const PALETTE_INK = ['232,180,168', '201,148,133', '245,213,200', '184,184,184', '61,56,53',
        '243,195,177', '183,110,121', '192,192,192'];
    const CARD_DARK = { r: 23, g: 21, b: 20 };   // welcome teaching card, dark theme
    const CARD_LIGHT = { r: 255, g: 255, b: 255 };

    function contrast(a, b) {
        const x = relLum(a), y = relLum(b);
        return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
    }

    // Black, white and greys are just "normal text" in the source - the
    // theme decides those. A real colour stays only if it can still be read
    // on BOTH the dark and the light card (navy vanishes on dark, yellow on
    // white - those become normal text).
    function keepTextColor(c) {
        if (c.a < 0.35) return false;
        if (PALETTE_INK.includes(Math.round(c.r) + ',' + Math.round(c.g) + ',' + Math.round(c.b))) return true;
        return colorHsl(c).s >= 0.2 && contrast(c, CARD_DARK) >= 2.5 && contrast(c, CARD_LIGHT) >= 2.5;
    }

    // Highlights stay (softened so text reads on both themes); white /
    // grey page backgrounds go
    function keepBackground(c) {
        if (c.a < 0.08) return false;
        const hsl = colorHsl(c);
        return hsl.s >= 0.15 && hsl.l <= 0.94 && hsl.l >= 0.08;
    }

    // ---------- size helpers ----------
    const LEGACY_FONT_PX = [0, 10, 13, 16, 18, 24, 32, 48];
    const SIZE_WORDS = { 'xx-small': 9, 'x-small': 10, small: 13, medium: 16, large: 18, 'x-large': 24, 'xx-large': 32, 'xxx-large': 48 };

    function cssLengthPx(v, emPx) {
        const m = String(v || '').trim().toLowerCase().match(/^(-?[\d.]+)(px|pt|in|cm|mm|em|rem|%)?$/);
        if (!m) return 0;
        const n = parseFloat(m[1]);
        switch (m[2]) {
            case 'pt': return n * 4 / 3;
            case 'in': return n * 96;
            case 'cm': return n * 37.8;
            case 'mm': return n * 3.78;
            case 'em': return n * emPx;
            case 'rem': return n * 16;
            case '%': return n * emPx / 100;
            default: return n;
        }
    }

    function fontPxOf(el, parentPx) {
        const tag = el.nodeName.toUpperCase();
        let v = (el.style && el.style.fontSize) || '';
        if (!v && tag === 'FONT' && el.getAttribute('size')) {
            const raw = el.getAttribute('size').trim();
            let n = parseInt(raw, 10) || 0;
            if (/^[+-]/.test(raw)) n = 3 + n;
            return LEGACY_FONT_PX[Math.max(1, Math.min(7, n || 3))];
        }
        if (!v) {
            if (tag === 'SMALL') return parentPx * 0.83;
            if (tag === 'BIG') return parentPx * 1.2;
            return 0;
        }
        v = v.trim().toLowerCase();
        if (SIZE_WORDS[v]) return SIZE_WORDS[v];
        if (v === 'smaller') return parentPx / 1.2;
        if (v === 'larger') return parentPx * 1.2;
        return cssLengthPx(v, parentPx);
    }

    const round2 = n => Math.round(n * 100) / 100;

    function emFor(px, relativeTo) {
        const ratio = px / relativeTo;
        if (!isFinite(ratio) || Math.abs(ratio - 1) <= 0.06) return '';
        return round2(Math.min(3, Math.max(0.6, ratio))) + 'em';
    }

    function isHiddenEl(el) {
        const st = el.style;
        if (st && (st.display === 'none' || st.visibility === 'hidden')) return true;
        return /mso-hide\s*:\s*all/i.test(el.getAttribute('style') || '');
    }

    function blockAlign(el) {
        const a = String((el.style && el.style.textAlign) || el.getAttribute('align') || '').toLowerCase();
        if (el.nodeName.toUpperCase() === 'CENTER') return 'center';
        return /^(center|right|justify)$/.test(a) ? a : '';
    }

    function blockIndentPx(el) {
        const px = cssLengthPx(el.style ? el.style.marginLeft : '', 16);
        if (!px || px < 10) return 0;
        return Math.min(200, Math.round(px / 4) * 4);
    }

    function safeHref(href) {
        const h = String(href || '').trim();
        if (!h) return '';
        const probe = h.replace(/[\x00-\x20]+/g, '').toLowerCase();
        if (/^(https?:\/\/|mailto:|tel:)/.test(probe)) return h;
        return '';
    }

    // ---------- Word desktop lists ----------
    // Word puts list items in <p style="mso-list:l0 level1 lfo1"> with the
    // number/bullet as text in a mso-list:Ignore span. Rebuild real lists.
    function convertWordLists(doc) {
        const isListPara = p => /mso-list\s*:\s*l\d+/i.test(p.getAttribute('style') || '');

        const info = p => {
            const style = p.getAttribute('style') || '';
            const level = parseInt((style.match(/level(\d+)/i) || [])[1] || '1', 10) || 1;
            let marker = '';
            const ignore = Array.from(p.querySelectorAll('[style]'))
                .find(el => /mso-list\s*:\s*ignore/i.test(el.getAttribute('style') || ''));
            if (ignore) {
                marker = ignore.textContent.replace(/[\s\u00a0]+/g, ' ').trim();
                ignore.remove();
            }
            let type = 'ul', olType = '', start = 1;
            // "1.1." / "2.3.1" (multi-level) counts as decimal numbering too
            const m = marker.match(/^\(?([0-9]+|[a-zA-Z]{1,4})[.)]$/) || marker.match(/^([0-9]+)$/)
                || (/^\d+(\.\d+)+\.?$/.test(marker) ? [marker, '1'] : null);
            if (m) {
                type = 'ol';
                const t = m[1];
                if (/^\d+$/.test(t)) { olType = '1'; start = parseInt(t, 10); }
                else if (/^(i|ii|iii|iv|v|vi|vii|viii|ix|x)$/.test(t)) { olType = 'i'; }
                else if (/^(I|II|III|IV|V|VI|VII|VIII|IX|X)$/.test(t)) { olType = 'I'; }
                else if (/^[a-z]$/.test(t)) { olType = 'a'; start = t.charCodeAt(0) - 96; }
                else if (/^[A-Z]$/.test(t)) { olType = 'A'; start = t.charCodeAt(0) - 64; }
                else { type = 'ul'; }
            }
            return { level, type, olType, start };
        };

        // Numbered headings ("1. Inleiding") stay headings: keep the number,
        // drop Word's run of spacing after it
        doc.body.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach(h => {
            if (!isListPara(h)) return;
            const ignore = Array.from(h.querySelectorAll('[style]'))
                .find(el => /mso-list\s*:\s*ignore/i.test(el.getAttribute('style') || ''));
            if (ignore) ignore.replaceWith(doc.createTextNode(ignore.textContent.replace(/[\s\u00a0]+/g, ' ').trim() + ' '));
        });

        const paras = Array.from(doc.body.querySelectorAll('p'));
        const done = new Set();

        paras.forEach(first => {
            if (done.has(first) || !isListPara(first)) return;
            const run = [first];
            let next = first.nextElementSibling;
            while (next && next.nodeName === 'P' && isListPara(next)) {
                run.push(next);
                next = next.nextElementSibling;
            }
            run.forEach(p => done.add(p));

            const roots = [];
            const stack = [];
            run.forEach(p => {
                const it = info(p);
                const li = doc.createElement('li');
                while (p.firstChild) li.appendChild(p.firstChild);

                while (stack.length && stack[stack.length - 1].level > it.level) stack.pop();
                let top = stack[stack.length - 1];
                if (top && top.level === it.level && top.list.nodeName.toLowerCase() !== it.type) {
                    stack.pop();
                    top = stack[stack.length - 1];
                }
                if (!top || top.level < it.level) {
                    const list = doc.createElement(it.type);
                    if (it.type === 'ol') {
                        if (it.olType && it.olType !== '1') list.setAttribute('type', it.olType);
                        if (it.start > 1) list.setAttribute('start', String(it.start));
                    }
                    if (top) {
                        let parentLi = top.list.lastElementChild;
                        if (!parentLi) parentLi = top.list.appendChild(doc.createElement('li'));
                        parentLi.appendChild(list);
                    } else {
                        roots.push(list);
                    }
                    stack.push({ level: it.level, list });
                    top = stack[stack.length - 1];
                }
                top.list.appendChild(li);
            });

            roots.forEach(list => first.parentNode.insertBefore(list, first));
            run.forEach(p => p.remove());
        });
    }

    // ---------- "With formatting" ----------
    // The source is flattened into text runs that carry their effective
    // formatting, then rebuilt with clean tags. That is what keeps the
    // look (bold, italic, underline, colours, highlights, sizes, headings,
    // lists, tables, alignment) while dropping Word / Docs / web junk.

    const HEADING_MAP = { H1: 'h1', H2: 'h2', H3: 'h3', H4: 'h3', H5: 'h3', H6: 'h3' };
    const CONTAINERS = new Set([
        'P', 'DIV', 'SECTION', 'ARTICLE', 'HEADER', 'FOOTER', 'MAIN', 'ASIDE', 'NAV', 'FIGURE',
        'FIGCAPTION', 'ADDRESS', 'CENTER', 'FIELDSET', 'DETAILS', 'SUMMARY', 'PRE', 'DT', 'DD', 'BODY'
    ]);

    const isItem = x => x && !x.nodeType;

    function dominantFontPx(root) {
        const weights = new Map();
        (function walk(el, px) {
            Array.from(el.childNodes).forEach(n => {
                if (n.nodeType === 3) {
                    const len = n.nodeValue.replace(/\s+/g, '').length;
                    if (len) {
                        const k = Math.round(px * 2) / 2;
                        weights.set(k, (weights.get(k) || 0) + len);
                    }
                } else if (n.nodeType === 1) {
                    const tag = n.nodeName.toUpperCase();
                    if (DROP_TAGS.has(tag) || HEADING_MAP[tag]) return;
                    walk(n, fontPxOf(n, px) || px);
                }
            });
        })(root, 16);
        let best = 16, bestW = -1;
        weights.forEach((w, k) => {
            if (w > bestW) { best = k; bestW = w; }
        });
        return best || 16;
    }

    function fmtKey(f, blockPx, heading) {
        if (heading) return [f.em, f.u, f.s, f.sup, f.sub].join('|');
        return [f.strong, f.em, f.u, f.s, f.sup, f.sub, f.color, f.bg, (f.sup || f.sub) ? '' : emFor(f.px, blockPx)].join('|');
    }

    // The colour span goes INSIDE <strong>/<em>: bold has its own colour
    // in the stylesheet, which would otherwise win over the pasted colour
    function wrapRun(doc, text, f, blockPx, heading) {
        let node = doc.createTextNode(text);
        if (!heading) {
            const style = [];
            if (f.color) style.push('color: ' + f.color);
            if (f.bg) style.push('background-color: ' + f.bg);
            const em = (f.sup || f.sub) ? '' : emFor(f.px, blockPx);
            if (em) style.push('font-size: ' + em);
            if (style.length) {
                const s = doc.createElement('span');
                s.setAttribute('style', style.join('; '));
                s.appendChild(node);
                node = s;
            }
        }
        ['sub', 'sup', 's', 'u', 'em', 'strong'].forEach(m => {
            if (!f[m] || (heading && m === 'strong')) return;
            const w = doc.createElement(m);
            w.appendChild(node);
            node = w;
        });
        return node;
    }

    // Inline items -> clean DOM, merging neighbours with equal formatting
    function renderInline(doc, items, blockPx, heading) {
        const out = [];
        let i = 0;
        while (i < items.length) {
            const it = items[i];
            if (it.t === 'br') { out.push(doc.createElement('br')); i++; continue; }
            if (it.t !== 'text') { i++; continue; }

            // Links group their runs into one <a>
            const href = it.f.href || '';
            const seg = [];
            let j = i;
            while (j < items.length && items[j].t === 'text' && (items[j].f.href || '') === href) {
                seg.push(items[j]);
                j++;
            }

            const parts = [];
            let carry = '';
            let k = 0;
            while (k < seg.length) {
                // whitespace-only runs (Word spacers) join their neighbour
                // instead of getting their own formatting
                if (!seg[k].text.trim() && k + 1 < seg.length) {
                    carry += seg[k].text;
                    k++;
                    continue;
                }
                let text = carry + seg[k].text;
                carry = '';
                const f = seg[k].f;
                const key = fmtKey(f, blockPx, heading);
                let n = k + 1;
                while (n < seg.length && (fmtKey(seg[n].f, blockPx, heading) === key || !seg[n].text.trim())) {
                    text += seg[n].text;
                    n++;
                }
                parts.push(wrapRun(doc, text, f, blockPx, heading));
                k = n;
            }

            if (href) {
                const a = doc.createElement('a');
                a.setAttribute('href', href);
                a.setAttribute('target', '_blank');
                a.setAttribute('rel', 'noopener noreferrer');
                parts.forEach(p => a.appendChild(p));
                out.push(a);
            } else {
                parts.forEach(p => out.push(p));
            }
            i = j;
        }
        return out;
    }

    // Drop leading/trailing breaks and spaces of a line
    function trimItems(items) {
        const list = items.slice();
        while (list.length && (list[0].t === 'br' || (list[0].t === 'text' && !list[0].text.trim()))) list.shift();
        while (list.length && (list[list.length - 1].t === 'br' || (list[list.length - 1].t === 'text' && !list[list.length - 1].text.trim()))) list.pop();
        if (list.length && list[0].t === 'text') list[0] = Object.assign({}, list[0], { text: list[0].text.replace(/^\s+/, '') });
        const last = list.length - 1;
        if (last >= 0 && list[last].t === 'text') list[last] = Object.assign({}, list[last], { text: list[last].text.replace(/\s+$/, '') });
        return list;
    }

    // Inline pastes keep their own spaces ("word " stays "word "); only
    // whole whitespace / line-break items at the edges are dropped
    function trimEdges(items) {
        const list = items.slice();
        const edge = it => it.t === 'br' || (it.t === 'text' && !it.text.trim());
        while (list.length && edge(list[0])) list.shift();
        while (list.length && edge(list[list.length - 1])) list.pop();
        return list;
    }

    const itemsHaveContent = items => items.some(it => it.t === 'text' && it.text.replace(INVISIBLE, '') !== '');

    function appendAll(parent, nodes) {
        nodes.forEach(n => parent.appendChild(n));
        return parent;
    }

    // A mix of inline items and finished blocks -> only blocks.
    // blockPx: size of the wrapper the items came from, base: page size
    function groupInline(doc, items, blockPx, base, align, indent) {
        const out = [];
        let buf = [];
        const flush = () => {
            const line = trimItems(buf);
            if (itemsHaveContent(line)) {
                const p = doc.createElement('p');
                appendAll(p, renderInline(doc, line, blockPx, false));
                if (align) p.style.textAlign = align;
                if (indent) p.style.marginLeft = indent + 'px';
                const em = emFor(blockPx, base);
                if (em) p.style.fontSize = em;
                out.push(p);
            }
            buf = [];
        };
        items.forEach(it => {
            if (isItem(it)) {
                buf.push(it);
            } else {
                flush();
                if (align && /^(P|H1|H2|H3)$/.test(it.nodeName) && !it.style.textAlign) it.style.textAlign = align;
                if (indent && /^(P|H1|H2|H3|UL|OL|BLOCKQUOTE)$/.test(it.nodeName) && !it.style.marginLeft) it.style.marginLeft = indent + 'px';
                out.push(it);
            }
        });
        flush();
        return out;
    }

    // Block elements inside something that must stay one line (heading,
    // list item): keep their text, separated by line breaks
    function inlineOnly(items) {
        const out = [];
        items.forEach(it => {
            if (isItem(it)) {
                out.push(it);
            } else if (hasRealContent(it)) {
                if (out.length) out.push({ t: 'br' });
                out.push({ t: 'text', text: it.textContent.replace(/\s+/g, ' ').trim(), f: { px: 16 } });
            }
        });
        return out;
    }

    function cleanChildren(node, ctx) {
        const out = [];
        Array.from(node.childNodes).forEach(child => {
            cleanNode(child, ctx).forEach(x => out.push(x));
        });
        return out;
    }

    function cleanNode(node, ctx) {
        const doc = ctx.doc;

        if (node.nodeType === 3) {
            if (ctx.pre) {
                const res = [];
                node.nodeValue.replace(/\r\n?/g, '\n').split('\n').forEach((part, i) => {
                    if (i) res.push({ t: 'br' });
                    // keep the indentation of preformatted lines
                    if (part) res.push({ t: 'text', text: part.replace(/\u00a0/g, ' ').replace(/^ +| {2,}/g, m => '\u00a0'.repeat(m.length)), f: ctx.f });
                });
                return res;
            }
            const text = node.nodeValue.replace(/[\r\n]+/g, ' ').replace(/\u00a0/g, ' ');
            return text ? [{ t: 'text', text, f: ctx.f }] : [];
        }
        if (node.nodeType !== 1) return [];

        const tag = node.nodeName.toUpperCase();
        if (DROP_TAGS.has(tag)) return [];
        if (tag.indexOf(':') !== -1 && tag !== 'O:P') return []; // w:*, v:*, m:* office junk
        if (isHiddenEl(node)) return [];
        if (tag === 'BR') return [{ t: 'br' }];
        if (tag === 'HR') return [doc.createElement('hr')];
        if (tag === 'IMG' || tag === 'PICTURE') {
            // A teaching is text: image links from Word/Docs/web expire or
            // point at other sites, so they are not kept (the elder is told)
            ctx.stats.droppedImages++;
            return [];
        }

        const st = node.style || {};
        const isHeading = !!HEADING_MAP[tag];
        const heading = ctx.heading || isHeading;
        const ownPx = isHeading ? ctx.px : (fontPxOf(node, ctx.px) || ctx.px);

        // Bible verse blocks (copied from a teaching) keep only their class:
        // colours / sizes copied from the screen would freeze one theme in
        const isVerseBlock = tag === 'P' && !!node.classList &&
            (node.classList.contains('vref') || node.classList.contains('vtxt'));
        if (ctx.verse || isVerseBlock) {
            const vf = { px: ctx.px, sup: tag === 'SUP' || !!(ctx.verse && ctx.f.sup) };
            const vctx = Object.assign({}, ctx, { f: vf, verse: true });
            const vkids = cleanChildren(node, vctx);
            if (!isVerseBlock) return vkids;
            const line = trimItems(inlineOnly(vkids));
            if (!itemsHaveContent(line)) return [];
            const vp = doc.createElement('p');
            vp.className = node.classList.contains('vref') ? 'vref' : 'vtxt';
            appendAll(vp, renderInline(doc, line, ctx.px, false));
            return [vp];
        }

        // Effective formatting for everything inside this element
        const f = Object.assign({}, ctx.f, { px: ownPx });
        if (tag === 'A') {
            const href = safeHref(node.getAttribute('href'));
            if (href) {
                f.href = href;
                f.u = false;   // links get the site's link style
                f.color = '';
            }
        }

        const fw = String(st.fontWeight || '').toLowerCase();
        const fwNum = parseInt(fw, 10);
        if (tag === 'B' || tag === 'STRONG') {
            // Google Docs wraps everything in <b style="font-weight:normal">
            f.strong = !(fw === 'normal' || fw === 'lighter' || (fwNum && fwNum <= 400));
        } else if (fw === 'bold' || fw === 'bolder' || fwNum >= 600) {
            f.strong = true;
        } else if (fw === 'normal' || (fwNum && fwNum <= 400)) {
            f.strong = false;
        }

        const fs = String(st.fontStyle || '').toLowerCase();
        if (/^(I|EM|CITE|DFN|VAR)$/.test(tag)) f.em = fs !== 'normal';
        else if (fs === 'italic' || fs === 'oblique') f.em = true;
        else if (fs === 'normal') f.em = false;

        const deco = (String(st.textDecorationLine || '') + ' ' + String(st.textDecoration || '')).toLowerCase();
        if (!f.href && (tag === 'U' || tag === 'INS' || deco.indexOf('underline') !== -1)) f.u = true;
        if (tag === 'S' || tag === 'STRIKE' || tag === 'DEL' || deco.indexOf('line-through') !== -1) f.s = true;

        const va = String(st.verticalAlign || '').toLowerCase();
        if (tag === 'SUP' || va === 'super') { f.sup = true; f.sub = false; }
        else if (tag === 'SUB' || va === 'sub') { f.sub = true; f.sup = false; }

        const c = f.href ? null : parseColor(st.color || (tag === 'FONT' ? node.getAttribute('color') : ''));
        if (c) f.color = keepTextColor(c) ? 'rgb(' + Math.round(c.r) + ', ' + Math.round(c.g) + ', ' + Math.round(c.b) + ')' : '';
        const bgRaw = st.backgroundColor || (tag === 'MARK' ? 'rgb(255, 235, 59)' : '');
        const bg = parseColor(bgRaw);
        if (bg) f.bg = keepBackground(bg) ? 'rgba(' + Math.round(bg.r) + ', ' + Math.round(bg.g) + ', ' + Math.round(bg.b) + ', 0.4)' : '';
        else if (/^transparent$/i.test(String(bgRaw).trim())) f.bg = '';

        const childCtx = Object.assign({}, ctx, { f, px: ownPx, heading, pre: ctx.pre || tag === 'PRE' });
        const kids = cleanChildren(node, childCtx);

        // ----- headings -----
        if (isHeading) {
            const items = trimItems(inlineOnly(kids));
            if (!itemsHaveContent(items)) return [];
            const h = doc.createElement(HEADING_MAP[tag]);
            appendAll(h, renderInline(doc, items, ownPx, true));
            const align = blockAlign(node);
            if (align) h.style.textAlign = align;
            return [h];
        }

        // ----- lists -----
        if (tag === 'UL' || tag === 'OL') {
            const list = doc.createElement(tag.toLowerCase());
            const lst = String(st.listStyleType || '').toLowerCase()
                || String((node.querySelector('li') || { style: {} }).style.listStyleType || '').toLowerCase();
            const typeMap = { 'lower-alpha': 'a', 'lower-latin': 'a', 'upper-alpha': 'A', 'upper-latin': 'A', 'lower-roman': 'i', 'upper-roman': 'I' };
            const type = /^[1aAiI]$/.test(node.getAttribute('type') || '') ? node.getAttribute('type') : (typeMap[lst] || '');
            if (tag === 'OL') {
                if (type && type !== '1') list.setAttribute('type', type);
                const start = parseInt(node.getAttribute('start') || '', 10);
                if (start > 1) list.setAttribute('start', String(start));
            }
            let pending = [];
            const flushStray = () => {
                const line = trimItems(pending);
                if (itemsHaveContent(line)) {
                    const li = doc.createElement('li');
                    appendAll(li, renderInline(doc, line, ctx.base, false));
                    list.appendChild(li);
                }
                pending = [];
            };
            kids.forEach(k => {
                if (isItem(k)) { pending.push(k); return; }
                flushStray();
                if (k.nodeName === 'LI') {
                    list.appendChild(k);
                } else if (k.nodeName === 'UL' || k.nodeName === 'OL') {
                    // invalid <ul><ul> nesting: hang it under the previous item
                    let prev = list.lastElementChild;
                    if (!prev) prev = list.appendChild(doc.createElement('li'));
                    prev.appendChild(k);
                } else if (hasRealContent(k)) {
                    const li = doc.createElement('li');
                    while (k.firstChild) li.appendChild(k.firstChild);
                    list.appendChild(li);
                }
            });
            flushStray();
            return list.children.length ? [list] : [];
        }

        if (tag === 'LI') {
            const li = doc.createElement('li');
            let buf = [];
            let needBr = false;
            const flush = () => {
                const line = trimItems(buf);
                if (itemsHaveContent(line)) {
                    if (needBr) li.appendChild(doc.createElement('br'));
                    appendAll(li, renderInline(doc, line, ownPx, false));
                    needBr = true;
                }
                buf = [];
            };
            kids.forEach(k => {
                if (isItem(k)) { buf.push(k); return; }
                flush();
                if (/^(UL|OL|TABLE)$/.test(k.nodeName)) {
                    li.appendChild(k);
                    needBr = false;
                } else if (hasRealContent(k)) {
                    // Google Docs: <li><p>text</p></li> - keep the text in the item
                    if (needBr) li.appendChild(doc.createElement('br'));
                    while (k.firstChild) li.appendChild(k.firstChild);
                    needBr = true;
                }
            });
            flush();
            const em = emFor(ownPx, ctx.base);
            if (em) li.style.fontSize = em;
            return [li];
        }

        // ----- tables -----
        if (tag === 'TABLE' || tag === 'THEAD' || tag === 'TBODY' || tag === 'TFOOT' || tag === 'TR') {
            const allowed = tag === 'TABLE' ? ['CAPTION', 'THEAD', 'TBODY', 'TFOOT', 'TR']
                : tag === 'TR' ? ['TD', 'TH'] : ['TR'];
            const el = doc.createElement(tag.toLowerCase());
            kids.forEach(k => {
                if (!isItem(k) && allowed.includes(k.nodeName)) el.appendChild(k);
            });
            if (tag === 'TABLE' && !el.querySelector('tr')) return [];
            return el.children.length ? [el] : [];
        }

        if (tag === 'TD' || tag === 'TH' || tag === 'CAPTION') {
            const cell = doc.createElement(tag.toLowerCase());
            ['colspan', 'rowspan'].forEach(a => {
                const v = parseInt(node.getAttribute(a) || '', 10);
                if (v > 1 && v < 50) cell.setAttribute(a, String(v));
            });
            if (kids.some(k => !isItem(k))) {
                const blocks = groupInline(doc, kids, ownPx, ctx.base, '', 0);
                // Word puts every cell in a <p class=MsoNormal> - one paragraph is just text
                if (blocks.length === 1 && blocks[0].nodeName === 'P' && !blocks[0].getAttribute('style')) {
                    while (blocks[0].firstChild) cell.appendChild(blocks[0].firstChild);
                } else {
                    appendAll(cell, blocks);
                }
            } else {
                appendAll(cell, renderInline(doc, trimItems(kids), ctx.base, false));
            }
            return [cell];
        }

        // ----- quotes -----
        if (tag === 'BLOCKQUOTE') {
            const bq = doc.createElement('blockquote');
            if (kids.some(k => !isItem(k))) {
                appendAll(bq, groupInline(doc, kids, ownPx, ctx.base, '', 0));
            } else {
                const line = trimItems(kids);
                if (!itemsHaveContent(line)) return [];
                appendAll(bq, renderInline(doc, line, ctx.base, false));
            }
            return hasRealContent(bq) ? [bq] : [];
        }

        // ----- paragraphs and other block wrappers -----
        if (CONTAINERS.has(tag) || tag === 'DL') {
            const align = blockAlign(node);
            const indent = tag === 'DD' ? 24 : blockIndentPx(node);

            if (tag === 'DL' || kids.some(k => !isItem(k))) {
                // A wrapper around real blocks: keep the blocks, pass alignment down
                return groupInline(doc, kids, ownPx, ctx.base, align, indent);
            }
            const line = trimItems(kids);
            if (!itemsHaveContent(line)) return [];
            const p = doc.createElement('p');
            appendAll(p, renderInline(doc, line, ownPx, false));
            if (align) p.style.textAlign = align;
            if (indent) p.style.marginLeft = indent + 'px';
            const em = emFor(ownPx, ctx.base);
            if (em) p.style.fontSize = em;
            return [p];
        }

        // ----- everything else is inline: its formatting is already in f -----
        return kids;
    }

    // Final tidy-up of the cleaned blocks
    function finalizeBlocks(root) {
        // Empty paragraphs / headings / items (Word & Docs spacing lines)
        root.querySelectorAll('p, h1, h2, h3, li, blockquote').forEach(el => {
            if (!hasRealContent(el) && !el.querySelector('ul, ol')) el.remove();
        });
        root.querySelectorAll('ul, ol').forEach(list => {
            if (!list.querySelector('li')) list.remove();
        });
        // Trailing <br> inside blocks
        root.querySelectorAll('p, h1, h2, h3, li, td, th, blockquote').forEach(el => {
            while (el.lastChild && el.lastChild.nodeName === 'BR') el.lastChild.remove();
        });
        // Lists that were split by empty lines become one list again, so
        // the numbering continues (1, 2, 3 instead of 1, 1, 1)
        const mergeLists = parent => {
            let prev = null;
            Array.from(parent.childNodes).forEach(node => {
                if (node.nodeType === 3 && !node.nodeValue.trim()) return;
                if (node.nodeType === 1 && (node.nodeName === 'UL' || node.nodeName === 'OL') &&
                    prev && prev.nodeName === node.nodeName &&
                    (prev.getAttribute('type') || '') === (node.getAttribute('type') || '')) {
                    const prevStart = parseInt(prev.getAttribute('start') || '1', 10) || 1;
                    const start = node.getAttribute('start');
                    const continues = !start || parseInt(start, 10) === prevStart + prev.children.length;
                    if (node.nodeName === 'UL' || continues) {
                        while (node.firstChild) prev.appendChild(node.firstChild);
                        node.remove();
                        return;
                    }
                }
                prev = node.nodeType === 1 ? node : null;
            });
        };
        mergeLists(root);
        root.querySelectorAll('blockquote, li, td').forEach(mergeLists);
    }

    function buildRich(doc) {
        const base = dominantFontPx(doc.body);
        const ctx = { doc, base, px: base, f: { px: base }, heading: false, pre: false, stats: { droppedImages: 0 } };
        const items = cleanChildren(doc.body, ctx);
        const out = doc.createElement('div');

        if (!items.some(x => !isItem(x))) {
            // Only inline content (a word, a sentence): no paragraph around it
            appendAll(out, renderInline(doc, trimEdges(items), base, false));
        } else {
            appendAll(out, groupInline(doc, items, base, base, '', 0));
            finalizeBlocks(out);
        }
        return { html: out.innerHTML.trim(), droppedImages: ctx.stats.droppedImages };
    }

    // ---------- "Plain text" ----------
    // Text only, but the lines stay lines: every paragraph, heading and
    // list item becomes its own paragraph (lists keep "1." / "•").
    function blocksToHtml(blocks) {
        const list = blocks.filter(b => b && b.trim());
        if (!list.length) return '';
        if (list.length === 1 && list[0].indexOf('\n') === -1) return escapeHtml(list[0]);
        return list.map(b => '<p>' + escapeHtml(b).replace(/\n/g, '<br>') + '</p>').join('');
    }

    function toRoman(n) {
        const map = [[10, 'x'], [9, 'ix'], [5, 'v'], [4, 'iv'], [1, 'i']];
        let out = '';
        map.forEach(([v, r]) => { while (n >= v) { out += r; n -= v; } });
        return out;
    }

    // "1.", "a.", "iv." ... as the list shows it
    function listMarker(type, n) {
        if (type === 'a') return (n <= 26 ? String.fromCharCode(96 + n) : n) + '.';
        if (type === 'A') return (n <= 26 ? String.fromCharCode(64 + n) : n) + '.';
        if (type === 'i') return (n < 40 ? toRoman(n) : n) + '.';
        if (type === 'I') return (n < 40 ? toRoman(n).toUpperCase() : n) + '.';
        return n + '.';
    }

    function buildPlain(doc) {
        const blocks = [];
        let cur = '';
        let prefix = '';
        const flush = () => {
            const t = cur.replace(/[ \t\u00a0]+/g, ' ').replace(/ ?\n ?/g, '\n').replace(/^\n+|\n+$/g, '').trim();
            if (t) {
                blocks.push(prefix + t);
                prefix = '';
            }
            cur = '';
        };
        const typeMap = { 'lower-alpha': 'a', 'lower-latin': 'a', 'upper-alpha': 'A', 'upper-latin': 'A', 'lower-roman': 'i', 'upper-roman': 'I' };
        const listType = list => {
            const attr = list.getAttribute('type') || '';
            if (/^[1aAiI]$/.test(attr)) return attr;
            const first = list.querySelector('li');
            const lst = String((list.style && list.style.listStyleType) || (first && first.style.listStyleType) || '').toLowerCase();
            return typeMap[lst] || '1';
        };
        let inPre = 0;
        const walk = node => {
            if (node.nodeType === 3) {
                cur += inPre ? node.nodeValue.replace(/\r\n?/g, '\n') : node.nodeValue.replace(/[\r\n]+/g, ' ');
                return;
            }
            if (node.nodeType !== 1) return;
            const tag = node.nodeName.toUpperCase();
            if (DROP_TAGS.has(tag) || (tag.indexOf(':') !== -1 && tag !== 'O:P') || isHiddenEl(node)) return;
            if (tag === 'BR') { cur += '\n'; return; }
            if (tag === 'UL' || tag === 'OL') {
                flush();
                let n = parseInt(node.getAttribute('start') || '1', 10) || 1;
                Array.from(node.childNodes).forEach(li => {
                    if (li.nodeType === 1 && li.nodeName.toUpperCase() === 'LI') {
                        flush();
                        prefix = tag === 'OL' ? listMarker(listType(node), n++) + ' ' : '• ';
                        Array.from(li.childNodes).forEach(walk);
                        flush();
                        prefix = '';
                    } else {
                        walk(li);
                    }
                });
                return;
            }
            if (tag === 'TR') {
                flush();
                const cells = Array.from(node.children)
                    .filter(td => /^(TD|TH)$/i.test(td.nodeName))
                    .map(td => td.textContent.replace(/\s+/g, ' ').trim())
                    .filter(Boolean);
                if (cells.length) blocks.push(cells.join(' | '));
                return;
            }
            const block = BLOCK_LEVEL.has(tag);
            if (block) flush();
            if (tag === 'PRE') inPre++;
            Array.from(node.childNodes).forEach(walk);
            if (tag === 'PRE') inPre--;
            if (block) flush();
        };
        walk(doc.body);
        flush();
        return blocksToHtml(blocks);
    }

    // Clipboard text without HTML (WhatsApp, Notepad, PDF viewers...):
    // blank lines separate paragraphs, single line breaks stay line breaks
    function textToHtml(text) {
        const norm = String(text || '').replace(/\r\n?/g, '\n').replace(/\u00a0/g, ' ');
        const paras = norm.split(/\n[ \t]*\n+/)
            .map(p => joinHardWraps(p.replace(/[ \t]+$/gm, '').replace(/^\n+|\n+$/g, '')))
            .filter(p => p.trim());
        return blocksToHtml(paras);
    }

    // Text copied from a PDF breaks every line where the page did. When
    // almost every line of a paragraph is (nearly) full width, those breaks
    // are the page's, not the writer's: join them. A short line, or a line
    // that ends a sentence before a capital, keeps its break (WhatsApp-style
    // text keeps its own lines).
    function joinHardWraps(para) {
        const lines = para.split('\n');
        if (lines.length < 3) return para;
        const lens = lines.map(l => l.trim().length);
        const max = Math.max.apply(null, lens);
        if (max < 35) return para;
        const body = lens.slice(0, -1);
        if (body.filter(n => n >= max * 0.7).length < body.length * 0.8) return para;
        let out = lines[0].trim();
        for (let i = 1; i < lines.length; i++) {
            const prev = lines[i - 1].trim();
            const cur = lines[i].trim();
            const prevFull = lens[i - 1] >= max * 0.7;
            const sentenceEnd = /[.!?:;"”’)]$/.test(prev) && /^[A-ZÀ-Ý0-9"“‘(]/.test(cur);
            if (!prevFull || sentenceEnd) out += '\n' + cur;
            else if (/-$/.test(prev)) out += cur;
            else out += ' ' + cur;
        }
        return out;
    }

    function buildPasteVersions(html, text) {
        if (html && html.trim()) {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            convertWordLists(doc);
            const rich = buildRich(doc);
            const plain = buildPlain(doc) || textToHtml(text);
            const richHtml = rich.html || plain;
            return { rich: richHtml, plain: plain || richHtml, differs: !!richHtml && !!plain && richHtml !== plain, droppedImages: rich.droppedImages };
        }
        const t = textToHtml(text);
        return { rich: t, plain: t, differs: false, droppedImages: 0 };
    }

    // ---------- inserting cleaned HTML at the caret ----------
    // Done by hand (not execCommand('insertHTML')) because the browser
    // merges the first pasted block into the current paragraph and adds
    // inline style spans - headings and verse blocks would lose their look.

    function insertHtmlAtCaret(html) {
        const ed = getCurrentEditor();
        let ctx = selectionInEditor() || focusEditor();
        if (!ctx || !html) return;

        if (!ctx.sel.isCollapsed) {
            document.execCommand('delete', false, null);
            ensureParagraph(ed);
            resetIfEmptied(ed);
            ctx = selectionInEditor() || focusEditor();
            if (!ctx) return;
        }

        const tpl = document.createElement('template');
        tpl.innerHTML = html;
        const nodes = Array.from(tpl.content.childNodes);
        if (!nodes.length) return;

        const range = ctx.sel.getRangeAt(0);
        if (nodes.some(isBlockEl)) {
            insertBlocks(ed, range, tpl.content, nodes);
        } else {
            insertInline(ed, range, tpl.content, nodes);
        }
    }

    function insertInline(ed, range, frag, nodes) {
        const last = nodes[nodes.length - 1];
        if (range.startContainer === ed) {
            // Caret between blocks: continue the paragraph before it
            const prev = ed.childNodes[range.startOffset - 1];
            if (prev && isBlockEl(prev) && !/^(HR|TABLE)$/.test(prev.nodeName)) {
                placeCaretAtEnd(prev);
                range = window.getSelection().getRangeAt(0);
            }
        }
        if (range.startContainer === ed) {
            const p = document.createElement('p');
            p.appendChild(frag);
            ed.insertBefore(p, ed.childNodes[range.startOffset] || null);
            placeCaretAtEnd(last);
            return;
        }
        // An empty line's placeholder <br> would end up after the text
        const blk = closestTag(range.startContainer, BLOCK_TAGS, ed);
        if (blk && !hasRealContent(blk)) {
            blk.innerHTML = '';
            range = document.createRange();
            range.setStart(blk, 0);
        }
        range.insertNode(frag);
        placeCaretAtEnd(last);
    }

    function insertBlocks(ed, range, frag, nodes) {
        const sc = range.startContainer;
        const last = nodes[nodes.length - 1];

        // In a table cell: the blocks simply go into the cell
        if (closestTag(sc, ['TD', 'TH'], ed)) {
            range.insertNode(frag);
            placeCaretAtEnd(last);
            return;
        }

        // In a list item: pasted lines become list items, a divider goes
        // below the whole list
        const li = closestTag(sc, ['LI'], ed);
        if (li && nodes.every(n => n.nodeName === 'HR')) {
            let top = li;
            while (top.parentNode && top.parentNode !== ed) top = top.parentNode;
            ed.insertBefore(frag, top.nextSibling);
            finishBlockCaret(ed, last);
            return;
        }
        if (li) {
            insertIntoList(li, range, nodes);
            return;
        }

        // Caret directly in the editor (between blocks)
        if (sc === ed) {
            ed.insertBefore(frag, ed.childNodes[range.startOffset] || null);
            finishBlockCaret(ed, last);
            return;
        }

        // Split the top-level block at the caret: head | pasted | tail
        let top = sc;
        while (top && top.parentNode !== ed) top = top.parentNode;
        if (!top) {
            ed.appendChild(frag);
            finishBlockCaret(ed, last);
            return;
        }

        const tailRange = document.createRange();
        tailRange.setStart(range.startContainer, range.startOffset);
        tailRange.setEndAfter(top);
        const tail = tailRange.extractContents();
        const next = top.nextSibling;

        ed.insertBefore(frag, next);
        Array.from(tail.childNodes).forEach(n => {
            if (n.nodeType === 1 && n.nodeName !== 'HR' && !hasRealContent(n)) return;
            if (n.nodeType === 3 && !n.nodeValue.trim()) return;
            ed.insertBefore(n, next);
        });
        if (top.nodeType === 3 ? !top.nodeValue.trim() : (top.nodeName !== 'HR' && !hasRealContent(top))) {
            top.remove();
        }
        finishBlockCaret(ed, last);
    }

    // After a divider / table you can keep typing below it
    function finishBlockCaret(ed, last) {
        if (/^(HR|TABLE)$/.test(last.nodeName)) {
            let nxt = last.nextSibling;
            if (!nxt || !isBlockEl(nxt) || /^(HR|TABLE)$/.test(nxt.nodeName)) {
                nxt = document.createElement('p');
                nxt.appendChild(document.createElement('br'));
                last.parentNode.insertBefore(nxt, last.nextSibling);
            }
            placeCaretAtStart(nxt);
            return;
        }
        placeCaretAtEnd(last);
    }

    function insertIntoList(li, range, nodes) {
        const list = li.parentNode;
        const tailRange = document.createRange();
        tailRange.setStart(range.startContainer, range.startOffset);
        tailRange.setEndAfter(li);
        const tail = tailRange.extractContents();

        const items = [];
        const toItem = el => {
            const item = document.createElement('li');
            if (el.nodeType === 3) {
                item.textContent = el.nodeValue.trim();
            } else if (/^(TABLE|BLOCKQUOTE)$/.test(el.nodeName)) {
                item.textContent = el.textContent.replace(/\s+/g, ' ').trim();
            } else {
                while (el.firstChild) item.appendChild(el.firstChild);
            }
            return item;
        };
        nodes.forEach(n => {
            if (n.nodeType === 1 && (n.nodeName === 'UL' || n.nodeName === 'OL')) {
                Array.from(n.children).forEach(c => { if (c.nodeName === 'LI') items.push(c); });
            } else if (n.nodeType === 1 && n.nodeName === 'HR') {
                // a divider has no meaning inside a list
            } else if (hasRealContent(n)) {
                items.push(toItem(n));
            }
        });

        const ref = li.nextSibling;
        items.forEach(it => list.insertBefore(it, ref));
        Array.from(tail.childNodes).forEach(n => {
            if (n.nodeType === 1 && n.nodeName === 'LI' && !hasRealContent(n)) return;
            if (n.nodeType === 3 && !n.nodeValue.trim()) return;
            list.insertBefore(n, ref);
        });
        if (!hasRealContent(li) && !li.querySelector('ul, ol')) li.remove();
        placeCaretAtEnd(items.length ? items[items.length - 1] : list);
    }

    // ---------- paste / drop handling ----------
    function handleIncoming(html, text, forcePlain, hadImages) {
        const code = currentLang;
        let versions;
        try {
            versions = buildPasteVersions(html, text);
        } catch (err) {
            console.error('elders.js: paste cleaning failed', err);
            const t = textToHtml(text);
            versions = { rich: t, plain: t, differs: false, droppedImages: 0 };
        }

        const mode = forcePlain ? 'plain' : pasteMode;
        const chosen = versions[mode] || versions.plain || versions.rich;
        if (!chosen) {
            if (hadImages || versions.droppedImages) flash('error', STR.paste_no_images);
            return;
        }

        recordHistory(code); // "before" step (includes the selection being replaced)
        const before = hist(code).stack[hist(code).index];
        hidePastePop();
        insertHtmlAtCaret(chosen);
        recordHistory(code);
        afterContentChange(code);

        if (versions.droppedImages) flash('error', STR.paste_no_images);

        if (versions.differs) {
            lastPaste = { code, before, after: hist(code).stack[hist(code).index], versions, mode };
            showPastePop(mode);
        }
    }

    function onPaste(e) {
        if (e.currentTarget !== getCurrentEditor()) return;
        const cd = e.clipboardData || window.clipboardData;
        if (!cd || typeof cd.getData !== 'function') return; // let the browser do it
        e.preventDefault();

        let html = '';
        let text = '';
        try { html = cd.getData('text/html') || ''; } catch (err) { html = ''; }
        try { text = cd.getData('text/plain') || cd.getData('Text') || ''; } catch (err) { text = ''; }
        const hadImages = Array.from(cd.items || []).some(it => it.kind === 'file' && /^image\//.test(it.type));

        const forcePlain = Date.now() < forcePlainUntil;
        forcePlainUntil = 0;

        if (!html.trim() && !text) {
            if (hadImages) flash('error', STR.paste_no_images);
            return;
        }
        handleIncoming(html, text, forcePlain, hadImages);
    }

    // ---------- the "pasted as" bar ----------
    const pastePop = $('paste-pop');
    let pastePopTimer = 0;

    function showPastePop(mode) {
        if (!pastePop) return;
        pastePop.querySelectorAll('[data-paste-switch]').forEach(b => {
            b.setAttribute('aria-pressed', b.dataset.pasteSwitch === mode ? 'true' : 'false');
        });
        pastePop.hidden = false;
        const live = $('paste-pop-live');
        if (live) live.textContent = (STR.paste_options || '') + ': ' + (mode === 'plain' ? STR.paste_plain : STR.paste_rich);
        placePastePop();
        clearTimeout(pastePopTimer);
        pastePopTimer = setTimeout(hidePastePop, 15000);
    }

    // iOS Safari (and browsers ignoring interactive-widget) lay the
    // keyboard over the page: lift the bar by the part that is covered
    function placePastePop() {
        if (!pastePop || !window.visualViewport) return;
        const vv = window.visualViewport;
        const covered = Math.max(0, window.innerHeight - (vv.height + vv.offsetTop));
        pastePop.style.setProperty('--kb', Math.round(covered) + 'px');
    }

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', placePastePop);
        window.visualViewport.addEventListener('scroll', placePastePop);
    }

    function hidePastePop() {
        clearTimeout(pastePopTimer);
        if (pastePop && !pastePop.hidden) pastePop.hidden = true;
        lastPaste = null;
    }

    // Swap the paste that just happened for the other version
    function switchPaste(mode) {
        const lp = lastPaste;
        if (!lp || lp.code !== currentLang) { hidePastePop(); return; }
        if (lp.mode === mode) return;
        const h = hist(lp.code);
        recordHistory(lp.code);
        const bi = h.stack.indexOf(lp.before);
        if (bi < 0 || h.stack[h.index] !== lp.after) { hidePastePop(); return; } // edited since

        h.index = bi;
        h.stack.length = h.index + 1;
        restoreSnapshot(lp.code, h.stack[h.index], true);
        insertHtmlAtCaret(lp.versions[mode]);
        recordHistory(lp.code);
        lp.after = h.stack[h.index];
        afterContentChange(lp.code);

        lp.mode = mode;
        lastPaste = lp;
        setPasteMode(mode, true); // the choice is remembered for the next paste
        showPastePop(mode);
    }

    if (pastePop) {
        pastePop.querySelectorAll('[data-paste-switch]').forEach(b => {
            b.addEventListener('click', () => switchPaste(b.dataset.pasteSwitch));
        });
        const close = $('paste-pop-close');
        if (close) close.addEventListener('click', hidePastePop);
    }

    // ---------- toolbar paste mode ----------
    function setPasteMode(mode, persist) {
        pasteMode = mode === 'plain' ? 'plain' : 'rich';
        document.querySelectorAll('[data-paste-mode]').forEach(b => {
            const on = b.dataset.pasteMode === pasteMode;
            b.setAttribute('aria-checked', on ? 'true' : 'false');
            b.tabIndex = on ? 0 : -1;
        });
        if (persist) store.set('oac_paste_mode', pasteMode);
    }

    document.querySelectorAll('[data-paste-mode]').forEach(b => {
        b.addEventListener('click', () => setPasteMode(b.dataset.pasteMode, true));
        b.addEventListener('keydown', e => {
            if (e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                e.preventDefault();
                setPasteMode(pasteMode === 'rich' ? 'plain' : 'rich', true);
                const on = document.querySelector('[data-paste-mode="' + pasteMode + '"]');
                if (on) on.focus();
            }
        });
    });

    setPasteMode(store.get('oac_paste_mode') || 'rich', false);

    // =================================================================
    // FORMATTING COMMANDS
    // =================================================================

    // New lines create <p> instead of <div>
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* old browser */ }

    function clearFontSizeStyle(el) {
        el.style.fontSize = '';
        if (!el.getAttribute('style')) el.removeAttribute('style');
        if (el.tagName === 'SPAN' && !el.attributes.length) unwrap(el);
    }

    function clearSizesInside(el) {
        el.querySelectorAll('span, font').forEach(x => {
            if (x.tagName === 'FONT') x.removeAttribute('size');
            if (x.style && x.style.fontSize) clearFontSizeStyle(x);
        });
    }

    // Sizes are stored in em (relative), like pasted sizes, so the reader's
    // A- / A+ buttons on the welcome page still scale them
    function setEmSize(el, wantPx) {
        const prev = el.style.fontSize;
        el.style.fontSize = '';
        const natural = parseFloat(getComputedStyle(el).fontSize) || 16;
        el.style.fontSize = prev;
        if (Math.abs(natural - wantPx) < 0.5) {
            el.style.fontSize = '';
        } else {
            const parentPx = parseFloat(getComputedStyle(el.parentElement).fontSize) || 16;
            el.style.fontSize = (Math.round(wantPx / parentPx * 1000) / 1000) + 'em';
        }
        if (!el.getAttribute('style')) el.removeAttribute('style');
    }

    // ----- Block type (H1 / H2 / H3 / P) -----
    // Every selected paragraph changes on its own (the browser's formatBlock
    // glues a multi-paragraph selection into one block with <br>s). Clicking
    // the active heading turns it back into a paragraph.
    const RETYPE_BLOCKS = /^(P|H[1-6]|DIV|PRE|BLOCKQUOTE)$/;

    function setBlockType(tag) {
        runEdit(ctx => {
            const ed = ctx.editor;
            const blocks = getSelectedBlocks().filter(b =>
                RETYPE_BLOCKS.test(b.nodeName) &&
                !(b.nodeName === 'BLOCKQUOTE' && Array.from(b.childNodes).some(isBlockEl)));
            let target = tag.toUpperCase();
            if (!blocks.length) {
                document.execCommand('formatBlock', false, '<' + target + '>');
                return;
            }
            if (target !== 'P' && blocks.every(b => b.nodeName === target)) target = 'P';

            const caret = saveCaret(ed);
            blocks.forEach(b => {
                if (b.nodeName === target) return;
                const el = document.createElement(target.toLowerCase());
                ['text-align', 'margin-left', 'line-height'].concat(target === 'P' ? ['font-size'] : []).forEach(prop => {
                    const v = b.style ? b.style.getPropertyValue(prop) : '';
                    if (v) el.style.setProperty(prop, v);
                });
                while (b.firstChild) el.appendChild(b.firstChild);
                if (/^H[1-6]$/.test(target)) clearSizesInside(el);
                b.replaceWith(el);
            });
            restoreCaret(ed, caret);
        });
    }

    // ----- Font size -----
    // Collapsed cursor: the whole current block gets the size.
    // Real selection: the browser splits the text nodes cleanly via the
    // legacy fontSize command, then the <font> tags are swapped for
    // proper em spans and conflicting nested sizes are removed.
    function applyFontSize(value) {
        const design = parseFloat(value);
        if (!design) return;
        runEdit(ctx => {
            const ed = ctx.editor;
            const wantPx = design * (parseFloat(getComputedStyle(ed).fontSize) || 16) / 16;

            if (ctx.sel.isCollapsed) {
                const blk = closestBlock(ctx.sel.anchorNode);
                if (!blk) return;
                clearSizesInside(blk);
                setEmSize(blk, wantPx);
                return;
            }

            document.execCommand('styleWithCSS', false, false);
            document.execCommand('fontSize', false, '7');
            const spans = [];
            ed.querySelectorAll('font[size="7"]').forEach(f => {
                const span = document.createElement('span');
                while (f.firstChild) span.appendChild(f.firstChild);
                f.replaceWith(span);
                clearSizesInside(span);
                setEmSize(span, wantPx);
                spans.push(span);
            });
            if (!spans.length) return;
            const first = spans[0].firstChild || spans[0];
            const lastSpan = spans[spans.length - 1];
            const lastNode = lastSpan.lastChild || lastSpan;
            spans.forEach(s => { if (!s.attributes.length) unwrap(s); });
            try {
                const r = document.createRange();
                r.setStartBefore(first);
                r.setEndAfter(lastNode);
                ctx.sel.removeAllRanges();
                ctx.sel.addRange(r);
            } catch (e) { /* selection is a nicety */ }
        });
    }

    // ----- Line spacing: every block in the selection -----
    function applyLineSpacing(value) {
        runEdit(() => {
            getSelectedBlocks().forEach(b => { b.style.lineHeight = value; });
        });
    }

    // ----- Colors -----
    // color '' = back to the theme's own text colour: paint with a marker
    // colour (so the browser splits the text exactly at the selection), then
    // remove every colour on and inside the marked text
    const COLOR_MARK = 'rgb(1, 2, 3)';

    function applyColor(color) {
        runEdit(ctx => {
            expandToBlockIfCollapsed(ctx.sel);
            document.execCommand('styleWithCSS', false, true);
            document.execCommand('foreColor', false, color || COLOR_MARK);
            document.execCommand('styleWithCSS', false, false);
            if (color) return;
            const marked = Array.from(ctx.editor.querySelectorAll('[style*="color"], font[color]')).filter(el =>
                (el.style.color || '').replace(/\s+/g, '') === COLOR_MARK.replace(/\s+/g, '') ||
                (el.getAttribute('color') || '').toLowerCase() === '#010203');
            marked.forEach(el => {
                [el].concat(Array.from(el.querySelectorAll('[style*="color"], font[color]'))).forEach(x => {
                    x.style.color = '';
                    x.removeAttribute('color');
                    if (!x.getAttribute('style')) x.removeAttribute('style');
                });
            });
            const caret = saveCaret(ctx.editor);
            marked.concat(Array.from(ctx.editor.querySelectorAll('span:not([style]):not([class]), font:not([color]):not([size])')))
                .forEach(x => { if (x.isConnected && (x.nodeName === 'SPAN' || x.nodeName === 'FONT') && !x.attributes.length) unwrap(x); });
            restoreCaret(ctx.editor, caret);
        });
    }

    function applyHighlight(color) {
        runEdit(ctx => {
            expandToBlockIfCollapsed(ctx.sel);
            document.execCommand('styleWithCSS', false, true);
            let ok = false;
            try { ok = document.execCommand('hiliteColor', false, color); } catch (e) { ok = false; }
            if (!ok) {
                try { document.execCommand('backColor', false, color); } catch (e) { /* unsupported */ }
            }
            document.execCommand('styleWithCSS', false, false);
        });
    }

    // ----- Quote block toggle -----
    // Quote: the selected paragraphs go into ONE quote, each staying a
    // paragraph. Unquote: the paragraphs come back out.
    function toggleQuote() {
        runEdit(ctx => {
            const ed = ctx.editor;
            if (closestTag(ctx.sel.anchorNode, ['TD', 'TH'], ed)) return;
            const blocks = getSelectedBlocks();
            if (!blocks.length) return;
            const caret = saveCaret(ed);

            const quoteOf = b => (b.nodeName === 'BLOCKQUOTE' ? b : closestTag(b, ['BLOCKQUOTE'], ed));
            if (blocks.every(quoteOf)) {
                new Set(blocks.map(quoteOf)).forEach(q => {
                    if (Array.from(q.childNodes).some(isBlockEl)) {
                        liftIndent(q, 0, '');
                    } else {
                        const p = document.createElement('p');
                        while (q.firstChild) p.appendChild(q.firstChild);
                        q.replaceWith(p);
                    }
                });
            } else {
                const topOf = n => { while (n.parentNode && n.parentNode !== ed) n = n.parentNode; return n; };
                const first = topOf(blocks[0]);
                const last = topOf(blocks[blocks.length - 1]);
                const bq = document.createElement('blockquote');
                ed.insertBefore(bq, first);
                let n = first;
                while (n) {
                    const next = n.nextSibling;
                    if (n.nodeName === 'BLOCKQUOTE') {
                        while (n.firstChild) bq.appendChild(n.firstChild);
                        n.remove();
                    } else if (n.nodeType === 1 && !isBlockEl(n)) {
                        const p = document.createElement('p');
                        p.appendChild(n);
                        bq.appendChild(p);
                    } else {
                        bq.appendChild(n);
                    }
                    if (n === last) break;
                    n = next;
                }
            }
            restoreCaret(ed, caret);
        });
    }

    // ----- Indent / outdent -----
    // In lists: real nesting. Elsewhere: a left margin on the blocks
    // (the browser's own indent wraps text in a <blockquote>, which the
    // welcome page shows as a quote box)
    function indent(dir) {
        runEdit(ctx => {
            if (closestTag(ctx.sel.anchorNode, ['LI'], ctx.editor)) {
                document.execCommand(dir > 0 ? 'indent' : 'outdent', false, null);
                return;
            }
            getSelectedBlocks().forEach(b => {
                if (/^(LI|TD|TH)$/.test(b.tagName)) return;
                const cur = parseFloat(b.style.marginLeft) || 0;
                const next = Math.max(0, Math.min(200, cur + dir * 40));
                b.style.marginLeft = next ? next + 'px' : '';
                if (!b.getAttribute('style')) b.removeAttribute('style');
            });
        });
    }

    // ----- Clear formatting -----
    function clearFormatting() {
        runEdit(ctx => {
            expandToBlockIfCollapsed(ctx.sel);
            document.execCommand('removeFormat', false, null);
            document.execCommand('unlink', false, null);
            getSelectedBlocks().forEach(blk => {
                ['fontSize', 'color', 'backgroundColor', 'lineHeight', 'textAlign', 'marginLeft'].forEach(prop => {
                    blk.style[prop] = '';
                });
                if (!blk.getAttribute('style')) blk.removeAttribute('style');
                clearSizesInside(blk);
            });
        });
    }

    // ----- Links -----
    function insertLink() {
        const ctx = focusEditor();
        if (!ctx) return;
        const saved = ctx.range.cloneRange();

        let url = window.prompt(STR.link_prompt, 'https://');
        if (!url) return;
        url = url.trim();
        if (!url || url === 'https://') return;
        if (!/^(https?:\/\/|mailto:|tel:)/i.test(url)) url = 'https://' + url.replace(/^\/+/, '');

        const ed = getCurrentEditor();
        try { ed.focus({ preventScroll: true }); } catch (e) { ed.focus(); }
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(saved);

        runEdit(c => {
            if (c.sel.isCollapsed) {
                const a = document.createElement('a');
                a.href = url;
                a.textContent = url.replace(/^(https?:\/\/|mailto:|tel:)/i, '');
                c.range.insertNode(a);
                const r = document.createRange();
                r.setStartAfter(a);
                r.collapse(true);
                c.sel.removeAllRanges();
                c.sel.addRange(r);
            } else {
                document.execCommand('createLink', false, url);
            }
            c.editor.querySelectorAll('a').forEach(a => {
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
            });
        });
    }

    function removeLink() {
        runEdit(ctx => {
            if (ctx.sel.isCollapsed) {
                let el = ctx.sel.anchorNode;
                if (el && el.nodeType === 3) el = el.parentElement;
                const a = el && el.closest ? el.closest('a') : null;
                if (a && ctx.editor.contains(a)) {
                    const r = document.createRange();
                    r.selectNodeContents(a);
                    ctx.sel.removeAllRanges();
                    ctx.sel.addRange(r);
                }
            }
            document.execCommand('unlink', false, null);
        });
    }

    // ----- Toolbar wiring -----
    const on = (id, fn) => { const el = $(id); if (el) el.addEventListener('click', fn); };

    on('btn-undo', undo);
    on('btn-redo', redo);

    on('btn-h1', () => setBlockType('h1'));
    on('btn-h2', () => setBlockType('h2'));
    on('btn-h3', () => setBlockType('h3'));
    on('btn-p', () => setBlockType('p'));

    on('btn-bold', () => execCmd('bold'));
    on('btn-italic', () => execCmd('italic'));
    on('btn-underline', () => execCmd('underline'));
    on('btn-strike', () => execCmd('strikeThrough'));
    on('btn-clear', clearFormatting);

    on('btn-left', () => execCmd('justifyLeft'));
    on('btn-center', () => execCmd('justifyCenter'));
    on('btn-right', () => execCmd('justifyRight'));
    on('btn-justify', () => execCmd('justifyFull'));

    on('btn-ul', () => execCmd('insertUnorderedList'));
    on('btn-ol', () => execCmd('insertOrderedList'));
    on('btn-indent', () => indent(1));
    on('btn-outdent', () => indent(-1));

    on('btn-quote', toggleQuote);
    on('btn-hr', () => runEdit(() => insertHtmlAtCaret('<hr>')));
    on('btn-link', insertLink);
    on('btn-unlink', removeLink);

    $('font-size').addEventListener('change', function () {
        if (this.value) applyFontSize(this.value);
    });

    $('line-spacing').addEventListener('change', function () {
        if (this.value) applyLineSpacing(this.value);
    });

    document.querySelectorAll('.color-swatch').forEach(swatch => {
        swatch.addEventListener('click', function () {
            if (this.dataset.type === 'highlight') {
                applyHighlight(this.dataset.color);
            } else {
                applyColor(this.dataset.color);
            }
        });
    });

    // Buttons must not steal the focus/selection from the editor
    document.querySelectorAll('.toolbar button, #paste-pop button').forEach(b => {
        b.addEventListener('mousedown', e => e.preventDefault());
    });

    // =================================================================
    // EDITOR EVENTS
    // =================================================================

    // Enter at the end of a verse block starts a normal paragraph
    function fixNewParagraph() {
        const ctx = selectionInEditor();
        if (!ctx) return;
        const blk = closestBlock(ctx.sel.anchorNode);
        if (blk && blk.classList && (blk.classList.contains('vref') || blk.classList.contains('vtxt')) && !hasRealContent(blk)) {
            blk.removeAttribute('class');
        }
    }

    let internalDrag = false;
    let tabLeaves = false;

    Object.keys(editors).forEach(code => {
        const ed = editors[code];
        if (!ed) return;

        ed.addEventListener('paste', onPaste);

        ed.addEventListener('input', e => {
            const type = e.inputType || '';
            if (type === 'insertParagraph') fixNewParagraph();
            if (!ed.firstChild || ed.innerHTML === '<br>') {
                elementsBefore = null;
                ed.innerHTML = '<p><br></p>';
                placeCaretAtStart(ed.firstChild);
            } else if (/^delete/.test(type) && resetIfEmptied(ed)) {
                elementsBefore = null;
            } else {
                tidyNewElements(ed, type);
                if (/^(delete|insertParagraph|insertFromDrop|insertLineBreak)/.test(type)) tidyStructure(ed);
            }
            scheduleHistory(code);
            afterContentChange(code);
        });

        ed.addEventListener('beforeinput', e => {
            if (e.inputType === 'historyUndo') { e.preventDefault(); undo(); return; }
            if (e.inputType === 'historyRedo') { e.preventDefault(); redo(); return; }
            rememberElements(ed);
        });

        ed.addEventListener('keydown', e => {
            const mod = (e.ctrlKey || e.metaKey) && !e.altKey;
            const k = (e.key || '').toLowerCase();

            if (mod && k === 'z') { e.preventDefault(); if (e.shiftKey) redo(); else undo(); return; }
            if (mod && k === 'y') { e.preventDefault(); redo(); return; }
            if (mod && !e.shiftKey && k === 'b') { e.preventDefault(); execCmd('bold'); return; }
            if (mod && !e.shiftKey && k === 'i') { e.preventDefault(); execCmd('italic'); return; }
            if (mod && !e.shiftKey && k === 'u') { e.preventDefault(); execCmd('underline'); return; }
            // Ctrl+Shift+V = always plain text (the paste event follows)
            if (mod && e.shiftKey && k === 'v') { forcePlainUntil = Date.now() + 1500; return; }

            if (e.key === 'Escape') {
                tabLeaves = true;
                return;
            }
            if (e.key === 'Tab') {
                // Esc then Tab moves on to the next control (no keyboard trap)
                if (tabLeaves) { tabLeaves = false; return; }
                e.preventDefault();
                indent(e.shiftKey ? -1 : 1);
                return;
            }
            if (!/^(Shift|Control|Meta|Alt|CapsLock)$/.test(e.key)) tabLeaves = false;
            if (!/^(Shift|Control|Meta|Alt|CapsLock)$/.test(e.key) && !mod) hidePastePop();
        });

        ed.addEventListener('mousedown', hidePastePop);

        // Dropped text goes through the same cleaning as a paste; dropped
        // files (images) are refused instead of becoming broken <img> tags
        ed.addEventListener('dragstart', () => { internalDrag = true; });
        ed.addEventListener('dragend', () => { internalDrag = false; });
        ed.addEventListener('drop', e => {
            if (internalDrag) {
                internalDrag = false;
                setTimeout(() => { recordHistory(code); afterContentChange(code); }, 0);
                return;
            }
            const dt = e.dataTransfer;
            e.preventDefault();
            if (!dt) return;
            let html = '';
            let text = '';
            try { html = dt.getData('text/html') || ''; } catch (err) { html = ''; }
            try { text = dt.getData('text/plain') || ''; } catch (err) { text = ''; }
            if (!html.trim() && !text) {
                if (dt.files && dt.files.length) flash('error', STR.paste_no_images);
                return;
            }
            let r = null;
            if (document.caretRangeFromPoint) {
                r = document.caretRangeFromPoint(e.clientX, e.clientY);
            } else if (document.caretPositionFromPoint) {
                const pos = document.caretPositionFromPoint(e.clientX, e.clientY);
                if (pos) {
                    r = document.createRange();
                    r.setStart(pos.offsetNode, pos.offset);
                    r.collapse(true);
                }
            }
            try { ed.focus({ preventScroll: true }); } catch (err) { ed.focus(); }
            if (r && ed.contains(r.startContainer)) {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(r);
            }
            handleIncoming(html, text, false, false);
        });
    });

    // ===================================================================
    // LANGUAGE TABS
    // ===================================================================
    document.querySelectorAll('.lang-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const code = tab.dataset.lang;
            if (!editors[code] || code === currentLang) return;
            recordHistory(currentLang);
            hidePastePop();
            currentLang = code;

            document.querySelectorAll('.lang-tab').forEach(t => {
                const active = t === tab;
                t.classList.toggle('active', active);
                t.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            LANGS.forEach(c => {
                if (editors[c]) editors[c].hidden = c !== currentLang;
            });

            loadBible(currentLang);
            updateEmptyState(editors[currentLang]);
            updateToolbarState();
            updateHistoryButtons();
        });
    });

    // ===================================================================
    // TOOLBAR STATE SYNC - buttons and dropdowns follow the cursor
    // ===================================================================

    function setActive(id, isOn) {
        const el = $(id);
        if (el) el.classList.toggle('active', !!isOn);
    }

    // A value that is not in the list gets its own option for as long as it
    // is needed (a hidden <option> shows as a blank row in iOS pickers)
    function showSelectValue(sel, value, label, matches) {
        const customId = sel.id + '-custom';
        let custom = $(customId);
        const match = Array.from(sel.options).find(o => o !== custom && matches(o.value));
        if (match) {
            if (custom) custom.remove();
            sel.value = match.value;
            return;
        }
        if (!custom) {
            custom = document.createElement('option');
            custom.id = customId;
            sel.insertBefore(custom, sel.firstChild);
        }
        custom.value = value;
        custom.textContent = label;
        sel.value = value;
    }

    function reflectFontSize(px) {
        showSelectValue($('font-size'), px + 'px', String(px), v => v === px + 'px');
    }

    function reflectLineSpacing(ratio) {
        const v = (Math.round(ratio * 10) / 10).toString();
        showSelectValue($('line-spacing'), v, v, o => Math.abs(parseFloat(o) - ratio) < 0.05);
    }

    function updateWordCount() {
        const out = $('word-count');
        if (!out) return;
        const ed = getCurrentEditor();
        const text = ed ? ed.innerText.trim() : '';
        const words = text ? (text.match(/\S+/g) || []).length : 0;
        const chars = text.replace(/\s+/g, ' ').length;
        out.textContent = words + ' ' + (STR.words || 'words') + ' · ' + chars + ' ' + (STR.characters || 'characters');
    }

    function updateToolbarState() {
        const ctx = selectionInEditor();
        const q = cmd => {
            try { return document.queryCommandState(cmd); } catch (e) { return false; }
        };
        const has = !!ctx;

        setActive('btn-bold', has && q('bold'));
        setActive('btn-italic', has && q('italic'));
        setActive('btn-underline', has && q('underline'));
        setActive('btn-strike', has && q('strikeThrough'));
        setActive('btn-left', has && q('justifyLeft'));
        setActive('btn-center', has && q('justifyCenter'));
        setActive('btn-right', has && q('justifyRight'));
        setActive('btn-justify', has && q('justifyFull'));
        setActive('btn-ul', has && q('insertUnorderedList'));
        setActive('btn-ol', has && q('insertOrderedList'));

        let tag = '', inQuote = false, el = null;
        if (ctx) {
            el = ctx.sel.focusNode;
            if (el && el.nodeType === 3) el = el.parentElement;
            const blk = closestBlock(ctx.sel.focusNode);
            tag = blk ? blk.tagName : '';
            if (el && el.closest) {
                const bq = el.closest('blockquote');
                inQuote = !!(bq && ctx.editor.contains(bq));
            }
        }
        setActive('btn-h1', tag === 'H1');
        setActive('btn-h2', tag === 'H2');
        setActive('btn-h3', tag === 'H3');
        setActive('btn-p', tag === 'P');
        setActive('btn-quote', inQuote);

        if (ctx && el && ctx.editor.contains(el)) {
            const basePx = parseFloat(getComputedStyle(ctx.editor).fontSize) || 16;
            const cs = getComputedStyle(el);
            reflectFontSize(Math.round(parseFloat(cs.fontSize) * 16 / basePx));

            const blk = closestBlock(ctx.sel.focusNode) || el;
            const bcs = getComputedStyle(blk);
            const ratio = bcs.lineHeight === 'normal'
                ? 1.2
                : parseFloat(bcs.lineHeight) / parseFloat(bcs.fontSize);
            reflectLineSpacing(ratio);
        }
        updateWordCount();
    }

    let stateRaf = null;
    document.addEventListener('selectionchange', () => {
        const ctx = selectionInEditor();
        if (ctx) lastRanges[currentLang] = ctx.range.cloneRange();
        if (stateRaf) return;
        stateRaf = requestAnimationFrame(() => {
            stateRaf = null;
            updateToolbarState();
        });
    });

    // Escape closes any open modal and the paste bar
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(m => m.classList.remove('show'));
            hidePastePop();
        }
    });

    // ===================================================================
    // BIBLE VERSES
    // ===================================================================
    async function loadBible(lang) {
        if (bibles[lang]) return bibles[lang];

        const file = (CONFIG.bibleFiles || {})[lang] || (CONFIG.bibleFiles || {}).en;
        if (!file) return null;
        try {
            const res = await fetch('/bible/bibles/' + file, { credentials: 'same-origin' });
            const data = await res.json();
            if (data && Object.keys(data).length > 0) {
                bibles[lang] = data;
                return data;
            }
        } catch (e) {
            console.error('Bible load failed for', lang, e);
        }
        return null;
    }

    const fillSelect = (sel, values) => {
        sel.innerHTML = '<option value="">' + escapeHtml(STR.select || '') + '...</option>';
        values.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v;
            sel.appendChild(opt);
        });
    };

    $('btn-verse').addEventListener('click', async () => {
        const bible = await loadBible(currentLang);
        if (!bible) {
            alert(STR.bible_not_available);
            return;
        }
        fillSelect($('v-book'), Object.keys(bible));
        $('v-chapter').innerHTML = '';
        $('v-from').innerHTML = '';
        $('v-to').innerHTML = '';
        $('v-preview').innerHTML = '';
        $('verse-modal').classList.add('show');
    });

    $('v-book').addEventListener('change', function () {
        const bible = bibles[currentLang];
        const book = this.value;
        fillSelect($('v-chapter'), book && bible && bible[book] ? Object.keys(bible[book]) : []);
        $('v-from').innerHTML = '';
        $('v-to').innerHTML = '';
        $('v-preview').innerHTML = '';
    });

    // Get verses with proper numbering
    function getVersesWithNumbers(bible, book, chapter) {
        if (!bible || !bible[book] || !bible[book][chapter]) return [];
        const verses = [];
        let num = 1;
        bible[book][chapter].forEach(item => {
            if (item.v) {
                verses.push({ n: num, v: item.v });
                num++;
            }
        });
        return verses;
    }

    $('v-chapter').addEventListener('change', function () {
        const book = $('v-book').value;
        const verses = book && this.value ? getVersesWithNumbers(bibles[currentLang], book, this.value) : [];
        const nums = verses.map(v => v.n);
        fillSelect($('v-from'), nums);
        fillSelect($('v-to'), nums);
        $('v-preview').innerHTML = '';
    });

    function selectedVerses() {
        const book = $('v-book').value;
        const chapter = $('v-chapter').value;
        const from = parseInt($('v-from').value, 10);
        let to = parseInt($('v-to').value, 10) || from;
        if (to < from) to = from;
        if (!book || !chapter || !from) return null;
        const verses = getVersesWithNumbers(bibles[currentLang], book, chapter).filter(v => v.n >= from && v.n <= to);
        const ref = book + ' ' + chapter + ':' + from + (to > from ? '-' + to : '');
        return { ref, verses };
    }

    function updateVersePreview() {
        const pick = selectedVerses();
        const preview = $('v-preview');
        if (!pick) {
            preview.innerHTML = '';
            return;
        }
        preview.innerHTML = '<strong>' + escapeHtml(pick.ref) + '</strong><br><br>' +
            pick.verses.map(v => '<sup>' + v.n + '</sup> ' + escapeHtml(v.v)).join(' ');
    }

    $('v-from').addEventListener('change', updateVersePreview);
    $('v-to').addEventListener('change', updateVersePreview);

    // Insert verse - using vref/vtxt classes to match welcome.css
    $('btn-insert').addEventListener('click', () => {
        const pick = selectedVerses();
        if (!pick) {
            alert(STR.select_all_fields);
            return;
        }
        const html = '<p class="vref">' + escapeHtml(pick.ref) + '</p><p class="vtxt">' +
            pick.verses.map(v => '<sup>' + v.n + '</sup> ' + escapeHtml(v.v)).join(' ') + '</p>';

        $('verse-modal').classList.remove('show');
        runEdit(() => insertHtmlAtCaret(html));
        flash('saved', STR.verse_inserted);
    });

    // Modal close
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('.modal').classList.remove('show'));
    });

    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', e => {
            if (e.target === modal) modal.classList.remove('show');
        });
    });

    // ===================================================================
    // AI: IMPROVE + TRANSLATE
    // ===================================================================

    async function postForm(url, params) {
        const fd = new URLSearchParams();
        Object.keys(params).forEach(k => fd.append(k, params[k]));
        const res = await fetch(url, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': CSRF }
        });
        const raw = await res.text();
        let data = null;
        try { data = JSON.parse(raw); } catch (e) { data = null; }
        if (!res.ok || !data || !data.success) {
            const msg = data
                ? (data.error || 'Error') + (data.detail ? ': ' + data.detail : '')
                : 'Server error ' + res.status + ': ' + raw.substring(0, 200);
            throw new Error(msg);
        }
        return data;
    }

    // Bible verse blocks in a translation get the real Bible text of the
    // target language: the books are matched by their position (all full
    // Bibles have the same 66 books in the same order), so "Johannes 3:16"
    // becomes "John 3:16" with the KJV words - not an AI paraphrase.
    async function localizeVerses(sourceHtml, translatedHtml, sourceLang, targetLang) {
        try {
            const parse = h => new DOMParser().parseFromString('<!DOCTYPE html><html><body>' + h + '</body></html>', 'text/html');
            const refs = Array.from(parse(sourceHtml).body.querySelectorAll('p.vref')).map(p => p.textContent.trim());
            if (!refs.length) return translatedHtml;

            const [sb, tb] = await Promise.all([loadBible(sourceLang), loadBible(targetLang)]);
            if (!sb || !tb) return translatedHtml;
            const sBooks = Object.keys(sb);
            const tBooks = Object.keys(tb);
            if (sBooks.length !== tBooks.length) return translatedHtml;

            const doc = parse(translatedHtml);
            const vrefs = Array.from(doc.body.querySelectorAll('p.vref'));
            if (vrefs.length !== refs.length) return translatedHtml; // structure changed: leave it

            vrefs.forEach((p, i) => {
                const m = refs[i].match(/^(.+?)\s+(\d+):(\d+)(?:\s*-\s*(\d+))?$/);
                if (!m) return;
                const idx = sBooks.indexOf(m[1].trim());
                if (idx < 0) return;
                const book = tBooks[idx];
                const chapter = m[2];
                const from = parseInt(m[3], 10);
                const to = m[4] ? parseInt(m[4], 10) : from;
                const verses = getVersesWithNumbers(tb, book, chapter).filter(v => v.n >= from && v.n <= to);
                if (!verses.length) return;
                p.textContent = book + ' ' + chapter + ':' + from + (to > from ? '-' + to : '');
                const txt = p.nextElementSibling;
                if (txt && txt.classList.contains('vtxt')) {
                    txt.innerHTML = verses.map(v => '<sup>' + v.n + '</sup> ' + escapeHtml(v.v)).join(' ');
                }
            });
            return doc.body.innerHTML;
        } catch (e) {
            console.error('Verse localisation failed', e);
            return translatedHtml;
        }
    }

    // While the AI works nothing can be typed: the answer replaces the
    // content that was sent, so anything typed meanwhile would be lost
    let busy = false;
    function setBusy(on) {
        busy = on;
        const box = document.querySelector('.container');
        if (on && document.activeElement && document.activeElement.blur) document.activeElement.blur();
        LANGS.forEach(c => { if (editors[c]) editors[c].setAttribute('contenteditable', on ? 'false' : 'true'); });
        if (box) {
            if (on) box.setAttribute('inert', '');
            else box.removeAttribute('inert');
            box.setAttribute('aria-busy', on ? 'true' : 'false');
        }
    }

    // Replace one editor's content as a single undoable step
    function replaceEditorContent(code, html) {
        const ed = editors[code];
        if (!ed) return;
        recordHistory(code);
        ed.innerHTML = sanitizeOwnHtml(html);
        normalizeEditor(ed);
        recordHistory(code);
        afterContentChange(code);
    }

    $('btn-improve').addEventListener('click', async () => {
        const code = currentLang;
        const content = serializeEditor(editors[code]);
        if (!content) {
            flash('error', STR.editor_empty);
            return;
        }

        const btn = $('btn-improve');
        const ed = editors[code];
        const caret = saveCaret(ed);
        btn.disabled = true;
        btn.textContent = STR.improving;
        showStatus('saving', STR.improving);
        hidePastePop();
        setBusy(true);

        let improved = null;
        try {
            const data = await postForm('/admin/api/ai/improve.php', { content, lang: code });
            if (!data.improved || !String(data.improved).trim()) throw new Error(STR.editor_empty);
            improved = data.improved;
        } catch (e) {
            flash('error', e.message, 8000);
        } finally {
            setBusy(false);
            btn.disabled = false;
            btn.textContent = STR.improve;
        }
        if (improved !== null) {
            replaceEditorContent(code, improved);
            // back to (about) the same place in the text
            try { ed.focus({ preventScroll: true }); } catch (e) { ed.focus(); }
            restoreCaret(ed, caret);
            flash('saved', STR.improved);
        }
    });

    // Translates ONE language at a time to avoid API timeouts, then saves
    // everything automatically. Every tab can be undone afterwards.
    $('btn-translate-all').addEventListener('click', async () => {
        const sourceLang = currentLang;
        const content = serializeEditor(editors[sourceLang]);
        if (!content) {
            flash('error', STR.editor_empty);
            return;
        }

        // The other tabs get overwritten - make sure the elder means it
        if (!confirm(STR.translate_confirm)) return;

        hidePastePop();
        const overlay = $('translate-overlay');
        const overlayText = overlay.querySelector('.translate-text');
        const overlayTextDefault = overlayText.textContent;
        overlay.classList.add('show');
        setBusy(true);

        document.querySelectorAll('.translate-progress .lang-badge').forEach(b => {
            b.classList.remove('active', 'done');
        });
        const sourceBadge = document.querySelector('.lang-badge[data-lang="' + sourceLang + '"]');
        if (sourceBadge) sourceBadge.classList.add('done');

        const targetLangs = LANGS.filter(l => l !== sourceLang);
        let successCount = 0;
        let errorLang = null;

        try {
            for (const targetLang of targetLangs) {
                const badge = document.querySelector('.lang-badge[data-lang="' + targetLang + '"]');
                if (badge) {
                    badge.classList.remove('done');
                    badge.classList.add('active');
                }

                errorLang = targetLang;
                const data = await postForm('/admin/api/ai/translate_all.php', {
                    content,
                    source_lang: sourceLang,
                    target_lang: targetLang
                });
                if (!data.translation || !String(data.translation).trim()) throw new Error(STR.editor_empty);
                replaceEditorContent(targetLang, await localizeVerses(content, data.translation, sourceLang, targetLang));
                if (badge) {
                    badge.classList.remove('active');
                    badge.classList.add('done');
                }
                successCount++;
                errorLang = null;
            }

            overlayText.textContent = STR.saving;
            const saved = await saveAll(true);
            if (saved) flash('saved', STR.translations_done_saved);
        } catch (e) {
            console.error('Translation error:', e);
            flash('error', (errorLang ? errorLang.toUpperCase() + ': ' : '') + e.message, 8000);
            if (successCount > 0) {
                alert(STR.translation_stopped + ' ' + (errorLang || '?').toUpperCase() + ': ' + e.message + '\n\n' + successCount + ' ' + STR.languages_completed);
            } else {
                alert(e.message);
            }
        } finally {
            overlayText.textContent = overlayTextDefault;
            overlay.classList.remove('show');
            setBusy(false);
        }
    });

    // ===================================================================
    // SAVE
    // Shared by the save button, Ctrl+S and the translate flow.
    // ===================================================================
    async function saveAll(fromTranslate) {
        if (isSaving || (busy && !fromTranslate)) return false;

        LANGS.forEach(c => recordHistory(c));
        const payload = {};
        LANGS.forEach(c => { payload[c] = serializeEditor(editors[c]); });

        if (LANGS.every(c => !payload[c])) {
            flash('error', STR.editor_empty);
            return false;
        }

        isSaving = true;
        const saveBtn = $('btn-save');
        saveBtn.disabled = true;
        showStatus('saving', STR.saving);

        try {
            const params = { csrf_token: CSRF };
            LANGS.forEach(c => { params['content_' + c] = payload[c]; });
            await postForm('/admin/api/elders/save_teaching.php', params);

            LANGS.forEach(c => { savedHtml[c] = payload[c]; });
            savedThisSession = true;
            hasChanges = isDirty();
            if (!hasChanges && DRAFT_KEY) store.del(DRAFT_KEY);
            hideDraftBar();
            statusHold = 0;
            refreshStatus();
            return true;
        } catch (e) {
            flash('error', (STR.save_failed || 'Save failed') + ': ' + e.message, 10000);
            return false;
        } finally {
            isSaving = false;
            saveBtn.disabled = false;
        }
    }

    $('btn-save').addEventListener('click', () => { saveAll(); });

    // Ctrl+S / Cmd+S saves
    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && (e.key || '').toLowerCase() === 's') {
            e.preventDefault();
            saveAll();
        }
    });

    // ===================================================================
    // STATUS
    // ===================================================================
    function showStatus(type, msg) {
        const badge = $('status');
        const icon = $('status-icon');
        const text = $('status-text');

        badge.className = 'status ' + type;
        icon.innerHTML = type === 'saving' ? '&#9203;'
            : type === 'saved' ? '&#9989;'
            : type === 'unsaved' ? '&#9998;'
            : type === 'ready' ? '&#128190;'
            : '&#10060;';
        text.textContent = msg;
    }

    // A message that stays visible for a moment, then the real state returns
    function flash(type, msg, ms) {
        showStatus(type, msg);
        statusHold = Date.now() + (ms || 2500);
        clearTimeout(statusTimer);
        statusTimer = setTimeout(() => { statusHold = 0; refreshStatus(); }, ms || 2500);
    }

    function refreshStatus() {
        if (isSaving || Date.now() < statusHold) return;
        if (hasChanges) showStatus('unsaved', STR.unsaved_changes);
        else if (savedThisSession) showStatus('saved', STR.saved);
        else showStatus('ready', STR.ready_to_save);
    }

    // ===================================================================
    // DRAFT - local backup until the teaching is saved
    // ===================================================================
    // The draft offered in the bar. It is parked under ":prev" so the work
    // done while the bar is still showing keeps being backed up as normal.
    let offeredDraft = null;

    function readDraft(key) {
        if (!DRAFT_KEY) return null;
        try {
            const d = JSON.parse(store.get(key || DRAFT_KEY) || 'null');
            return d && d.contents ? d : null;
        } catch (e) {
            return null;
        }
    }

    function writeDraftNow() {
        if (!DRAFT_KEY) return;
        const contents = {};
        let differs = false;
        LANGS.forEach(c => {
            contents[c] = serializeEditor(editors[c]);
            if (contents[c] !== (savedHtml[c] || '')) differs = true;
        });
        if (!differs) {
            store.del(DRAFT_KEY);
            return;
        }
        store.set(DRAFT_KEY, JSON.stringify({ ts: Date.now(), contents }));
    }

    function scheduleDraft() {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(writeDraftNow, 1000);
    }

    function hideDraftBar() {
        const bar = $('draft-bar');
        if (bar) bar.hidden = true;
        offeredDraft = null;
        if (DRAFT_KEY) store.del(DRAFT_KEY + ':prev');
    }

    function offerDraft() {
        const differsFromSaved = d => d && LANGS.some(c => (d.contents[c] || '') !== (savedHtml[c] || ''));
        let d = readDraft();
        if (!differsFromSaved(d)) d = readDraft(DRAFT_KEY + ':prev');
        if (!differsFromSaved(d)) {
            store.del(DRAFT_KEY);
            store.del(DRAFT_KEY + ':prev');
            return;
        }
        offeredDraft = d;
        store.set(DRAFT_KEY + ':prev', JSON.stringify(d));
        const time = $('draft-time');
        if (time) {
            try {
                time.textContent = '(' + new Date(d.ts).toLocaleString(document.documentElement.lang || undefined) + ')';
            } catch (e) {
                time.textContent = '';
            }
        }
        $('draft-bar').hidden = false;
    }

    on('btn-draft-restore', () => {
        const d = offeredDraft;
        hideDraftBar();
        if (!d) return;
        LANGS.forEach(c => {
            const html = d.contents[c] || '';
            if (!editors[c] || html === serializeEditor(editors[c])) return;
            replaceEditorContent(c, html);
        });
        updateTabIndicators();
        scheduleDraft();
    });

    on('btn-draft-discard', () => {
        hideDraftBar();
        writeDraftNow();
    });

    // ===================================================================
    // PREVENT LOSS
    // ===================================================================
    window.addEventListener('beforeunload', e => {
        if (isDirty()) {
            writeDraftNow();
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // The Android app can be closed from the background without warning
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') writeDraftNow();
    });

    // ===================================================================
    // INIT
    // ===================================================================
    LANGS.forEach(code => {
        const ed = editors[code];
        if (!ed) return;
        normalizeEditor(ed);
        savedHtml[code] = serializeEditor(ed);
        updateEmptyState(ed);
        recordHistory(code);
    });
    loadBible(currentLang);
    updateTabIndicators();
    updateToolbarState();
    updateHistoryButtons();
    offerDraft();
})();
