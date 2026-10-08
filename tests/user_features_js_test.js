/* Actual panel lifecycle adapter with synthetic DOM/native callback boundaries. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
let checks = 0;
function check(actual, expected) { checks++; assert.deepEqual(actual, expected); }
const events = {}, bootstrapEvents = {};
const document = {
    activeElement: null,
    addEventListener(name, callback) { events[name] = callback; },
    body: {children: []}
};
class Element {
    constructor(name, tabIndex = 0) {
        this.tagName = name.toUpperCase(); this.tabIndex = tabIndex;
        this.attrs = {}; this.children = []; this.disabled = false;
        this.visible = true; this.isConnected = true;
    }
    contains(node) { return node === this || this.children.some(child => child.contains(node)); }
    hasAttribute(name) { return Object.hasOwn(this.attrs, name); }
    getAttribute(name) { return this.attrs[name] ?? null; }
    setAttribute(name, value) { this.attrs[name] = value; }
    removeAttribute(name) { delete this.attrs[name]; }
    getClientRects() { return this.visible ? [{}] : []; }
    closest() { return this.hasAttribute('inert') ? this : null; }
    querySelector() { return this.children.find(child => child.className === 'close'); }
    querySelectorAll() { return this.children; }
    focus() { document.activeElement = this; if (events.focusin) { events.focusin({target: this}); } }
}
const page = new Element('main'), opener = new Element('button'); page.children = [opener];
const alreadyHidden = new Element('aside'); alreadyHidden.setAttribute('aria-hidden', 'false'); alreadyHidden.setAttribute('inert', '');
const script = new Element('script'), panel = new Element('div', -1);
document.body.children = [page, alreadyHidden, script, panel]; document.activeElement = opener;
const observers = [];
function jQuery(node) {
    if (node === document) { return {on(names, selector, callback) { bootstrapEvents[names] = callback; }}; }
    if (typeof node === 'string') { return {attr(name, value) { panel.toggleValue = value; }}; }
    return {children(selector) { check(selector, '.dropdown-toggle'); return {attr(name, value) { node.setAttribute(name, value); }}; }};
}
class MutationObserver {
    constructor(callback) { this.callback = callback; observers.push(this); }
    observe(target, options) { check(target, panel); check(JSON.stringify(options), JSON.stringify({childList: true, subtree: true})); }
}
const context = {window: {}, document, jQuery, MutationObserver};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/pawtucket/js/user-features.js'), 'utf8'), context);
const menu = new Element('a');
bootstrapEvents['shown.bs.dropdown hidden.bs.dropdown'].call(menu, {type: 'shown'}); check(menu.getAttribute('aria-expanded'), 'true');
bootstrapEvents['shown.bs.dropdown hidden.bs.dropdown'].call(menu, {type: 'hidden'}); check(menu.getAttribute('aria-expanded'), 'false');
bootstrapEvents['shown.bs.collapse hidden.bs.collapse']({type: 'shown'}); check(panel.toggleValue, 'true');
bootstrapEvents['shown.bs.collapse hidden.bs.collapse']({type: 'hidden'}); check(panel.toggleValue, 'false');
let opened = 0, closed = 0;
const controller = {
    onOpenCallback(value) { check(this, controller); check(value, 'native argument'); opened++; },
    finallyCallback(value) { check(this, controller); check(value, 'close argument'); closed++; }
};
context.window.tadlAccessiblePanel(controller, panel);
controller.onOpenCallback('native argument');
check(opened, 1); check(document.activeElement, panel); check(panel.getAttribute('aria-hidden'), 'false');
check(page.hasAttribute('inert'), true); check(page.getAttribute('aria-hidden'), 'true'); check(script.hasAttribute('inert'), false);
const close = new Element('button'); close.className = 'close';
const download = new Element('summary'), help = new Element('button');
const hidden = new Element('button'); hidden.visible = false;
const disabled = new Element('button'); disabled.disabled = true;
const noTab = new Element('input', -1);
panel.children = [close, download, help, hidden, disabled, noTab];
observers[0].callback(); check(document.activeElement, close);
function tab(shift = false) {
    let prevented = false;
    events.keydown({key: 'Tab', shiftKey: shift, preventDefault() { prevented = true; }});
    return prevented;
}
check(tab(true), true); check(document.activeElement, help);
check(tab(), true); check(document.activeElement, close);
download.focus(); check(tab(), false); // Native tabbing between ordinary controls remains intact.
observers[0].callback(); check(document.activeElement, download); // AJAX slide changes must not steal focus.
opener.focus(); check(document.activeElement, panel); // Fallback for browsers without inert.
check(tab(), true); check(document.activeElement, close);
panel.children = []; document.activeElement = panel;
check(tab(), true); check(document.activeElement, panel);
panel.children = [close, download, help]; download.tabIndex = 2; help.tabIndex = 1;
document.activeElement = help; check(tab(true), true); check(document.activeElement, close);
check(tab(), true); check(document.activeElement, help); // Positive tabindex precedes DOM order.
controller.finallyCallback('close argument');
check(closed, 1); check(panel.getAttribute('aria-hidden'), 'true'); check(document.activeElement, opener);
check(page.hasAttribute('inert'), false); check(page.getAttribute('aria-hidden'), null);
check(alreadyHidden.hasAttribute('inert'), true); check(alreadyHidden.getAttribute('aria-hidden'), 'false');
check(tab(), false);
// Reopening uses the new opener; disappearing openers must not receive focus.
const nextOpener = new Element('a'); page.children.push(nextOpener); nextOpener.focus();
controller.onOpenCallback('native argument'); nextOpener.isConnected = false;
controller.finallyCallback('close argument'); check(document.activeElement === nextOpener, false);
check(opened, 2); check(closed, 2);
console.log(`Viewer accessibility passed: ${checks} assertions (focus, AJAX lifecycle, native callbacks and Bootstrap ARIA).`);
