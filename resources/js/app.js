import * as bootstrap from 'bootstrap';
import Alpine from 'alpinejs';
import 'bootstrap-icons/font/bootstrap-icons.css';

window.bootstrap = bootstrap;
window.Alpine = Alpine;

/* ---------------------------------------------------------------------------
 * helpers
 * ------------------------------------------------------------------------- */

const uuid = () => {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
};

/** "Rs. 14,000" (decimals only when needed) – mirrors App\Support\Money::format */
const fmtMoney = (value) => {
    const n = Number(String(value ?? 0).replace(/,/g, ''));
    if (!Number.isFinite(n)) return 'Rs. 0';
    const negative = n < 0 && Math.abs(n) >= 0.005;
    const abs = Math.abs(n);
    const hasDecimals = Math.abs(abs - Math.round(abs)) >= 0.005;
    const text = abs.toLocaleString('en-US', {
        minimumFractionDigits: hasDecimals ? 2 : 0,
        maximumFractionDigits: 2,
    });
    return `${negative ? '-' : ''}Rs. ${text}`;
};

window.fmtMoney = fmtMoney;
window.uuid = uuid;

const num = (v) => {
    const n = parseFloat(String(v ?? '').replace(/,/g, ''));
    return Number.isFinite(n) ? n : 0;
};

/* Sanitise money inputs (capture phase, so x-model sees the clean value). */
document.addEventListener(
    'input',
    (e) => {
        const el = e.target;
        if (!(el instanceof HTMLInputElement) || !el.hasAttribute('data-money')) return;

        const signed = el.getAttribute('data-money') === 'signed';
        let v = el.value.replace(/,/g, '');
        const negative = signed && v.trim().startsWith('-');
        v = v.replace(/[^0-9.]/g, '');
        const parts = v.split('.');
        v = parts.shift() + (parts.length ? '.' + parts.join('').slice(0, 2) : '');
        v = (negative ? '-' : '') + v;
        if (v !== el.value) el.value = v;
    },
    true,
);

/* ---------------------------------------------------------------------------
 * Alpine components
 * ------------------------------------------------------------------------- */

/**
 * Wrapper for every money-entry form: idempotency key, double-submit lock and
 * live "new balance" maths.
 */
Alpine.data('txnForm', (cfg = {}) => ({
    key: cfg.key || uuid(),
    submitting: false,
    vendor: cfg.vendor || null,
    bill: cfg.bill ?? '',
    paid: cfg.paid ?? '',
    amount: cfg.amount ?? '',
    source: cfg.source || 'canteen_cash',
    counted: cfg.counted ?? '',
    expected: cfg.expected ?? 0,
    cashAvailable: num(cfg.cashAvailable ?? 0),
    entryMode: cfg.entryMode || 'total',
    items: cfg.items?.length
        ? cfg.items
        : [{ item_name: '', quantity: '', unit: 'pcs', rate: '' }],
    todayLines: cfg.todayLines || [],
    todayUrl: cfg.todayUrl || null,
    fmt: fmtMoney,

    init() {
        if (!this.key) this.key = uuid();
        window.addEventListener('pageshow', (e) => {
            if (e.persisted) this.submitting = false;
        });
        if (this.vendor && this.todayUrl) {
            this.loadTodayDeliveries();
        }
    },

    n: num,

    get prev() {
        return this.vendor ? num(this.vendor.balance) : 0;
    },
    get itemsTotal() {
        return this.items.reduce((sum, row) => sum + num(row.quantity) * num(row.rate), 0);
    },
    get itemsQtyTotal() {
        return this.items.reduce((sum, row) => sum + num(row.quantity), 0);
    },
    get todayQtyTotal() {
        return this.todayLines.reduce((sum, row) => sum + num(row.quantity), 0);
    },
    get todayAmountTotal() {
        return this.todayLines.reduce((sum, row) => sum + num(row.line_total), 0);
    },
    get effectiveBill() {
        return this.entryMode === 'itemized' ? this.itemsTotal : num(this.bill);
    },
    /** purchase form: previous + bill - paid */
    get newBalanceBill() {
        return this.prev + this.effectiveBill - num(this.paid);
    },
    /** payment form: previous - amount */
    get newBalancePay() {
        return this.prev - num(this.amount);
    },
    get unpaid() {
        return this.effectiveBill - num(this.paid);
    },
    get overpaid() {
        const paid = num(this.paid);
        if (paid <= 0) return false;
        return paid > this.prev + this.effectiveBill + 0.001;
    },
    get difference() {
        return num(this.counted) - num(this.expected);
    },
    /** Shaam ka hisab: jitna cash hai aur jitna dena hai — chhota wala. */
    get settleSuggest() {
        const owed = Math.max(this.prev, 0);
        if (owed <= 0) return 0;
        if (this.cashAvailable <= 0) return 0;
        return Math.min(owed, this.cashAvailable);
    },
    fillSettleSuggest() {
        const v = this.settleSuggest;
        this.amount = v > 0 ? String(Math.round(v * 100) / 100) : '';
    },
    describeBalance(v) {
        if (Math.abs(v) < 0.005) return 'Koi baqaya nahi';
        return v > 0 ? `${fmtMoney(v)} dena hai` : `${fmtMoney(Math.abs(v))} advance`;
    },
    balanceClass(v) {
        if (Math.abs(v) < 0.005) return '';
        return v > 0 ? 'text-owed' : 'text-adv';
    },
    lineTotal(row) {
        return num(row.quantity) * num(row.rate);
    },
    /** Apply vendor defaults (fixed rate / item name) onto a row. */
    applyVendorDefaults(row = {}) {
        const v = this.vendor || {};
        const fixed = !!v.rate_fixed && num(v.default_rate) > 0;
        return {
            item_name: row.item_name || v.default_item_name || v.name || '',
            quantity: row.quantity ?? '',
            unit: row.unit || v.default_unit || 'pcs',
            rate: fixed
                ? String(num(v.default_rate))
                : (row.rate || (v.default_rate ? String(num(v.default_rate)) : '')),
        };
    },
    /** Nayi delivery row — pehle wali cheez ka naam/rate copy, qty khali focus ke liye. */
    addItem() {
        const last = this.items[this.items.length - 1] || {};
        this.items.push(this.applyVendorDefaults({
            item_name: last.item_name || '',
            quantity: '',
            unit: last.unit || '',
            rate: last.rate || '',
        }));
        this.$nextTick(() => {
            const inputs = this.$root.querySelectorAll('[data-qty-input]');
            const el = inputs[inputs.length - 1];
            el?.focus();
            el?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        });
    },
    removeItem(index) {
        if (this.items.length <= 1) {
            this.items[0] = this.applyVendorDefaults({ quantity: '', rate: this.items[0]?.rate || '' });
            return;
        }
        this.items.splice(index, 1);
    },
    setMode(mode) {
        this.entryMode = mode;
        if (mode === 'total' && this.itemsTotal > 0 && !num(this.bill)) {
            this.bill = String(Math.round(this.itemsTotal * 100) / 100);
        }
    },
    setVendor(v) {
        this.vendor = v;
        if (!v) {
            this.todayLines = [];
            return;
        }
        if (v.rate_fixed && num(v.default_rate) > 0) {
            this.entryMode = 'itemized';
            this.items = [this.applyVendorDefaults({ quantity: '' })];
            this.bill = '';
            this.$nextTick(() => document.getElementById('qty_0')?.focus());
        } else {
            this.entryMode = 'total';
            this.items = [{ item_name: '', quantity: '', unit: 'pcs', rate: '' }];
            this.$nextTick(() => document.getElementById('bill_amount')?.focus());
        }
        this.loadTodayDeliveries();
    },
    async loadTodayDeliveries() {
        if (!this.todayUrl || !this.vendor?.id) {
            this.todayLines = [];
            return;
        }
        try {
            const dateInput = this.$root.querySelector('input[name="business_date"]');
            const date = dateInput?.value || '';
            const res = await fetch(`${this.todayUrl}?vendor_id=${this.vendor.id}&date=${encodeURIComponent(date)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const data = await res.json();
            this.todayLines = data.lines || [];
            if (data.rate_fixed) {
                this.vendor = { ...this.vendor, rate_fixed: true, default_rate: data.default_rate || this.vendor.default_rate };
            }
            if (this.items.length === 1) {
                const row = this.items[0];
                if (data.last_rate && (!num(row.rate) || data.rate_fixed)) {
                    row.rate = String(data.last_rate);
                }
                if (data.last_item_name && !row.item_name) {
                    row.item_name = data.last_item_name;
                }
                if (data.last_unit && !row.unit) {
                    row.unit = data.last_unit;
                }
            }
        } catch (_) {
            this.todayLines = [];
        }
    },
    /** Blocks double taps; buttons stay enabled so the clicked button's name/value is still posted. */
    onSubmit(event) {
        if (this.submitting) {
            event.preventDefault();
            return;
        }
        this.submitting = true;
    },
}));

/** Vendor typeahead: debounced search, recent list when empty, keyboard + touch. */
Alpine.data('vendorAutocomplete', (cfg = {}) => ({
    url: cfg.url,
    focusId: cfg.focus || null,
    selected: cfg.selected || null,
    query: cfg.selected ? cfg.selected.name : '',
    items: [],
    open: false,
    loading: false,
    active: -1,
    timer: null,
    controller: null,
    fmt: fmtMoney,

    init() {
        // let the parent form know about a pre-selected vendor (e.g. ?vendor=ID or old input)
        if (this.selected) this.$nextTick(() => this.$dispatch('vendor-selected', this.selected));
    },

    onFocus() {
        if (this.selected) return;
        this.open = true;
        this.load();
    },

    onInput() {
        this.open = true;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.load(), 200);
    },

    async load() {
        if (this.controller) this.controller.abort();
        this.controller = new AbortController();
        this.loading = true;
        try {
            const res = await fetch(`${this.url}?q=${encodeURIComponent(this.query.trim())}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: this.controller.signal,
            });
            if (!res.ok) throw new Error('search failed');
            this.items = await res.json();
            this.active = this.items.length ? 0 : -1;
            this.loading = false;
        } catch (e) {
            if (e.name !== 'AbortError') {
                this.items = [];
                this.loading = false;
            }
        }
    },

    move(step) {
        if (!this.open) {
            this.open = true;
            this.load();
            return;
        }
        if (!this.items.length) return;
        this.active = (this.active + step + this.items.length) % this.items.length;
        this.$nextTick(() => {
            const el = this.$refs.list?.children[this.active];
            el?.scrollIntoView({ block: 'nearest' });
        });
    },

    enter(e) {
        if (this.open && this.active >= 0 && this.items[this.active]) {
            e.preventDefault();
            this.choose(this.items[this.active]);
        }
    },

    choose(vendor) {
        this.selected = vendor;
        this.query = vendor.name;
        this.open = false;
        this.$dispatch('vendor-selected', vendor);
        if (this.focusId) {
            this.$nextTick(() => {
                const el = document.getElementById(this.focusId);
                if (el) {
                    el.focus();
                    if (el.select) el.select();
                }
            });
        }
    },

    clear() {
        this.selected = null;
        this.query = '';
        this.items = [];
        this.$dispatch('vendor-selected', null);
        this.$nextTick(() => {
            this.$refs.q?.focus();
            this.open = true;
            this.load();
        });
    },
}));

/** Camera / gallery capture with client-side resize (max 1600px, JPEG 75%). */
Alpine.data('imageCapture', (cfg = {}) => ({
    preview: cfg.preview || null,
    busy: false,
    uploading: false,
    info: '',
    maxWidth: cfg.maxWidth || 1600,
    quality: cfg.quality || 0.75,

    async pick(event) {
        const input = event.target;
        const file = input.files && input.files[0];
        if (!file) return;

        this.busy = true;
        this.info = '';
        try {
            const blob = await this.resize(file);
            if (blob && blob.size < file.size) {
                const name = (file.name || 'bill').replace(/\.[^.]+$/, '') + '.jpg';
                const compressed = new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
                const dt = new DataTransfer();
                dt.items.add(compressed);
                input.files = dt.files;
                this.info = `${(file.size / 1024).toFixed(0)} KB → ${(compressed.size / 1024).toFixed(0)} KB`;
            }
        } catch (e) {
            // keep the original file – the server compresses it again anyway
        }

        if (this.preview) URL.revokeObjectURL(this.preview);
        this.preview = URL.createObjectURL(input.files[0]);
        this.busy = false;
    },

    async resize(file) {
        if (!file.type.startsWith('image/')) return null;

        let bitmap;
        if (window.createImageBitmap) {
            try {
                bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
            } catch (e) {
                bitmap = null;
            }
        }
        if (!bitmap) {
            bitmap = await new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = reject;
                img.src = URL.createObjectURL(file);
            });
        }

        const width = bitmap.width || bitmap.naturalWidth;
        const height = bitmap.height || bitmap.naturalHeight;
        const scale = Math.min(1, this.maxWidth / width);
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(width * scale);
        canvas.height = Math.round(height * scale);
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

        return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', this.quality));
    },

    clear() {
        if (this.preview) URL.revokeObjectURL(this.preview);
        this.preview = null;
        this.info = '';
        if (this.$refs.file) this.$refs.file.value = '';
    },
}));

/** "Load more" for the dashboard activity feed. */
Alpine.data('activityFeed', (cfg = {}) => ({
    url: cfg.url,
    date: cfg.date,
    next: cfg.next || null,
    loading: false,
    failed: false,

    async more() {
        if (!this.next || this.loading) return;
        this.loading = true;
        this.failed = false;
        try {
            const res = await fetch(`${this.url}?date=${encodeURIComponent(this.date)}&page=${this.next}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('failed');
            const data = await res.json();
            const before = this.$refs.list.children.length;
            this.$refs.list.insertAdjacentHTML('beforeend', data.html);
            const added = Array.from(this.$refs.list.children).slice(before);
            added.forEach((el) => window.Alpine?.initTree?.(el));
            this.next = data.has_more ? data.next_page : null;
        } catch (e) {
            this.failed = true;
        }
        this.loading = false;
    },
}));

/** Setup wizard rows. */
Alpine.data('vendorRows', (initial = []) => ({
    submitting: false,
    rows: initial.length ? initial : [{ name: '', phone: '', opening_balance: '', balance_type: 'owed' }],
    add() {
        this.rows.push({ name: '', phone: '', opening_balance: '', balance_type: 'owed' });
        this.$nextTick(() => {
            const inputs = this.$root.querySelectorAll('[data-row-name]');
            inputs[inputs.length - 1]?.focus();
        });
    },
    remove(i) {
        if (this.rows.length > 1) this.rows.splice(i, 1);
    },
}));

/** App shell: page loader + refresh. One label only: "Loading…". */
Alpine.data('pageShell', () => ({
    moreOpen: false,
    pageBusy: true,

    syncBody() {
        document.body.classList.toggle('is-page-loading', this.pageBusy);
    },

    ready() {
        this.syncBody();
        requestAnimationFrame(() => {
            setTimeout(() => {
                this.pageBusy = false;
                this.syncBody();
            }, 220);
        });
    },

    refresh() {
        if (this.pageBusy) return;
        this.pageBusy = true;
        this.syncBody();
        location.reload();
    },

    onPageShow(event) {
        if (event.persisted) {
            this.pageBusy = false;
            this.syncBody();
        }
    },
}));

Alpine.start();

/* ---------------------------------------------------------------------------
 * PWA
 * ------------------------------------------------------------------------- */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
