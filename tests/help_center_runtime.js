const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'includes', 'help_center.php'), 'utf8');
const scripts = [...source.matchAll(/<script>([\s\S]*?)<\/script>/g)];
if (!scripts.length) throw new Error('help center script missing');

const handlers = {};
const makeClassList = (initial = []) => {
    const values = new Set(initial);
    return {
        add: (...items) => items.forEach((item) => values.add(item)),
        remove: (...items) => items.forEach((item) => values.delete(item)),
        toggle: (item, force) => {
            if (force === undefined) {
                if (values.has(item)) {
                    values.delete(item);
                    return false;
                }
                values.add(item);
                return true;
            }
            if (force) values.add(item);
            else values.delete(item);
            return force;
        },
        contains: (item) => values.has(item),
    };
};

const closeButton = {
    addEventListener(type, callback) {
        handlers[`close:${type}`] = callback;
    },
};
const panel = {
    parentElement: null,
    classList: makeClassList(['offcanvas', 'd-none']),
    style: {},
    attrs: {},
    setAttribute(key, value) {
        this.attrs[key] = value;
    },
    addEventListener(type, callback) {
        handlers[`panel:${type}`] = callback;
    },
    querySelector(selector) {
        return selector === '[data-bs-dismiss="offcanvas"]' ? closeButton : null;
    },
};
const fab = {
    parentElement: null,
    classList: makeClassList(['help-fab', 'd-none']),
    style: {},
    attrs: { 'aria-hidden': 'true' },
    setAttribute(key, value) {
        this.attrs[key] = value;
    },
    removeAttribute(key) {
        delete this.attrs[key];
    },
    addEventListener(type, callback) {
        handlers[`fab:${type}`] = callback;
    },
};
const body = {
    style: {},
    children: [],
    appendChild(node) {
        this.children.push(node);
        node.parentElement = this;
    },
};
const footer = {
    getBoundingClientRect() {
        return { top: 100 };
    },
};

panel.parentElement = { name: 'wrapper' };
fab.parentElement = { name: 'wrapper' };
global.window = {
    bootstrap: undefined,
    innerHeight: 900,
    addEventListener() {},
};
global.document = {
    body,
    addEventListener(type, callback) {
        handlers[`document:${type}`] = callback;
    },
    getElementById(id) {
        return id === 'helpCenterOffcanvas' ? panel : null;
    },
    querySelector(selector) {
        if (selector === '[data-help-center-trigger]') return fab;
        if (selector === '.main-footer') return footer;
        return null;
    },
    querySelectorAll() {
        return [];
    },
    createElement() {
        return {
            className: '',
            addEventListener() {},
            remove() {
                this.removed = true;
            },
        };
    },
};

eval(scripts.at(-1)[1]);
handlers['document:DOMContentLoaded']();

if (fab.classList.contains('d-none') || fab.attrs['aria-hidden'] !== undefined) {
    throw new Error('help button remains hidden after initialization');
}
if (fab.style.opacity !== '1' || fab.style.pointerEvents !== 'auto') {
    throw new Error('visible footer disabled the help button');
}

handlers['fab:click']({ preventDefault() {}, stopPropagation() {} });
if (!panel.classList.contains('show')) throw new Error('help panel did not open');
if (panel.style.visibility !== 'visible') throw new Error('help panel is not visible');
if (fab.attrs['aria-expanded'] !== 'true') throw new Error('help button state was not updated');
if (fab.style.opacity !== '0' || fab.style.pointerEvents !== 'none') {
    throw new Error('help button did not hide after opening panel');
}
if (!body.children.some((node) => node.className === 'offcanvas-backdrop fade show')) {
    throw new Error('help panel backdrop missing');
}

// Simulate closing the help panel via the close button trigger
handlers['close:click']({ preventDefault() {} });
if (panel.classList.contains('show')) throw new Error('help panel did not close');
if (fab.style.opacity !== '1' || fab.style.pointerEvents !== 'auto') {
    throw new Error('help button did not restore after closing panel');
}

console.log('help center runtime OK');
