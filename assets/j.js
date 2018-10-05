! function() {
    function generateId() {
        for (var e = "vp_", t = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", r = 0; r < 8; r++) e += t.charAt(Math.floor(Math.random() * t.length));
        return e
    }

    function isArray(e) {
        return e.constructor === Array
    }

    function isInteger(e) {
        return parseInt(e) == e && e >= 0
    }

    function isBoolean(e) {
        return "boolean" == typeof e
    }

    function isObject(e) {
        return "object" == typeof e
    }

    function isFunction(e) {
        if (!e) return 0;
        var t = {};
        return e && "[object Function]" === t.toString.call(e)
    }

    function hasData(e) {
        var t = !1,
            r = e.attributes;
        r = Array.prototype.slice.call(r);
        for (key in r) {
            var i = r[key].nodeName;
            i && i.indexOf("data") > -1 && (t = !0)
        }
        return t
    }

    function parseBoolean(e) {
        return 0 == e.length ? null : "true" == e.toLowerCase()
    }

    function parseObject(e) {
        try {
            return JSON.parse(e)
        } catch (t) {
            return e
        }
    }

    function parseFunction(string) {
        try {
            return eval(string)
        } catch (e) {
            return string
        }
    }

    function setupSpinner() {
        var e = config.Url + "css/wyrepay.css",
            t = document.head,
            r = document.createElement("link");
        spindiv = document.createElement("div");
        for (var i = 0, n = document.styleSheets, o = 0, a = n.length; o < a; o++) n[o].href == e && (i = 1);
        i || (r.type = "text/css", r.rel = "stylesheet", r.href = e, t.appendChild(r))
    }

    function loadSpinner(e) {
        void 0 == e && (e = "Loading payment interface ... Please Wait ..."), void 0 == document.getElementById("wp-fading-circle") ? (spindiv.id = "wp-fading-circle", spindiv.innerHTML = '<div id="loader" class="loader loader-default is-active" data-text="' + e + '"></div>', document.body.appendChild(spindiv)) : document.getElementById("wp-fading-circle").innerHTML = '<div id="loader" class="loader loader-default is-active" data-text="' + e + '"></div>'
    }

    function buildParamUrl(e) {
        return Object.keys(e).map(function(t) {
            return encodeURIComponent(t) + "=" + encodeURIComponent(e[t])
        }).join("&")
    }

    function parseForm(e) {
        if (form = document.getElementById(e), !form) throw alert("Cannot locate payment form"), new Error("Cannot locate payment form");
        for (var t = {}, r = form.querySelectorAll("input"), i = 0; i < r.length; i++) "string" == typeof r[i].name && (t[r[i].name] = r[i].value);
        return t.form = e, t.recurrent && (t.recurrent = parseBoolean(t.recurrent)), t.closed && (t.closed = parseFunction(t.closed)), t.success && (t.success = parseFunction(t.success)), t.failed && (t.failed = parseFunction(t.failed)), t
    }

    function _payrequest(e) {
        this.iframeLoaded = 0, this.iframeOpen = 0, this.params = e, setupSpinner(), config.error || (void 0 === e.form ? this.buildFrame() : this.handleFormSubmit(this, e.form))
    }
    var config = {
            Url: "https://wyrepay.com/"
        },
        eventMethod = window.addEventListener ? "addEventListener" : "attachEvent",
        eventer = window[eventMethod],
        loadComplete = 0,
        timeout = 0,
        spindiv, error = 0,
        messageEvent = "attachEvent" == eventMethod ? "onmessage" : "message";
    _payrequest.prototype.handleFormSubmit = function(e, t) {
        var r = function(r) {
            r.preventDefault(), e.params = parseForm(t), e.params.id = generateId(), e.params.webload = e.params.id, delete e.params.form, _payrequest.prototype.buildFrame.call(e)
        };
        form = document.getElementById(t), form.addEventListener("submit", r)
    }, _payrequest.prototype.buildFrame = function() {
        loadSpinner(this.params.loadText);
        for (var e = document.getElementsByTagName("div"), t = 0, r = 0; r < e.length; r++) {
            var i = document.defaultView.getComputedStyle(e[r], null).getPropertyValue("z-index");
            i > t && "auto" != i && (t = i)
        }
        void 0 == this.params.items || (this.params.items = JSON.stringify(this.params.items));
        for (var n = this.params, o = JSON.parse(JSON.stringify(n)), a = ["loadText", "form", "success", "failed", "closed"], r = 0; r < a.length; r++) delete o[a[r]];
        var s = this.params.url ? this.params.url + "/id/" + this.params.id + "/webload/" + this.params.id : config.Url + "?p=pay&" + buildParamUrl(o),
            c = document.createElement("div");
        c.id = this.params.id + "_box", c.style.cssText = "position: fixed; display:none; z-index:999999; right: 0;  bottom: 0;left: 0; top: 0;-webkit-overflow-scrolling: touch;overflow-y: scroll;", this.iframe = document.createElement("iframe"), this.iframe.setAttribute("frameBorder", "0"), this.iframe.setAttribute("allowtransparency", "true"), this.iframe.style.cssText = "z-index: " + Math.max(10 * parseInt(t), 999999) + ";display: none;background: transparent;background: rgba(0,0,0,0.005);border: 0px none transparent;overflow-x: hidden;overflow-y: hidden;visibility: hidden;margin: 0;padding: 0;width: 100%;height: 100%;", this.iframe.id = this.iframe.name = this.params.id, this.iframe.src = s, this.iframe.onerror = function() {}, c.appendChild(this.iframe), document.body.appendChild(c), this.EventListen(), window.setTimeout(function() {
            if (!config.loadComplete) {
                var e = document.getElementById("wp-fading-circle");
                void 0 == e || (e.innerHTML = ""), config.timeout = 1
            }
        }, 6e4)
    }, _payrequest.prototype.EventListen = function() {
        var e = this;
        eventer(messageEvent, function(t) {
            var r = t.data || t.message;
            if (r && ("string" == typeof r || r instanceof String)) {
                var r = JSON.parse(r);
                if (void 0 == r.id || r.id != e.params.id) return !1;
                if ("loaded" == r.action && !config.timeout) {
                    var i = document.getElementById("wp-fading-circle");
                    void 0 == i || (i.innerHTML = "");
                    var n = document.getElementById(e.params.id);
                    n.style.display = "block", n.style.visibility = "visible", document.body.style.overflow = "hidden", document.getElementById(e.params.id + "_box").style.display = "block", e.iframeOpen = 1, config.loadComplete = 1
                }
                "closed" == r.action && (e.closeSignal(), e.closeIframe()), "success" == r.action && e.params.success && e.params.success.call(this, r.reference), "failed" == r.action && e.params.failed && e.params.failed.call(this, r.reference)
            }
        }, 0)
    }, _payrequest.prototype.closeSignal = function() {
        var e = this;
        e.params.closed && e.params.closed.call()
    }, _payrequest.prototype.closeIframe = function() {
        if (this.iframeOpen) {
            var e = document.getElementById(this.params.id);
            e.style.display = "none", e.style.visibility = "hidden", this.iframeOpen = 0, document.body.style.overflow = "", document.getElementById(this.params.id + "_box").style.display = "none"
        }
    };
    var payWindow = function() {
        return {
            init: function(e) {
                void 0 !== e.form && (e = parseForm(e.form));
                var t = generateId(),
                    r = {
                        id: t,
                        webload: t,
                        loadText: e.loadText || "Loading payment interface ... Please Wait ...",
                        v_merchant_id: e.v_merchant_id || "",
                        phone: e.phone || "",
                        email: e.email || "",
                        total: e.total || 0,
                        notify_url: e.notify_url || "",
                        cur: e.cur || "",
                        merchant_ref: e.merchant_ref || "",
                        memo: e.memo || "",
                        recurrent: e.recurrent || null,
                        frequency: e.frequency || 0,
                        developer_code: e.developer_code || "",
                        store_id: e.store_id || "",
                        items: e.items || {},
                        customer: e.customer || {},
                        success: e.success || null,
                        failed: e.failed || null,
                        closed: e.closed || null
                    };
                if (void 0 !== e.form && (r.form = e.form), void 0 === e.items || isArray(r.items) || (config.error = !0, alert("Items should be an array")), void 0 === e.customer || isObject(r.customer) || (config.error = !0, alert("Customer information should be an object array")), void 0 == e.success || isFunction(r.success) || (config.error = !0, alert("Success function is invalid")), void 0 == e.failed || isFunction(r.failed) || (config.error = !0, alert("Failed function is invalid")), void 0 == e.closed || isFunction(r.closed) || (config.error = !0, alert("Closed function is invalid")), r.items && !config.error) {
                    try {
                        count = 0, r.items.forEach(function(e) {
                            count++, r["item_" + count] = e.name, r["description_" + count] = e.description, r["price_" + count] = e.price
                        })
                    } catch (e) {}
                    delete r.items
                }
                if (r.customer && !config.error) {
                    try {
                        for (var i in r.customer) {
                            var n = r.customer[i];
                            r[i] = n
                        }
                    } catch (e) {}
                    delete r.customer
                }
                return r.form && !config.error && Object.keys(e).forEach(function(t) {
                    (t.match(/^item.*$/) || t.match(/^description.*$/) || t.match(/^price.*$/)) && (r[t] = e[t])
                }), "onload" in document.createElement("iframe") || (config.error = !0, alert("Iframe is not supported in this browser")), new _payrequest(r)
            },
            link: function(e) {
                return e.id = generateId(), e.webload = e.id, void 0 == e.success || isFunction(e.success) || (config.error = !0, alert("Success function is invalid")), void 0 == e.failed || isFunction(e.failed) || (config.error = !0, alert("Failed function is invalid")), void 0 == e.closed || isFunction(e.closed) || (config.error = !0, alert("Closed function is invalid")), void 0 == e.loadText && (e.loadText = "Loading payment interface ... Please Wait ..."), "onload" in document.createElement("iframe") || (config.error = !0, alert("Iframe is not supported in this browser")), new _payrequest(e)
            }
        }
    }();
    window.Wyrepay = payWindow;
    var source = document.currentScript || function() {
        var e = document.getElementsByTagName("script");
        return e[e.length - 1]
    }();
    hasData(source) && payWindow.init({
        v_merchant_id: source.getAttribute("data-v_merchant_id"),
        phone: source.getAttribute("data-phone"),
        email: source.getAttribute("data-email"),
        loadText: source.getAttribute("data-loadText"),
        total: source.getAttribute("data-total"),
        notify_url: source.getAttribute("data-notify_url"),
        cur: source.getAttribute("data-cur"),
        merchant_ref: source.getAttribute("data-merchant_ref"),
        memo: source.getAttribute("data-memo"),
        recurrent: parseBoolean(source.getAttribute("data-recurrent")),
        frequency: source.getAttribute("data-frequency"),
        developer_code: source.getAttribute("data-developer_code"),
        store_id: source.getAttribute("data-store_id"),
        items: parseObject(source.getAttribute("data-items")),
        customer: parseObject(source.getAttribute("data-customer")),
        closed: parseFunction(source.getAttribute("data-closed")),
        success: parseFunction(source.getAttribute("data-success")),
        failed: parseFunction(source.getAttribute("data-failed"))
    })
}();