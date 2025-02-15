/*------------------------------------*\
       Plugins - Table of contents
\*------------------------------------*/
/*
 - Jarallax
 - AOS
 - GlightBox
*/

/*!
 * Jarallax v2.0.2 (https://github.com/nk-o/jarallax)
 * Copyright 2022 nK <https://nkdev.info>
 * Licensed under MIT (https://github.com/nk-o/jarallax/blob/master/LICENSE)
 */
!(function (e, t) {
  "object" == typeof exports && "undefined" != typeof module
    ? (module.exports = t())
    : "function" == typeof define && define.amd
    ? define(t)
    : ((e =
        "undefined" != typeof globalThis ? globalThis : e || self).jarallax =
        t());
})(this, function () {
  "use strict";
  function e(e) {
    "complete" === document.readyState || "interactive" === document.readyState
      ? e()
      : document.addEventListener("DOMContentLoaded", e, {
          capture: !0,
          once: !0,
          passive: !0,
        });
  }
  let t;
  t =
    "undefined" != typeof window
      ? window
      : "undefined" != typeof global
      ? global
      : "undefined" != typeof self
      ? self
      : {};
  var i = t;
  const { navigator: o } = i,
    n = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
      o.userAgent
    );
  let a, s;
  function l() {
    n
      ? (!a &&
          document.body &&
          ((a = document.createElement("div")),
          (a.style.cssText =
            "position: fixed; top: -9999px; left: 0; height: 100vh; width: 0;"),
          document.body.appendChild(a)),
        (s =
          (a ? a.clientHeight : 0) ||
          i.innerHeight ||
          document.documentElement.clientHeight))
      : (s = i.innerHeight || document.documentElement.clientHeight);
  }
  l(),
    i.addEventListener("resize", l),
    i.addEventListener("orientationchange", l),
    i.addEventListener("load", l),
    e(() => {
      l();
    });
  const r = [];
  function m() {
    r.length &&
      (r.forEach((e, t) => {
        const { instance: o, oldData: n } = e,
          a = o.$item.getBoundingClientRect(),
          l = {
            width: a.width,
            height: a.height,
            top: a.top,
            bottom: a.bottom,
            wndW: i.innerWidth,
            wndH: s,
          },
          m =
            !n ||
            n.wndW !== l.wndW ||
            n.wndH !== l.wndH ||
            n.width !== l.width ||
            n.height !== l.height,
          c = m || !n || n.top !== l.top || n.bottom !== l.bottom;
        (r[t].oldData = l), m && o.onResize(), c && o.onScroll();
      }),
      i.requestAnimationFrame(m));
  }
  let c = 0;
  class p {
    constructor(e, t) {
      const i = this;
      (i.instanceID = c),
        (c += 1),
        (i.$item = e),
        (i.defaults = {
          type: "scroll",
          speed: 0.5,
          imgSrc: null,
          imgElement: ".jarallax-img",
          imgSize: "cover",
          imgPosition: "50% 50%",
          imgRepeat: "no-repeat",
          keepImg: !1,
          elementInViewport: null,
          zIndex: -100,
          disableParallax: !1,
          disableVideo: !1,
          videoSrc: null,
          videoStartTime: 0,
          videoEndTime: 0,
          videoVolume: 0,
          videoLoop: !0,
          videoPlayOnlyVisible: !0,
          videoLazyLoading: !0,
          onScroll: null,
          onInit: null,
          onDestroy: null,
          onCoverImage: null,
        });
      const n = i.$item.dataset || {},
        a = {};
      if (
        (Object.keys(n).forEach((e) => {
          const t = e.substr(0, 1).toLowerCase() + e.substr(1);
          t && void 0 !== i.defaults[t] && (a[t] = n[e]);
        }),
        (i.options = i.extend({}, i.defaults, a, t)),
        (i.pureOptions = i.extend({}, i.options)),
        Object.keys(i.options).forEach((e) => {
          "true" === i.options[e]
            ? (i.options[e] = !0)
            : "false" === i.options[e] && (i.options[e] = !1);
        }),
        (i.options.speed = Math.min(
          2,
          Math.max(-1, parseFloat(i.options.speed))
        )),
        "string" == typeof i.options.disableParallax &&
          (i.options.disableParallax = new RegExp(i.options.disableParallax)),
        i.options.disableParallax instanceof RegExp)
      ) {
        const e = i.options.disableParallax;
        i.options.disableParallax = () => e.test(o.userAgent);
      }
      if (
        ("function" != typeof i.options.disableParallax &&
          (i.options.disableParallax = () => !1),
        "string" == typeof i.options.disableVideo &&
          (i.options.disableVideo = new RegExp(i.options.disableVideo)),
        i.options.disableVideo instanceof RegExp)
      ) {
        const e = i.options.disableVideo;
        i.options.disableVideo = () => e.test(o.userAgent);
      }
      "function" != typeof i.options.disableVideo &&
        (i.options.disableVideo = () => !1);
      let s = i.options.elementInViewport;
      s && "object" == typeof s && void 0 !== s.length && ([s] = s),
        s instanceof Element || (s = null),
        (i.options.elementInViewport = s),
        (i.image = {
          src: i.options.imgSrc || null,
          $container: null,
          useImgTag: !1,
          position: "fixed",
        }),
        i.initImg() && i.canInitParallax() && i.init();
    }
    css(e, t) {
      return "string" == typeof t
        ? i.getComputedStyle(e).getPropertyValue(t)
        : (Object.keys(t).forEach((i) => {
            e.style[i] = t[i];
          }),
          e);
    }
    extend(e, ...t) {
      return (
        (e = e || {}),
        Object.keys(t).forEach((i) => {
          t[i] &&
            Object.keys(t[i]).forEach((o) => {
              e[o] = t[i][o];
            });
        }),
        e
      );
    }
    getWindowData() {
      return {
        width: i.innerWidth || document.documentElement.clientWidth,
        height: s,
        y: document.documentElement.scrollTop,
      };
    }
    initImg() {
      const e = this;
      let t = e.options.imgElement;
      return (
        t && "string" == typeof t && (t = e.$item.querySelector(t)),
        t instanceof Element ||
          (e.options.imgSrc
            ? ((t = new Image()), (t.src = e.options.imgSrc))
            : (t = null)),
        t &&
          (e.options.keepImg
            ? (e.image.$item = t.cloneNode(!0))
            : ((e.image.$item = t), (e.image.$itemParent = t.parentNode)),
          (e.image.useImgTag = !0)),
        !!e.image.$item ||
          (null === e.image.src &&
            ((e.image.src =
              "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"),
            (e.image.bgImage = e.css(e.$item, "background-image"))),
          !(!e.image.bgImage || "none" === e.image.bgImage))
      );
    }
    canInitParallax() {
      return !this.options.disableParallax();
    }
    init() {
      const e = this,
        t = {
          position: "absolute",
          top: 0,
          left: 0,
          width: "100%",
          height: "100%",
          overflow: "hidden",
        };
      let o = {
        pointerEvents: "none",
        transformStyle: "preserve-3d",
        backfaceVisibility: "hidden",
        willChange: "transform,opacity",
      };
      if (!e.options.keepImg) {
        const t = e.$item.getAttribute("style");
        if (
          (t && e.$item.setAttribute("data-jarallax-original-styles", t),
          e.image.useImgTag)
        ) {
          const t = e.image.$item.getAttribute("style");
          t && e.image.$item.setAttribute("data-jarallax-original-styles", t);
        }
      }
      if (
        ("static" === e.css(e.$item, "position") &&
          e.css(e.$item, { position: "relative" }),
        "auto" === e.css(e.$item, "z-index") && e.css(e.$item, { zIndex: 0 }),
        (e.image.$container = document.createElement("div")),
        e.css(e.image.$container, t),
        e.css(e.image.$container, { "z-index": e.options.zIndex }),
        "fixed" === this.image.position &&
          e.css(e.image.$container, {
            "-webkit-clip-path": "polygon(0 0, 100% 0, 100% 100%, 0 100%)",
            "clip-path": "polygon(0 0, 100% 0, 100% 100%, 0 100%)",
          }),
        e.image.$container.setAttribute(
          "id",
          `jarallax-container-${e.instanceID}`
        ),
        e.$item.appendChild(e.image.$container),
        e.image.useImgTag
          ? (o = e.extend(
              {
                "object-fit": e.options.imgSize,
                "object-position": e.options.imgPosition,
                "max-width": "none",
              },
              t,
              o
            ))
          : ((e.image.$item = document.createElement("div")),
            e.image.src &&
              (o = e.extend(
                {
                  "background-position": e.options.imgPosition,
                  "background-size": e.options.imgSize,
                  "background-repeat": e.options.imgRepeat,
                  "background-image":
                    e.image.bgImage || `url("${e.image.src}")`,
                },
                t,
                o
              ))),
        ("opacity" !== e.options.type &&
          "scale" !== e.options.type &&
          "scale-opacity" !== e.options.type &&
          1 !== e.options.speed) ||
          (e.image.position = "absolute"),
        "fixed" === e.image.position)
      ) {
        const t = (function (e) {
          const t = [];
          for (; null !== e.parentElement; )
            1 === (e = e.parentElement).nodeType && t.push(e);
          return t;
        })(e.$item).filter((e) => {
          const t = i.getComputedStyle(e),
            o = t["-webkit-transform"] || t["-moz-transform"] || t.transform;
          return (
            (o && "none" !== o) ||
            /(auto|scroll)/.test(t.overflow + t["overflow-y"] + t["overflow-x"])
          );
        });
        e.image.position = t.length ? "absolute" : "fixed";
      }
      (o.position = e.image.position),
        e.css(e.image.$item, o),
        e.image.$container.appendChild(e.image.$item),
        e.onResize(),
        e.onScroll(!0),
        e.options.onInit && e.options.onInit.call(e),
        "none" !== e.css(e.$item, "background-image") &&
          e.css(e.$item, { "background-image": "none" }),
        e.addToParallaxList();
    }
    addToParallaxList() {
      r.push({ instance: this }), 1 === r.length && i.requestAnimationFrame(m);
    }
    removeFromParallaxList() {
      const e = this;
      r.forEach((t, i) => {
        t.instance.instanceID === e.instanceID && r.splice(i, 1);
      });
    }
    destroy() {
      const e = this;
      e.removeFromParallaxList();
      const t = e.$item.getAttribute("data-jarallax-original-styles");
      if (
        (e.$item.removeAttribute("data-jarallax-original-styles"),
        t ? e.$item.setAttribute("style", t) : e.$item.removeAttribute("style"),
        e.image.useImgTag)
      ) {
        const i = e.image.$item.getAttribute("data-jarallax-original-styles");
        e.image.$item.removeAttribute("data-jarallax-original-styles"),
          i
            ? e.image.$item.setAttribute("style", t)
            : e.image.$item.removeAttribute("style"),
          e.image.$itemParent && e.image.$itemParent.appendChild(e.image.$item);
      }
      e.image.$container &&
        e.image.$container.parentNode.removeChild(e.image.$container),
        e.options.onDestroy && e.options.onDestroy.call(e),
        delete e.$item.jarallax;
    }
    clipContainer() {}
    coverImage() {
      const e = this,
        t = e.image.$container.getBoundingClientRect(),
        i = t.height,
        { speed: o } = e.options,
        n = "scroll" === e.options.type || "scroll-opacity" === e.options.type;
      let a = 0,
        l = i,
        r = 0;
      return (
        n &&
          (0 > o
            ? ((a = o * Math.max(i, s)), s < i && (a -= o * (i - s)))
            : (a = o * (i + s)),
          1 < o
            ? (l = Math.abs(a - s))
            : 0 > o
            ? (l = a / o + Math.abs(a))
            : (l += (s - i) * (1 - o)),
          (a /= 2)),
        (e.parallaxScrollDistance = a),
        (r = n ? (s - l) / 2 : (i - l) / 2),
        e.css(e.image.$item, {
          height: `${l}px`,
          marginTop: `${r}px`,
          left: "fixed" === e.image.position ? `${t.left}px` : "0",
          width: `${t.width}px`,
        }),
        e.options.onCoverImage && e.options.onCoverImage.call(e),
        { image: { height: l, marginTop: r }, container: t }
      );
    }
    isVisible() {
      return this.isElementInViewport || !1;
    }
    onScroll(e) {
      const t = this,
        o = t.$item.getBoundingClientRect(),
        n = o.top,
        a = o.height,
        l = {};
      let r = o;
      if (
        (t.options.elementInViewport &&
          (r = t.options.elementInViewport.getBoundingClientRect()),
        (t.isElementInViewport =
          0 <= r.bottom &&
          0 <= r.right &&
          r.top <= s &&
          r.left <= i.innerWidth),
        !e && !t.isElementInViewport)
      )
        return;
      const m = Math.max(0, n),
        c = Math.max(0, a + n),
        p = Math.max(0, -n),
        d = Math.max(0, n + a - s),
        g = Math.max(0, a - (n + a - s)),
        u = Math.max(0, -n + s - a),
        f = 1 - ((s - n) / (s + a)) * 2;
      let h = 1;
      if (
        (a < s
          ? (h = 1 - (p || d) / a)
          : c <= s
          ? (h = c / s)
          : g <= s && (h = g / s),
        ("opacity" !== t.options.type &&
          "scale-opacity" !== t.options.type &&
          "scroll-opacity" !== t.options.type) ||
          ((l.transform = "translate3d(0,0,0)"), (l.opacity = h)),
        "scale" === t.options.type || "scale-opacity" === t.options.type)
      ) {
        let e = 1;
        0 > t.options.speed
          ? (e -= t.options.speed * h)
          : (e += t.options.speed * (1 - h)),
          (l.transform = `scale(${e}) translate3d(0,0,0)`);
      }
      if ("scroll" === t.options.type || "scroll-opacity" === t.options.type) {
        let e = t.parallaxScrollDistance * f;
        "absolute" === t.image.position && (e -= n),
          (l.transform = `translate3d(0,${e}px,0)`);
      }
      t.css(t.image.$item, l),
        t.options.onScroll &&
          t.options.onScroll.call(t, {
            section: o,
            beforeTop: m,
            beforeTopEnd: c,
            afterTop: p,
            beforeBottom: d,
            beforeBottomEnd: g,
            afterBottom: u,
            visiblePercent: h,
            fromViewportCenter: f,
          });
    }
    onResize() {
      this.coverImage();
    }
  }
  const d = function (e, t, ...i) {
    ("object" == typeof HTMLElement
      ? e instanceof HTMLElement
      : e &&
        "object" == typeof e &&
        null !== e &&
        1 === e.nodeType &&
        "string" == typeof e.nodeName) && (e = [e]);
    const o = e.length;
    let n,
      a = 0;
    for (; a < o; a += 1)
      if (
        ("object" == typeof t || void 0 === t
          ? e[a].jarallax || (e[a].jarallax = new p(e[a], t))
          : e[a].jarallax && (n = e[a].jarallax[t].apply(e[a].jarallax, i)),
        void 0 !== n)
      )
        return n;
    return e;
  };
  d.constructor = p;
  const g = i.jQuery;
  if (void 0 !== g) {
    const e = function (...e) {
      Array.prototype.unshift.call(e, this);
      const t = d.apply(i, e);
      return "object" != typeof t ? t : this;
    };
    e.constructor = d.constructor;
    const t = g.fn.jarallax;
    (g.fn.jarallax = e),
      (g.fn.jarallax.noConflict = function () {
        return (g.fn.jarallax = t), this;
      });
  }
  return (
    e(() => {
      d(document.querySelectorAll("[data-jarallax]"));
    }),
    d
  );
});
//# sourceMappingURL=jarallax.min.js.map

// AOS

!(function (e, t) {
  "object" == typeof exports && "object" == typeof module
    ? (module.exports = t())
    : "function" == typeof define && define.amd
    ? define([], t)
    : "object" == typeof exports
    ? (exports.AOS = t())
    : (e.AOS = t());
})(this, function () {
  return (function (e) {
    function t(o) {
      if (n[o]) return n[o].exports;
      var i = (n[o] = { exports: {}, id: o, loaded: !1 });
      return e[o].call(i.exports, i, i.exports, t), (i.loaded = !0), i.exports;
    }
    var n = {};
    return (t.m = e), (t.c = n), (t.p = "dist/"), t(0);
  })([
    function (e, t, n) {
      "use strict";
      function o(e) {
        return e && e.__esModule ? e : { default: e };
      }
      var i =
          Object.assign ||
          function (e) {
            for (var t = 1; t < arguments.length; t++) {
              var n = arguments[t];
              for (var o in n)
                Object.prototype.hasOwnProperty.call(n, o) && (e[o] = n[o]);
            }
            return e;
          },
        r = n(1),
        a = (o(r), n(6)),
        u = o(a),
        c = n(7),
        f = o(c),
        s = n(8),
        d = o(s),
        l = n(9),
        p = o(l),
        m = n(10),
        b = o(m),
        v = n(11),
        y = o(v),
        g = n(14),
        h = o(g),
        w = [],
        k = !1,
        x = {
          offset: 120,
          delay: 0,
          easing: "ease",
          duration: 400,
          disable: !1,
          once: !1,
          startEvent: "DOMContentLoaded",
          throttleDelay: 99,
          debounceDelay: 50,
          disableMutationObserver: !1,
        },
        j = function () {
          var e =
            arguments.length > 0 && void 0 !== arguments[0] && arguments[0];
          if ((e && (k = !0), k))
            return (w = (0, y.default)(w, x)), (0, b.default)(w, x.once), w;
        },
        O = function () {
          (w = (0, h.default)()), j();
        },
        _ = function () {
          w.forEach(function (e, t) {
            e.node.removeAttribute("data-aos"),
              e.node.removeAttribute("data-aos-easing"),
              e.node.removeAttribute("data-aos-duration"),
              e.node.removeAttribute("data-aos-delay");
          });
        },
        S = function (e) {
          return (
            e === !0 ||
            ("mobile" === e && p.default.mobile()) ||
            ("phone" === e && p.default.phone()) ||
            ("tablet" === e && p.default.tablet()) ||
            ("function" == typeof e && e() === !0)
          );
        },
        z = function (e) {
          (x = i(x, e)), (w = (0, h.default)());
          var t = document.all && !window.atob;
          return S(x.disable) || t
            ? _()
            : (document
                .querySelector("body")
                .setAttribute("data-aos-easing", x.easing),
              document
                .querySelector("body")
                .setAttribute("data-aos-duration", x.duration),
              document
                .querySelector("body")
                .setAttribute("data-aos-delay", x.delay),
              "DOMContentLoaded" === x.startEvent &&
              ["complete", "interactive"].indexOf(document.readyState) > -1
                ? j(!0)
                : "load" === x.startEvent
                ? window.addEventListener(x.startEvent, function () {
                    j(!0);
                  })
                : document.addEventListener(x.startEvent, function () {
                    j(!0);
                  }),
              window.addEventListener(
                "resize",
                (0, f.default)(j, x.debounceDelay, !0)
              ),
              window.addEventListener(
                "orientationchange",
                (0, f.default)(j, x.debounceDelay, !0)
              ),
              window.addEventListener(
                "scroll",
                (0, u.default)(function () {
                  (0, b.default)(w, x.once);
                }, x.throttleDelay)
              ),
              x.disableMutationObserver || (0, d.default)("[data-aos]", O),
              w);
        };
      e.exports = { init: z, refresh: j, refreshHard: O };
    },
    function (e, t) {},
    ,
    ,
    ,
    ,
    function (e, t) {
      (function (t) {
        "use strict";
        function n(e, t, n) {
          function o(t) {
            var n = b,
              o = v;
            return (b = v = void 0), (k = t), (g = e.apply(o, n));
          }
          function r(e) {
            return (k = e), (h = setTimeout(s, t)), _ ? o(e) : g;
          }
          function a(e) {
            var n = e - w,
              o = e - k,
              i = t - n;
            return S ? j(i, y - o) : i;
          }
          function c(e) {
            var n = e - w,
              o = e - k;
            return void 0 === w || n >= t || n < 0 || (S && o >= y);
          }
          function s() {
            var e = O();
            return c(e) ? d(e) : void (h = setTimeout(s, a(e)));
          }
          function d(e) {
            return (h = void 0), z && b ? o(e) : ((b = v = void 0), g);
          }
          function l() {
            void 0 !== h && clearTimeout(h), (k = 0), (b = w = v = h = void 0);
          }
          function p() {
            return void 0 === h ? g : d(O());
          }
          function m() {
            var e = O(),
              n = c(e);
            if (((b = arguments), (v = this), (w = e), n)) {
              if (void 0 === h) return r(w);
              if (S) return (h = setTimeout(s, t)), o(w);
            }
            return void 0 === h && (h = setTimeout(s, t)), g;
          }
          var b,
            v,
            y,
            g,
            h,
            w,
            k = 0,
            _ = !1,
            S = !1,
            z = !0;
          if ("function" != typeof e) throw new TypeError(f);
          return (
            (t = u(t) || 0),
            i(n) &&
              ((_ = !!n.leading),
              (S = "maxWait" in n),
              (y = S ? x(u(n.maxWait) || 0, t) : y),
              (z = "trailing" in n ? !!n.trailing : z)),
            (m.cancel = l),
            (m.flush = p),
            m
          );
        }
        function o(e, t, o) {
          var r = !0,
            a = !0;
          if ("function" != typeof e) throw new TypeError(f);
          return (
            i(o) &&
              ((r = "leading" in o ? !!o.leading : r),
              (a = "trailing" in o ? !!o.trailing : a)),
            n(e, t, { leading: r, maxWait: t, trailing: a })
          );
        }
        function i(e) {
          var t = "undefined" == typeof e ? "undefined" : c(e);
          return !!e && ("object" == t || "function" == t);
        }
        function r(e) {
          return (
            !!e && "object" == ("undefined" == typeof e ? "undefined" : c(e))
          );
        }
        function a(e) {
          return (
            "symbol" == ("undefined" == typeof e ? "undefined" : c(e)) ||
            (r(e) && k.call(e) == d)
          );
        }
        function u(e) {
          if ("number" == typeof e) return e;
          if (a(e)) return s;
          if (i(e)) {
            var t = "function" == typeof e.valueOf ? e.valueOf() : e;
            e = i(t) ? t + "" : t;
          }
          if ("string" != typeof e) return 0 === e ? e : +e;
          e = e.replace(l, "");
          var n = m.test(e);
          return n || b.test(e) ? v(e.slice(2), n ? 2 : 8) : p.test(e) ? s : +e;
        }
        var c =
            "function" == typeof Symbol && "symbol" == typeof Symbol.iterator
              ? function (e) {
                  return typeof e;
                }
              : function (e) {
                  return e &&
                    "function" == typeof Symbol &&
                    e.constructor === Symbol &&
                    e !== Symbol.prototype
                    ? "symbol"
                    : typeof e;
                },
          f = "Expected a function",
          s = NaN,
          d = "[object Symbol]",
          l = /^\s+|\s+$/g,
          p = /^[-+]0x[0-9a-f]+$/i,
          m = /^0b[01]+$/i,
          b = /^0o[0-7]+$/i,
          v = parseInt,
          y =
            "object" == ("undefined" == typeof t ? "undefined" : c(t)) &&
            t &&
            t.Object === Object &&
            t,
          g =
            "object" == ("undefined" == typeof self ? "undefined" : c(self)) &&
            self &&
            self.Object === Object &&
            self,
          h = y || g || Function("return this")(),
          w = Object.prototype,
          k = w.toString,
          x = Math.max,
          j = Math.min,
          O = function () {
            return h.Date.now();
          };
        e.exports = o;
      }).call(
        t,
        (function () {
          return this;
        })()
      );
    },
    function (e, t) {
      (function (t) {
        "use strict";
        function n(e, t, n) {
          function i(t) {
            var n = b,
              o = v;
            return (b = v = void 0), (O = t), (g = e.apply(o, n));
          }
          function r(e) {
            return (O = e), (h = setTimeout(s, t)), _ ? i(e) : g;
          }
          function u(e) {
            var n = e - w,
              o = e - O,
              i = t - n;
            return S ? x(i, y - o) : i;
          }
          function f(e) {
            var n = e - w,
              o = e - O;
            return void 0 === w || n >= t || n < 0 || (S && o >= y);
          }
          function s() {
            var e = j();
            return f(e) ? d(e) : void (h = setTimeout(s, u(e)));
          }
          function d(e) {
            return (h = void 0), z && b ? i(e) : ((b = v = void 0), g);
          }
          function l() {
            void 0 !== h && clearTimeout(h), (O = 0), (b = w = v = h = void 0);
          }
          function p() {
            return void 0 === h ? g : d(j());
          }
          function m() {
            var e = j(),
              n = f(e);
            if (((b = arguments), (v = this), (w = e), n)) {
              if (void 0 === h) return r(w);
              if (S) return (h = setTimeout(s, t)), i(w);
            }
            return void 0 === h && (h = setTimeout(s, t)), g;
          }
          var b,
            v,
            y,
            g,
            h,
            w,
            O = 0,
            _ = !1,
            S = !1,
            z = !0;
          if ("function" != typeof e) throw new TypeError(c);
          return (
            (t = a(t) || 0),
            o(n) &&
              ((_ = !!n.leading),
              (S = "maxWait" in n),
              (y = S ? k(a(n.maxWait) || 0, t) : y),
              (z = "trailing" in n ? !!n.trailing : z)),
            (m.cancel = l),
            (m.flush = p),
            m
          );
        }
        function o(e) {
          var t = "undefined" == typeof e ? "undefined" : u(e);
          return !!e && ("object" == t || "function" == t);
        }
        function i(e) {
          return (
            !!e && "object" == ("undefined" == typeof e ? "undefined" : u(e))
          );
        }
        function r(e) {
          return (
            "symbol" == ("undefined" == typeof e ? "undefined" : u(e)) ||
            (i(e) && w.call(e) == s)
          );
        }
        function a(e) {
          if ("number" == typeof e) return e;
          if (r(e)) return f;
          if (o(e)) {
            var t = "function" == typeof e.valueOf ? e.valueOf() : e;
            e = o(t) ? t + "" : t;
          }
          if ("string" != typeof e) return 0 === e ? e : +e;
          e = e.replace(d, "");
          var n = p.test(e);
          return n || m.test(e) ? b(e.slice(2), n ? 2 : 8) : l.test(e) ? f : +e;
        }
        var u =
            "function" == typeof Symbol && "symbol" == typeof Symbol.iterator
              ? function (e) {
                  return typeof e;
                }
              : function (e) {
                  return e &&
                    "function" == typeof Symbol &&
                    e.constructor === Symbol &&
                    e !== Symbol.prototype
                    ? "symbol"
                    : typeof e;
                },
          c = "Expected a function",
          f = NaN,
          s = "[object Symbol]",
          d = /^\s+|\s+$/g,
          l = /^[-+]0x[0-9a-f]+$/i,
          p = /^0b[01]+$/i,
          m = /^0o[0-7]+$/i,
          b = parseInt,
          v =
            "object" == ("undefined" == typeof t ? "undefined" : u(t)) &&
            t &&
            t.Object === Object &&
            t,
          y =
            "object" == ("undefined" == typeof self ? "undefined" : u(self)) &&
            self &&
            self.Object === Object &&
            self,
          g = v || y || Function("return this")(),
          h = Object.prototype,
          w = h.toString,
          k = Math.max,
          x = Math.min,
          j = function () {
            return g.Date.now();
          };
        e.exports = n;
      }).call(
        t,
        (function () {
          return this;
        })()
      );
    },
    function (e, t) {
      "use strict";
      function n(e, t) {
        var n = window.document,
          r =
            window.MutationObserver ||
            window.WebKitMutationObserver ||
            window.MozMutationObserver,
          a = new r(o);
        (i = t),
          a.observe(n.documentElement, {
            childList: !0,
            subtree: !0,
            removedNodes: !0,
          });
      }
      function o(e) {
        e &&
          e.forEach(function (e) {
            var t = Array.prototype.slice.call(e.addedNodes),
              n = Array.prototype.slice.call(e.removedNodes),
              o = t.concat(n).filter(function (e) {
                return e.hasAttribute && e.hasAttribute("data-aos");
              }).length;
            o && i();
          });
      }
      Object.defineProperty(t, "__esModule", { value: !0 });
      var i = function () {};
      t.default = n;
    },
    function (e, t) {
      "use strict";
      function n(e, t) {
        if (!(e instanceof t))
          throw new TypeError("Cannot call a class as a function");
      }
      function o() {
        return navigator.userAgent || navigator.vendor || window.opera || "";
      }
      Object.defineProperty(t, "__esModule", { value: !0 });
      var i = (function () {
          function e(e, t) {
            for (var n = 0; n < t.length; n++) {
              var o = t[n];
              (o.enumerable = o.enumerable || !1),
                (o.configurable = !0),
                "value" in o && (o.writable = !0),
                Object.defineProperty(e, o.key, o);
            }
          }
          return function (t, n, o) {
            return n && e(t.prototype, n), o && e(t, o), t;
          };
        })(),
        r =
          /(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i,
        a =
          /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i,
        u =
          /(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino|android|ipad|playbook|silk/i,
        c =
          /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i,
        f = (function () {
          function e() {
            n(this, e);
          }
          return (
            i(e, [
              {
                key: "phone",
                value: function () {
                  var e = o();
                  return !(!r.test(e) && !a.test(e.substr(0, 4)));
                },
              },
              {
                key: "mobile",
                value: function () {
                  var e = o();
                  return !(!u.test(e) && !c.test(e.substr(0, 4)));
                },
              },
              {
                key: "tablet",
                value: function () {
                  return this.mobile() && !this.phone();
                },
              },
            ]),
            e
          );
        })();
      t.default = new f();
    },
    function (e, t) {
      "use strict";
      Object.defineProperty(t, "__esModule", { value: !0 });
      var n = function (e, t, n) {
          var o = e.node.getAttribute("data-aos-once");
          t > e.position
            ? e.node.classList.add("aos-animate")
            : "undefined" != typeof o &&
              ("false" === o || (!n && "true" !== o)) &&
              e.node.classList.remove("aos-animate");
        },
        o = function (e, t) {
          var o = window.pageYOffset,
            i = window.innerHeight;
          e.forEach(function (e, r) {
            n(e, i + o, t);
          });
        };
      t.default = o;
    },
    function (e, t, n) {
      "use strict";
      function o(e) {
        return e && e.__esModule ? e : { default: e };
      }
      Object.defineProperty(t, "__esModule", { value: !0 });
      var i = n(12),
        r = o(i),
        a = function (e, t) {
          return (
            e.forEach(function (e, n) {
              e.node.classList.add("aos-init"),
                (e.position = (0, r.default)(e.node, t.offset));
            }),
            e
          );
        };
      t.default = a;
    },
    function (e, t, n) {
      "use strict";
      function o(e) {
        return e && e.__esModule ? e : { default: e };
      }
      Object.defineProperty(t, "__esModule", { value: !0 });
      var i = n(13),
        r = o(i),
        a = function (e, t) {
          var n = 0,
            o = 0,
            i = window.innerHeight,
            a = {
              offset: e.getAttribute("data-aos-offset"),
              anchor: e.getAttribute("data-aos-anchor"),
              anchorPlacement: e.getAttribute("data-aos-anchor-placement"),
            };
          switch (
            (a.offset && !isNaN(a.offset) && (o = parseInt(a.offset)),
            a.anchor &&
              document.querySelectorAll(a.anchor) &&
              (e = document.querySelectorAll(a.anchor)[0]),
            (n = (0, r.default)(e).top),
            a.anchorPlacement)
          ) {
            case "top-bottom":
              break;
            case "center-bottom":
              n += e.offsetHeight / 2;
              break;
            case "bottom-bottom":
              n += e.offsetHeight;
              break;
            case "top-center":
              n += i / 2;
              break;
            case "bottom-center":
              n += i / 2 + e.offsetHeight;
              break;
            case "center-center":
              n += i / 2 + e.offsetHeight / 2;
              break;
            case "top-top":
              n += i;
              break;
            case "bottom-top":
              n += e.offsetHeight + i;
              break;
            case "center-top":
              n += e.offsetHeight / 2 + i;
          }
          return a.anchorPlacement || a.offset || isNaN(t) || (o = t), n + o;
        };
      t.default = a;
    },
    function (e, t) {
      "use strict";
      Object.defineProperty(t, "__esModule", { value: !0 });
      var n = function (e) {
        for (
          var t = 0, n = 0;
          e && !isNaN(e.offsetLeft) && !isNaN(e.offsetTop);

        )
          (t += e.offsetLeft - ("BODY" != e.tagName ? e.scrollLeft : 0)),
            (n += e.offsetTop - ("BODY" != e.tagName ? e.scrollTop : 0)),
            (e = e.offsetParent);
        return { top: n, left: t };
      };
      t.default = n;
    },
    function (e, t) {
      "use strict";
      Object.defineProperty(t, "__esModule", { value: !0 });
      var n = function (e) {
        return (
          (e = e || document.querySelectorAll("[data-aos]")),
          Array.prototype.map.call(e, function (e) {
            return { node: e };
          })
        );
      };
      t.default = n;
    },
  ]);
});

// GlightBox
// https://biati-digital.github.io/glightbox/

!(function (e, t) {
  "object" == typeof exports && "undefined" != typeof module
    ? (module.exports = t())
    : "function" == typeof define && define.amd
    ? define(t)
    : ((e = e || self).GLightbox = t());
})(this, function () {
  "use strict";
  function e(t) {
    return (e =
      "function" == typeof Symbol && "symbol" == typeof Symbol.iterator
        ? function (e) {
            return typeof e;
          }
        : function (e) {
            return e &&
              "function" == typeof Symbol &&
              e.constructor === Symbol &&
              e !== Symbol.prototype
              ? "symbol"
              : typeof e;
          })(t);
  }
  function t(e, t) {
    if (!(e instanceof t))
      throw new TypeError("Cannot call a class as a function");
  }
  function i(e, t) {
    for (var i = 0; i < t.length; i++) {
      var n = t[i];
      (n.enumerable = n.enumerable || !1),
        (n.configurable = !0),
        "value" in n && (n.writable = !0),
        Object.defineProperty(e, n.key, n);
    }
  }
  function n(e, t, n) {
    return t && i(e.prototype, t), n && i(e, n), e;
  }
  var s = Date.now();
  function l() {
    var e = {},
      t = !0,
      i = 0,
      n = arguments.length;
    "[object Boolean]" === Object.prototype.toString.call(arguments[0]) &&
      ((t = arguments[0]), i++);
    for (
      var s = function (i) {
        for (var n in i)
          Object.prototype.hasOwnProperty.call(i, n) &&
            (t && "[object Object]" === Object.prototype.toString.call(i[n])
              ? (e[n] = l(!0, e[n], i[n]))
              : (e[n] = i[n]));
      };
      i < n;
      i++
    ) {
      var o = arguments[i];
      s(o);
    }
    return e;
  }
  function o(e, t) {
    if (
      ((k(e) || e === window || e === document) && (e = [e]),
      A(e) || L(e) || (e = [e]),
      0 != P(e))
    )
      if (A(e) && !L(e))
        for (
          var i = e.length, n = 0;
          n < i && !1 !== t.call(e[n], e[n], n, e);
          n++
        );
      else if (L(e))
        for (var s in e) if (O(e, s) && !1 === t.call(e[s], e[s], s, e)) break;
  }
  function r(e) {
    var t =
        arguments.length > 1 && void 0 !== arguments[1] ? arguments[1] : null,
      i = arguments.length > 2 && void 0 !== arguments[2] ? arguments[2] : null,
      n = (e[s] = e[s] || []),
      l = { all: n, evt: null, found: null };
    return (
      t &&
        i &&
        P(n) > 0 &&
        o(n, function (e, n) {
          if (e.eventName == t && e.fn.toString() == i.toString())
            return (l.found = !0), (l.evt = n), !1;
        }),
      l
    );
  }
  function a(e) {
    var t = arguments.length > 1 && void 0 !== arguments[1] ? arguments[1] : {},
      i = t.onElement,
      n = t.withCallback,
      s = t.avoidDuplicate,
      l = void 0 === s || s,
      a = t.once,
      h = void 0 !== a && a,
      d = t.useCapture,
      c = void 0 !== d && d,
      u = arguments.length > 2 ? arguments[2] : void 0,
      g = i || [];
    function v(e) {
      T(n) && n.call(u, e, this), h && v.destroy();
    }
    return (
      C(g) && (g = document.querySelectorAll(g)),
      (v.destroy = function () {
        o(g, function (t) {
          var i = r(t, e, v);
          i.found && i.all.splice(i.evt, 1),
            t.removeEventListener && t.removeEventListener(e, v, c);
        });
      }),
      o(g, function (t) {
        var i = r(t, e, v);
        ((t.addEventListener && l && !i.found) || !l) &&
          (t.addEventListener(e, v, c), i.all.push({ eventName: e, fn: v }));
      }),
      v
    );
  }
  function h(e, t) {
    o(t.split(" "), function (t) {
      return e.classList.add(t);
    });
  }
  function d(e, t) {
    o(t.split(" "), function (t) {
      return e.classList.remove(t);
    });
  }
  function c(e, t) {
    return e.classList.contains(t);
  }
  function u(e, t) {
    for (; e !== document.body; ) {
      if (!(e = e.parentElement)) return !1;
      if (
        "function" == typeof e.matches ? e.matches(t) : e.msMatchesSelector(t)
      )
        return e;
    }
  }
  function g(e) {
    var t = arguments.length > 1 && void 0 !== arguments[1] ? arguments[1] : "",
      i = arguments.length > 2 && void 0 !== arguments[2] && arguments[2];
    if (!e || "" === t) return !1;
    if ("none" === t) return T(i) && i(), !1;
    var n = x(),
      s = t.split(" ");
    o(s, function (t) {
      h(e, "g" + t);
    }),
      a(n, {
        onElement: e,
        avoidDuplicate: !1,
        once: !0,
        withCallback: function (e, t) {
          o(s, function (e) {
            d(t, "g" + e);
          }),
            T(i) && i();
        },
      });
  }
  function v(e) {
    var t = arguments.length > 1 && void 0 !== arguments[1] ? arguments[1] : "";
    if ("" === t)
      return (
        (e.style.webkitTransform = ""),
        (e.style.MozTransform = ""),
        (e.style.msTransform = ""),
        (e.style.OTransform = ""),
        (e.style.transform = ""),
        !1
      );
    (e.style.webkitTransform = t),
      (e.style.MozTransform = t),
      (e.style.msTransform = t),
      (e.style.OTransform = t),
      (e.style.transform = t);
  }
  function f(e) {
    e.style.display = "block";
  }
  function p(e) {
    e.style.display = "none";
  }
  function m(e) {
    var t = document.createDocumentFragment(),
      i = document.createElement("div");
    for (i.innerHTML = e; i.firstChild; ) t.appendChild(i.firstChild);
    return t;
  }
  function y() {
    return {
      width:
        window.innerWidth ||
        document.documentElement.clientWidth ||
        document.body.clientWidth,
      height:
        window.innerHeight ||
        document.documentElement.clientHeight ||
        document.body.clientHeight,
    };
  }
  function x() {
    var e,
      t = document.createElement("fakeelement"),
      i = {
        animation: "animationend",
        OAnimation: "oAnimationEnd",
        MozAnimation: "animationend",
        WebkitAnimation: "webkitAnimationEnd",
      };
    for (e in i) if (void 0 !== t.style[e]) return i[e];
  }
  function b(e, t, i, n) {
    if (e()) t();
    else {
      var s;
      i || (i = 100);
      var l = setInterval(function () {
        e() && (clearInterval(l), s && clearTimeout(s), t());
      }, i);
      n &&
        (s = setTimeout(function () {
          clearInterval(l);
        }, n));
    }
  }
  function S(e, t, i) {
    if (I(e)) console.error("Inject assets error");
    else if ((T(t) && ((i = t), (t = !1)), C(t) && t in window)) T(i) && i();
    else {
      var n;
      if (-1 !== e.indexOf(".css")) {
        if (
          (n = document.querySelectorAll('link[href="' + e + '"]')) &&
          n.length > 0
        )
          return void (T(i) && i());
        var s = document.getElementsByTagName("head")[0],
          l = s.querySelectorAll('link[rel="stylesheet"]'),
          o = document.createElement("link");
        return (
          (o.rel = "stylesheet"),
          (o.type = "text/css"),
          (o.href = e),
          (o.media = "all"),
          l ? s.insertBefore(o, l[0]) : s.appendChild(o),
          void (T(i) && i())
        );
      }
      if (
        (n = document.querySelectorAll('script[src="' + e + '"]')) &&
        n.length > 0
      ) {
        if (T(i)) {
          if (C(t))
            return (
              b(
                function () {
                  return void 0 !== window[t];
                },
                function () {
                  i();
                }
              ),
              !1
            );
          i();
        }
      } else {
        var r = document.createElement("script");
        (r.type = "text/javascript"),
          (r.src = e),
          (r.onload = function () {
            if (T(i)) {
              if (C(t))
                return (
                  b(
                    function () {
                      return void 0 !== window[t];
                    },
                    function () {
                      i();
                    }
                  ),
                  !1
                );
              i();
            }
          }),
          document.body.appendChild(r);
      }
    }
  }
  function w() {
    return (
      "navigator" in window &&
      window.navigator.userAgent.match(
        /(iPad)|(iPhone)|(iPod)|(Android)|(PlayBook)|(BB10)|(BlackBerry)|(Opera Mini)|(IEMobile)|(webOS)|(MeeGo)/i
      )
    );
  }
  function T(e) {
    return "function" == typeof e;
  }
  function C(e) {
    return "string" == typeof e;
  }
  function k(e) {
    return !(!e || !e.nodeType || 1 != e.nodeType);
  }
  function E(e) {
    return Array.isArray(e);
  }
  function A(e) {
    return e && e.length && isFinite(e.length);
  }
  function L(t) {
    return "object" === e(t) && null != t && !T(t) && !E(t);
  }
  function I(e) {
    return null == e;
  }
  function O(e, t) {
    return null !== e && hasOwnProperty.call(e, t);
  }
  function P(e) {
    if (L(e)) {
      if (e.keys) return e.keys().length;
      var t = 0;
      for (var i in e) O(e, i) && t++;
      return t;
    }
    return e.length;
  }
  function M(e) {
    return !isNaN(parseFloat(e)) && isFinite(e);
  }
  function z() {
    var e = arguments.length > 0 && void 0 !== arguments[0] ? arguments[0] : -1,
      t = document.querySelectorAll(".gbtn[data-taborder]:not(.disabled)");
    if (!t.length) return !1;
    if (1 == t.length) return t[0];
    "string" == typeof e && (e = parseInt(e));
    var i = [];
    o(t, function (e) {
      i.push(e.getAttribute("data-taborder"));
    });
    var n = Math.max.apply(
        Math,
        i.map(function (e) {
          return parseInt(e);
        })
      ),
      s = e < 0 ? 1 : e + 1;
    s > n && (s = "1");
    var l = i.filter(function (e) {
        return e >= parseInt(s);
      }),
      r = l.sort()[0];
    return document.querySelector('.gbtn[data-taborder="'.concat(r, '"]'));
  }
  function X(e) {
    if (e.events.hasOwnProperty("keyboard")) return !1;
    e.events.keyboard = a("keydown", {
      onElement: window,
      withCallback: function (t, i) {
        var n = (t = t || window.event).keyCode;
        if (9 == n) {
          var s = document.querySelector(".gbtn.focused");
          if (!s) {
            var l =
              !(!document.activeElement || !document.activeElement.nodeName) &&
              document.activeElement.nodeName.toLocaleLowerCase();
            if ("input" == l || "textarea" == l || "button" == l) return;
          }
          t.preventDefault();
          var o = document.querySelectorAll(".gbtn[data-taborder]");
          if (!o || o.length <= 0) return;
          if (!s) {
            var r = z();
            return void (r && (r.focus(), h(r, "focused")));
          }
          var a = z(s.getAttribute("data-taborder"));
          d(s, "focused"), a && (a.focus(), h(a, "focused"));
        }
        39 == n && e.nextSlide(),
          37 == n && e.prevSlide(),
          27 == n && e.close();
      },
    });
  }
  function Y(e) {
    return Math.sqrt(e.x * e.x + e.y * e.y);
  }
  function q(e, t) {
    var i = (function (e, t) {
      var i = Y(e) * Y(t);
      if (0 === i) return 0;
      var n =
        (function (e, t) {
          return e.x * t.x + e.y * t.y;
        })(e, t) / i;
      return n > 1 && (n = 1), Math.acos(n);
    })(e, t);
    return (
      (function (e, t) {
        return e.x * t.y - t.x * e.y;
      })(e, t) > 0 && (i *= -1),
      (180 * i) / Math.PI
    );
  }
  var N = (function () {
    function e(i) {
      t(this, e), (this.handlers = []), (this.el = i);
    }
    return (
      n(e, [
        {
          key: "add",
          value: function (e) {
            this.handlers.push(e);
          },
        },
        {
          key: "del",
          value: function (e) {
            e || (this.handlers = []);
            for (var t = this.handlers.length; t >= 0; t--)
              this.handlers[t] === e && this.handlers.splice(t, 1);
          },
        },
        {
          key: "dispatch",
          value: function () {
            for (var e = 0, t = this.handlers.length; e < t; e++) {
              var i = this.handlers[e];
              "function" == typeof i && i.apply(this.el, arguments);
            }
          },
        },
      ]),
      e
    );
  })();
  function D(e, t) {
    var i = new N(e);
    return i.add(t), i;
  }
  var _ = (function () {
    function e(i, n) {
      t(this, e),
        (this.element = "string" == typeof i ? document.querySelector(i) : i),
        (this.start = this.start.bind(this)),
        (this.move = this.move.bind(this)),
        (this.end = this.end.bind(this)),
        (this.cancel = this.cancel.bind(this)),
        this.element.addEventListener("touchstart", this.start, !1),
        this.element.addEventListener("touchmove", this.move, !1),
        this.element.addEventListener("touchend", this.end, !1),
        this.element.addEventListener("touchcancel", this.cancel, !1),
        (this.preV = { x: null, y: null }),
        (this.pinchStartLen = null),
        (this.zoom = 1),
        (this.isDoubleTap = !1);
      var s = function () {};
      (this.rotate = D(this.element, n.rotate || s)),
        (this.touchStart = D(this.element, n.touchStart || s)),
        (this.multipointStart = D(this.element, n.multipointStart || s)),
        (this.multipointEnd = D(this.element, n.multipointEnd || s)),
        (this.pinch = D(this.element, n.pinch || s)),
        (this.swipe = D(this.element, n.swipe || s)),
        (this.tap = D(this.element, n.tap || s)),
        (this.doubleTap = D(this.element, n.doubleTap || s)),
        (this.longTap = D(this.element, n.longTap || s)),
        (this.singleTap = D(this.element, n.singleTap || s)),
        (this.pressMove = D(this.element, n.pressMove || s)),
        (this.twoFingerPressMove = D(this.element, n.twoFingerPressMove || s)),
        (this.touchMove = D(this.element, n.touchMove || s)),
        (this.touchEnd = D(this.element, n.touchEnd || s)),
        (this.touchCancel = D(this.element, n.touchCancel || s)),
        (this.translateContainer = this.element),
        (this._cancelAllHandler = this.cancelAll.bind(this)),
        window.addEventListener("scroll", this._cancelAllHandler),
        (this.delta = null),
        (this.last = null),
        (this.now = null),
        (this.tapTimeout = null),
        (this.singleTapTimeout = null),
        (this.longTapTimeout = null),
        (this.swipeTimeout = null),
        (this.x1 = this.x2 = this.y1 = this.y2 = null),
        (this.preTapPosition = { x: null, y: null });
    }
    return (
      n(e, [
        {
          key: "start",
          value: function (e) {
            if (e.touches) {
              if (
                e.target &&
                e.target.nodeName &&
                ["a", "button", "input"].indexOf(
                  e.target.nodeName.toLowerCase()
                ) >= 0
              )
                console.log(
                  "ignore drag for this touched element",
                  e.target.nodeName.toLowerCase()
                );
              else {
                (this.now = Date.now()),
                  (this.x1 = e.touches[0].pageX),
                  (this.y1 = e.touches[0].pageY),
                  (this.delta = this.now - (this.last || this.now)),
                  this.touchStart.dispatch(e, this.element),
                  null !== this.preTapPosition.x &&
                    ((this.isDoubleTap =
                      this.delta > 0 &&
                      this.delta <= 250 &&
                      Math.abs(this.preTapPosition.x - this.x1) < 30 &&
                      Math.abs(this.preTapPosition.y - this.y1) < 30),
                    this.isDoubleTap && clearTimeout(this.singleTapTimeout)),
                  (this.preTapPosition.x = this.x1),
                  (this.preTapPosition.y = this.y1),
                  (this.last = this.now);
                var t = this.preV;
                if (e.touches.length > 1) {
                  this._cancelLongTap(), this._cancelSingleTap();
                  var i = {
                    x: e.touches[1].pageX - this.x1,
                    y: e.touches[1].pageY - this.y1,
                  };
                  (t.x = i.x),
                    (t.y = i.y),
                    (this.pinchStartLen = Y(t)),
                    this.multipointStart.dispatch(e, this.element);
                }
                (this._preventTap = !1),
                  (this.longTapTimeout = setTimeout(
                    function () {
                      this.longTap.dispatch(e, this.element),
                        (this._preventTap = !0);
                    }.bind(this),
                    750
                  ));
              }
            }
          },
        },
        {
          key: "move",
          value: function (e) {
            if (e.touches) {
              var t = this.preV,
                i = e.touches.length,
                n = e.touches[0].pageX,
                s = e.touches[0].pageY;
              if (((this.isDoubleTap = !1), i > 1)) {
                var l = e.touches[1].pageX,
                  o = e.touches[1].pageY,
                  r = { x: e.touches[1].pageX - n, y: e.touches[1].pageY - s };
                null !== t.x &&
                  (this.pinchStartLen > 0 &&
                    ((e.zoom = Y(r) / this.pinchStartLen),
                    this.pinch.dispatch(e, this.element)),
                  (e.angle = q(r, t)),
                  this.rotate.dispatch(e, this.element)),
                  (t.x = r.x),
                  (t.y = r.y),
                  null !== this.x2 && null !== this.sx2
                    ? ((e.deltaX = (n - this.x2 + l - this.sx2) / 2),
                      (e.deltaY = (s - this.y2 + o - this.sy2) / 2))
                    : ((e.deltaX = 0), (e.deltaY = 0)),
                  this.twoFingerPressMove.dispatch(e, this.element),
                  (this.sx2 = l),
                  (this.sy2 = o);
              } else {
                if (null !== this.x2) {
                  (e.deltaX = n - this.x2), (e.deltaY = s - this.y2);
                  var a = Math.abs(this.x1 - this.x2),
                    h = Math.abs(this.y1 - this.y2);
                  (a > 10 || h > 10) && (this._preventTap = !0);
                } else (e.deltaX = 0), (e.deltaY = 0);
                this.pressMove.dispatch(e, this.element);
              }
              this.touchMove.dispatch(e, this.element),
                this._cancelLongTap(),
                (this.x2 = n),
                (this.y2 = s),
                i > 1 && e.preventDefault();
            }
          },
        },
        {
          key: "end",
          value: function (e) {
            if (e.changedTouches) {
              this._cancelLongTap();
              var t = this;
              e.touches.length < 2 &&
                (this.multipointEnd.dispatch(e, this.element),
                (this.sx2 = this.sy2 = null)),
                (this.x2 && Math.abs(this.x1 - this.x2) > 30) ||
                (this.y2 && Math.abs(this.y1 - this.y2) > 30)
                  ? ((e.direction = this._swipeDirection(
                      this.x1,
                      this.x2,
                      this.y1,
                      this.y2
                    )),
                    (this.swipeTimeout = setTimeout(function () {
                      t.swipe.dispatch(e, t.element);
                    }, 0)))
                  : ((this.tapTimeout = setTimeout(function () {
                      t._preventTap || t.tap.dispatch(e, t.element),
                        t.isDoubleTap &&
                          (t.doubleTap.dispatch(e, t.element),
                          (t.isDoubleTap = !1));
                    }, 0)),
                    t.isDoubleTap ||
                      (t.singleTapTimeout = setTimeout(function () {
                        t.singleTap.dispatch(e, t.element);
                      }, 250))),
                this.touchEnd.dispatch(e, this.element),
                (this.preV.x = 0),
                (this.preV.y = 0),
                (this.zoom = 1),
                (this.pinchStartLen = null),
                (this.x1 = this.x2 = this.y1 = this.y2 = null);
            }
          },
        },
        {
          key: "cancelAll",
          value: function () {
            (this._preventTap = !0),
              clearTimeout(this.singleTapTimeout),
              clearTimeout(this.tapTimeout),
              clearTimeout(this.longTapTimeout),
              clearTimeout(this.swipeTimeout);
          },
        },
        {
          key: "cancel",
          value: function (e) {
            this.cancelAll(), this.touchCancel.dispatch(e, this.element);
          },
        },
        {
          key: "_cancelLongTap",
          value: function () {
            clearTimeout(this.longTapTimeout);
          },
        },
        {
          key: "_cancelSingleTap",
          value: function () {
            clearTimeout(this.singleTapTimeout);
          },
        },
        {
          key: "_swipeDirection",
          value: function (e, t, i, n) {
            return Math.abs(e - t) >= Math.abs(i - n)
              ? e - t > 0
                ? "Left"
                : "Right"
              : i - n > 0
              ? "Up"
              : "Down";
          },
        },
        {
          key: "on",
          value: function (e, t) {
            this[e] && this[e].add(t);
          },
        },
        {
          key: "off",
          value: function (e, t) {
            this[e] && this[e].del(t);
          },
        },
        {
          key: "destroy",
          value: function () {
            return (
              this.singleTapTimeout && clearTimeout(this.singleTapTimeout),
              this.tapTimeout && clearTimeout(this.tapTimeout),
              this.longTapTimeout && clearTimeout(this.longTapTimeout),
              this.swipeTimeout && clearTimeout(this.swipeTimeout),
              this.element.removeEventListener("touchstart", this.start),
              this.element.removeEventListener("touchmove", this.move),
              this.element.removeEventListener("touchend", this.end),
              this.element.removeEventListener("touchcancel", this.cancel),
              this.rotate.del(),
              this.touchStart.del(),
              this.multipointStart.del(),
              this.multipointEnd.del(),
              this.pinch.del(),
              this.swipe.del(),
              this.tap.del(),
              this.doubleTap.del(),
              this.longTap.del(),
              this.singleTap.del(),
              this.pressMove.del(),
              this.twoFingerPressMove.del(),
              this.touchMove.del(),
              this.touchEnd.del(),
              this.touchCancel.del(),
              (this.preV =
                this.pinchStartLen =
                this.zoom =
                this.isDoubleTap =
                this.delta =
                this.last =
                this.now =
                this.tapTimeout =
                this.singleTapTimeout =
                this.longTapTimeout =
                this.swipeTimeout =
                this.x1 =
                this.x2 =
                this.y1 =
                this.y2 =
                this.preTapPosition =
                this.rotate =
                this.touchStart =
                this.multipointStart =
                this.multipointEnd =
                this.pinch =
                this.swipe =
                this.tap =
                this.doubleTap =
                this.longTap =
                this.singleTap =
                this.pressMove =
                this.touchMove =
                this.touchEnd =
                this.touchCancel =
                this.twoFingerPressMove =
                  null),
              window.removeEventListener("scroll", this._cancelAllHandler),
              null
            );
          },
        },
      ]),
      e
    );
  })();
  function W(e) {
    var t = (function () {
        var e,
          t = document.createElement("fakeelement"),
          i = {
            transition: "transitionend",
            OTransition: "oTransitionEnd",
            MozTransition: "transitionend",
            WebkitTransition: "webkitTransitionEnd",
          };
        for (e in i) if (void 0 !== t.style[e]) return i[e];
      })(),
      i =
        window.innerWidth ||
        document.documentElement.clientWidth ||
        document.body.clientWidth,
      n = c(e, "gslide-media") ? e : e.querySelector(".gslide-media"),
      s = u(n, ".ginner-container"),
      l = e.querySelector(".gslide-description");
    i > 769 && (n = s),
      h(n, "greset"),
      v(n, "translate3d(0, 0, 0)"),
      a(t, {
        onElement: n,
        once: !0,
        withCallback: function (e, t) {
          d(n, "greset");
        },
      }),
      (n.style.opacity = ""),
      l && (l.style.opacity = "");
  }
  function B(e) {
    if (e.events.hasOwnProperty("touch")) return !1;
    var t,
      i,
      n,
      s = y(),
      l = s.width,
      o = s.height,
      r = !1,
      a = null,
      g = null,
      f = null,
      p = !1,
      m = 1,
      x = 1,
      b = !1,
      S = !1,
      w = null,
      T = null,
      C = null,
      k = null,
      E = 0,
      A = 0,
      L = !1,
      I = !1,
      O = {},
      P = {},
      M = 0,
      z = 0,
      X = document.getElementById("glightbox-slider"),
      Y = document.querySelector(".goverlay"),
      q = new _(X, {
        touchStart: function (t) {
          if (
            ((r = !0),
            (c(t.targetTouches[0].target, "ginner-container") ||
              u(t.targetTouches[0].target, ".gslide-desc") ||
              "a" == t.targetTouches[0].target.nodeName.toLowerCase()) &&
              (r = !1),
            u(t.targetTouches[0].target, ".gslide-inline") &&
              !c(t.targetTouches[0].target.parentNode, "gslide-inline") &&
              (r = !1),
            r)
          ) {
            if (
              ((P = t.targetTouches[0]),
              (O.pageX = t.targetTouches[0].pageX),
              (O.pageY = t.targetTouches[0].pageY),
              (M = t.targetTouches[0].clientX),
              (z = t.targetTouches[0].clientY),
              (a = e.activeSlide),
              (g = a.querySelector(".gslide-media")),
              (n = a.querySelector(".gslide-inline")),
              (f = null),
              c(g, "gslide-image") && (f = g.querySelector("img")),
              (window.innerWidth ||
                document.documentElement.clientWidth ||
                document.body.clientWidth) > 769 &&
                (g = a.querySelector(".ginner-container")),
              d(Y, "greset"),
              t.pageX > 20 && t.pageX < window.innerWidth - 20)
            )
              return;
            t.preventDefault();
          }
        },
        touchMove: function (s) {
          if (r && ((P = s.targetTouches[0]), !b && !S)) {
            if (n && n.offsetHeight > o) {
              var a = O.pageX - P.pageX;
              if (Math.abs(a) <= 13) return !1;
            }
            p = !0;
            var h,
              d = s.targetTouches[0].clientX,
              c = s.targetTouches[0].clientY,
              u = M - d,
              m = z - c;
            if (
              (Math.abs(u) > Math.abs(m)
                ? ((L = !1), (I = !0))
                : ((I = !1), (L = !0)),
              (t = P.pageX - O.pageX),
              (E = (100 * t) / l),
              (i = P.pageY - O.pageY),
              (A = (100 * i) / o),
              L &&
                f &&
                ((h = 1 - Math.abs(i) / o),
                (Y.style.opacity = h),
                e.settings.touchFollowAxis && (E = 0)),
              I &&
                ((h = 1 - Math.abs(t) / l),
                (g.style.opacity = h),
                e.settings.touchFollowAxis && (A = 0)),
              !f)
            )
              return v(g, "translate3d(".concat(E, "%, 0, 0)"));
            v(g, "translate3d(".concat(E, "%, ").concat(A, "%, 0)"));
          }
        },
        touchEnd: function () {
          if (r) {
            if (((p = !1), S || b)) return (C = w), void (k = T);
            var t = Math.abs(parseInt(A)),
              i = Math.abs(parseInt(E));
            if (!(t > 29 && f))
              return t < 29 && i < 25
                ? (h(Y, "greset"), (Y.style.opacity = 1), W(g))
                : void 0;
            e.close();
          }
        },
        multipointEnd: function () {
          setTimeout(function () {
            b = !1;
          }, 50);
        },
        multipointStart: function () {
          (b = !0), (m = x || 1);
        },
        pinch: function (e) {
          if (!f || p) return !1;
          (b = !0), (f.scaleX = f.scaleY = m * e.zoom);
          var t = m * e.zoom;
          if (((S = !0), t <= 1))
            return (
              (S = !1),
              (t = 1),
              (k = null),
              (C = null),
              (w = null),
              (T = null),
              void f.setAttribute("style", "")
            );
          t > 4.5 && (t = 4.5),
            (f.style.transform = "scale3d(".concat(t, ", ").concat(t, ", 1)")),
            (x = t);
        },
        pressMove: function (e) {
          if (S && !b) {
            var t = P.pageX - O.pageX,
              i = P.pageY - O.pageY;
            C && (t += C), k && (i += k), (w = t), (T = i);
            var n = "translate3d(".concat(t, "px, ").concat(i, "px, 0)");
            x && (n += " scale3d(".concat(x, ", ").concat(x, ", 1)")), v(f, n);
          }
        },
        swipe: function (t) {
          if (!S)
            if (b) b = !1;
            else {
              if ("Left" == t.direction) {
                if (e.index == e.elements.length - 1) return W(g);
                e.nextSlide();
              }
              if ("Right" == t.direction) {
                if (0 == e.index) return W(g);
                e.prevSlide();
              }
            }
        },
      });
    e.events.touch = q;
  }
  var H = (function () {
      function e(i, n) {
        var s = this,
          l =
            arguments.length > 2 && void 0 !== arguments[2]
              ? arguments[2]
              : null;
        if (
          (t(this, e),
          (this.img = i),
          (this.slide = n),
          (this.onclose = l),
          this.img.setZoomEvents)
        )
          return !1;
        (this.active = !1),
          (this.zoomedIn = !1),
          (this.dragging = !1),
          (this.currentX = null),
          (this.currentY = null),
          (this.initialX = null),
          (this.initialY = null),
          (this.xOffset = 0),
          (this.yOffset = 0),
          this.img.addEventListener(
            "mousedown",
            function (e) {
              return s.dragStart(e);
            },
            !1
          ),
          this.img.addEventListener(
            "mouseup",
            function (e) {
              return s.dragEnd(e);
            },
            !1
          ),
          this.img.addEventListener(
            "mousemove",
            function (e) {
              return s.drag(e);
            },
            !1
          ),
          this.img.addEventListener(
            "click",
            function (e) {
              return s.slide.classList.contains("dragging-nav")
                ? (s.zoomOut(), !1)
                : s.zoomedIn
                ? void (s.zoomedIn && !s.dragging && s.zoomOut())
                : s.zoomIn();
            },
            !1
          ),
          (this.img.setZoomEvents = !0);
      }
      return (
        n(e, [
          {
            key: "zoomIn",
            value: function () {
              var e = this.widowWidth();
              if (!(this.zoomedIn || e <= 768)) {
                var t = this.img;
                if (
                  (t.setAttribute("data-style", t.getAttribute("style")),
                  (t.style.maxWidth = t.naturalWidth + "px"),
                  (t.style.maxHeight = t.naturalHeight + "px"),
                  t.naturalWidth > e)
                ) {
                  var i = e / 2 - t.naturalWidth / 2;
                  this.setTranslate(this.img.parentNode, i, 0);
                }
                this.slide.classList.add("zoomed"), (this.zoomedIn = !0);
              }
            },
          },
          {
            key: "zoomOut",
            value: function () {
              this.img.parentNode.setAttribute("style", ""),
                this.img.setAttribute(
                  "style",
                  this.img.getAttribute("data-style")
                ),
                this.slide.classList.remove("zoomed"),
                (this.zoomedIn = !1),
                (this.currentX = null),
                (this.currentY = null),
                (this.initialX = null),
                (this.initialY = null),
                (this.xOffset = 0),
                (this.yOffset = 0),
                this.onclose &&
                  "function" == typeof this.onclose &&
                  this.onclose();
            },
          },
          {
            key: "dragStart",
            value: function (e) {
              e.preventDefault(),
                this.zoomedIn
                  ? ("touchstart" === e.type
                      ? ((this.initialX = e.touches[0].clientX - this.xOffset),
                        (this.initialY = e.touches[0].clientY - this.yOffset))
                      : ((this.initialX = e.clientX - this.xOffset),
                        (this.initialY = e.clientY - this.yOffset)),
                    e.target === this.img &&
                      ((this.active = !0), this.img.classList.add("dragging")))
                  : (this.active = !1);
            },
          },
          {
            key: "dragEnd",
            value: function (e) {
              var t = this;
              e.preventDefault(),
                (this.initialX = this.currentX),
                (this.initialY = this.currentY),
                (this.active = !1),
                setTimeout(function () {
                  (t.dragging = !1),
                    (t.img.isDragging = !1),
                    t.img.classList.remove("dragging");
                }, 100);
            },
          },
          {
            key: "drag",
            value: function (e) {
              this.active &&
                (e.preventDefault(),
                "touchmove" === e.type
                  ? ((this.currentX = e.touches[0].clientX - this.initialX),
                    (this.currentY = e.touches[0].clientY - this.initialY))
                  : ((this.currentX = e.clientX - this.initialX),
                    (this.currentY = e.clientY - this.initialY)),
                (this.xOffset = this.currentX),
                (this.yOffset = this.currentY),
                (this.img.isDragging = !0),
                (this.dragging = !0),
                this.setTranslate(this.img, this.currentX, this.currentY));
            },
          },
          {
            key: "onMove",
            value: function (e) {
              if (this.zoomedIn) {
                var t = e.clientX - this.img.naturalWidth / 2,
                  i = e.clientY - this.img.naturalHeight / 2;
                this.setTranslate(this.img, t, i);
              }
            },
          },
          {
            key: "setTranslate",
            value: function (e, t, i) {
              e.style.transform = "translate3d(" + t + "px, " + i + "px, 0)";
            },
          },
          {
            key: "widowWidth",
            value: function () {
              return (
                window.innerWidth ||
                document.documentElement.clientWidth ||
                document.body.clientWidth
              );
            },
          },
        ]),
        e
      );
    })(),
    V = (function () {
      function e() {
        var i = this,
          n =
            arguments.length > 0 && void 0 !== arguments[0] ? arguments[0] : {};
        t(this, e);
        var s = n.dragEl,
          l = n.toleranceX,
          o = void 0 === l ? 40 : l,
          r = n.toleranceY,
          a = void 0 === r ? 65 : r,
          h = n.slide,
          d = void 0 === h ? null : h,
          c = n.instance,
          u = void 0 === c ? null : c;
        (this.el = s),
          (this.active = !1),
          (this.dragging = !1),
          (this.currentX = null),
          (this.currentY = null),
          (this.initialX = null),
          (this.initialY = null),
          (this.xOffset = 0),
          (this.yOffset = 0),
          (this.direction = null),
          (this.lastDirection = null),
          (this.toleranceX = o),
          (this.toleranceY = a),
          (this.toleranceReached = !1),
          (this.dragContainer = this.el),
          (this.slide = d),
          (this.instance = u),
          this.el.addEventListener(
            "mousedown",
            function (e) {
              return i.dragStart(e);
            },
            !1
          ),
          this.el.addEventListener(
            "mouseup",
            function (e) {
              return i.dragEnd(e);
            },
            !1
          ),
          this.el.addEventListener(
            "mousemove",
            function (e) {
              return i.drag(e);
            },
            !1
          );
      }
      return (
        n(e, [
          {
            key: "dragStart",
            value: function (e) {
              if (this.slide.classList.contains("zoomed")) this.active = !1;
              else {
                "touchstart" === e.type
                  ? ((this.initialX = e.touches[0].clientX - this.xOffset),
                    (this.initialY = e.touches[0].clientY - this.yOffset))
                  : ((this.initialX = e.clientX - this.xOffset),
                    (this.initialY = e.clientY - this.yOffset));
                var t = e.target.nodeName.toLowerCase();
                e.target.classList.contains("nodrag") ||
                u(e.target, ".nodrag") ||
                -1 !== ["input", "select", "textarea", "button", "a"].indexOf(t)
                  ? (this.active = !1)
                  : (e.preventDefault(),
                    (e.target === this.el ||
                      ("img" !== t && u(e.target, ".gslide-inline"))) &&
                      ((this.active = !0),
                      this.el.classList.add("dragging"),
                      (this.dragContainer = u(e.target, ".ginner-container"))));
              }
            },
          },
          {
            key: "dragEnd",
            value: function (e) {
              var t = this;
              e && e.preventDefault(),
                (this.initialX = 0),
                (this.initialY = 0),
                (this.currentX = null),
                (this.currentY = null),
                (this.initialX = null),
                (this.initialY = null),
                (this.xOffset = 0),
                (this.yOffset = 0),
                (this.active = !1),
                this.doSlideChange &&
                  ((this.instance.preventOutsideClick = !0),
                  "right" == this.doSlideChange && this.instance.prevSlide(),
                  "left" == this.doSlideChange && this.instance.nextSlide()),
                this.doSlideClose && this.instance.close(),
                this.toleranceReached ||
                  this.setTranslate(this.dragContainer, 0, 0, !0),
                setTimeout(function () {
                  (t.instance.preventOutsideClick = !1),
                    (t.toleranceReached = !1),
                    (t.lastDirection = null),
                    (t.dragging = !1),
                    (t.el.isDragging = !1),
                    t.el.classList.remove("dragging"),
                    t.slide.classList.remove("dragging-nav"),
                    (t.dragContainer.style.transform = ""),
                    (t.dragContainer.style.transition = "");
                }, 100);
            },
          },
          {
            key: "drag",
            value: function (e) {
              if (this.active) {
                e.preventDefault(),
                  this.slide.classList.add("dragging-nav"),
                  "touchmove" === e.type
                    ? ((this.currentX = e.touches[0].clientX - this.initialX),
                      (this.currentY = e.touches[0].clientY - this.initialY))
                    : ((this.currentX = e.clientX - this.initialX),
                      (this.currentY = e.clientY - this.initialY)),
                  (this.xOffset = this.currentX),
                  (this.yOffset = this.currentY),
                  (this.el.isDragging = !0),
                  (this.dragging = !0),
                  (this.doSlideChange = !1),
                  (this.doSlideClose = !1);
                var t = Math.abs(this.currentX),
                  i = Math.abs(this.currentY);
                if (
                  t > 0 &&
                  t >= Math.abs(this.currentY) &&
                  (!this.lastDirection || "x" == this.lastDirection)
                ) {
                  (this.yOffset = 0),
                    (this.lastDirection = "x"),
                    this.setTranslate(this.dragContainer, this.currentX, 0);
                  var n = this.shouldChange();
                  if (
                    (!this.instance.settings.dragAutoSnap &&
                      n &&
                      (this.doSlideChange = n),
                    this.instance.settings.dragAutoSnap && n)
                  )
                    return (
                      (this.instance.preventOutsideClick = !0),
                      (this.toleranceReached = !0),
                      (this.active = !1),
                      (this.instance.preventOutsideClick = !0),
                      this.dragEnd(null),
                      "right" == n && this.instance.prevSlide(),
                      void ("left" == n && this.instance.nextSlide())
                    );
                }
                if (
                  this.toleranceY > 0 &&
                  i > 0 &&
                  i >= t &&
                  (!this.lastDirection || "y" == this.lastDirection)
                ) {
                  (this.xOffset = 0),
                    (this.lastDirection = "y"),
                    this.setTranslate(this.dragContainer, 0, this.currentY);
                  var s = this.shouldClose();
                  return (
                    !this.instance.settings.dragAutoSnap &&
                      s &&
                      (this.doSlideClose = !0),
                    void (
                      this.instance.settings.dragAutoSnap &&
                      s &&
                      this.instance.close()
                    )
                  );
                }
              }
            },
          },
          {
            key: "shouldChange",
            value: function () {
              var e = !1;
              if (Math.abs(this.currentX) >= this.toleranceX) {
                var t = this.currentX > 0 ? "right" : "left";
                (("left" == t &&
                  this.slide !== this.slide.parentNode.lastChild) ||
                  ("right" == t &&
                    this.slide !== this.slide.parentNode.firstChild)) &&
                  (e = t);
              }
              return e;
            },
          },
          {
            key: "shouldClose",
            value: function () {
              var e = !1;
              return Math.abs(this.currentY) >= this.toleranceY && (e = !0), e;
            },
          },
          {
            key: "setTranslate",
            value: function (e, t, i) {
              var n =
                arguments.length > 3 && void 0 !== arguments[3] && arguments[3];
              (e.style.transition = n ? "all .2s ease" : ""),
                (e.style.transform = "translate3d("
                  .concat(t, "px, ")
                  .concat(i, "px, 0)"));
            },
          },
        ]),
        e
      );
    })();
  function j(e, t, i, n) {
    var s = e.querySelector(".gslide-media"),
      l = new Image(),
      o = "gSlideTitle_" + i,
      r = "gSlideDesc_" + i;
    l.addEventListener(
      "load",
      function () {
        T(n) && n();
      },
      !1
    ),
      (l.src = t.href),
      "" != t.sizes &&
        "" != t.srcset &&
        ((l.sizes = t.sizes), (l.srcset = t.srcset)),
      (l.alt = ""),
      I(t.alt) || "" === t.alt || (l.alt = t.alt),
      "" !== t.title && l.setAttribute("aria-labelledby", o),
      "" !== t.description && l.setAttribute("aria-describedby", r),
      t.hasOwnProperty("_hasCustomWidth") &&
        t._hasCustomWidth &&
        (l.style.width = t.width),
      t.hasOwnProperty("_hasCustomHeight") &&
        t._hasCustomHeight &&
        (l.style.height = t.height),
      s.insertBefore(l, s.firstChild);
  }
  function F(e, t, i, n) {
    var s = this,
      l = e.querySelector(".ginner-container"),
      o = "gvideo" + i,
      r = e.querySelector(".gslide-media"),
      a = this.getAllPlayers();
    h(l, "gvideo-container"),
      r.insertBefore(m('<div class="gvideo-wrapper"></div>'), r.firstChild);
    var d = e.querySelector(".gvideo-wrapper");
    S(this.settings.plyr.css, "Plyr");
    var c = t.href,
      u = null == t ? void 0 : t.videoProvider,
      g = !1;
    (r.style.maxWidth = t.width),
      S(this.settings.plyr.js, "Plyr", function () {
        if (
          (!u && c.match(/vimeo\.com\/([0-9]*)/) && (u = "vimeo"),
          !u &&
            (c.match(
              /(youtube\.com|youtube-nocookie\.com)\/watch\?v=([a-zA-Z0-9\-_]+)/
            ) ||
              c.match(/youtu\.be\/([a-zA-Z0-9\-_]+)/) ||
              c.match(
                /(youtube\.com|youtube-nocookie\.com)\/embed\/([a-zA-Z0-9\-_]+)/
              )) &&
            (u = "youtube"),
          "local" === u || !u)
        ) {
          u = "local";
          var l = '<video id="' + o + '" ';
          (l += 'style="background:#000; max-width: '.concat(t.width, ';" ')),
            (l += 'preload="metadata" '),
            (l += 'x-webkit-airplay="allow" '),
            (l += "playsinline "),
            (l += "controls "),
            (l += 'class="gvideo-local">'),
            (l += '<source src="'.concat(c, '">')),
            (g = m((l += "</video>")));
        }
        var r =
          g ||
          m(
            '<div id="'
              .concat(o, '" data-plyr-provider="')
              .concat(u, '" data-plyr-embed-id="')
              .concat(c, '"></div>')
          );
        h(d, "".concat(u, "-video gvideo")),
          d.appendChild(r),
          d.setAttribute("data-id", o),
          d.setAttribute("data-index", i);
        var v = O(s.settings.plyr, "config") ? s.settings.plyr.config : {},
          f = new Plyr("#" + o, v);
        f.on("ready", function (e) {
          (a[o] = e.detail.plyr), T(n) && n();
        }),
          b(
            function () {
              return (
                e.querySelector("iframe") &&
                "true" == e.querySelector("iframe").dataset.ready
              );
            },
            function () {
              s.resize(e);
            }
          ),
          f.on("enterfullscreen", R),
          f.on("exitfullscreen", R);
      });
  }
  function R(e) {
    var t = u(e.target, ".gslide-media");
    "enterfullscreen" === e.type && h(t, "fullscreen"),
      "exitfullscreen" === e.type && d(t, "fullscreen");
  }
  function G(e, t, i, n) {
    var s,
      l = this,
      o = e.querySelector(".gslide-media"),
      r = !(!O(t, "href") || !t.href) && t.href.split("#").pop().trim(),
      d = !(!O(t, "content") || !t.content) && t.content;
    if (
      d &&
      (C(d) && (s = m('<div class="ginlined-content">'.concat(d, "</div>"))),
      k(d))
    ) {
      "none" == d.style.display && (d.style.display = "block");
      var c = document.createElement("div");
      (c.className = "ginlined-content"), c.appendChild(d), (s = c);
    }
    if (r) {
      var u = document.getElementById(r);
      if (!u) return !1;
      var g = u.cloneNode(!0);
      (g.style.height = t.height),
        (g.style.maxWidth = t.width),
        h(g, "ginlined-content"),
        (s = g);
    }
    if (!s)
      return console.error("Unable to append inline slide content", t), !1;
    (o.style.height = t.height),
      (o.style.width = t.width),
      o.appendChild(s),
      (this.events["inlineclose" + r] = a("click", {
        onElement: o.querySelectorAll(".gtrigger-close"),
        withCallback: function (e) {
          e.preventDefault(), l.close();
        },
      })),
      T(n) && n();
  }
  function Z(e, t, i, n) {
    var s = e.querySelector(".gslide-media"),
      l = (function (e) {
        var t = e.url,
          i = e.allow,
          n = e.callback,
          s = e.appendTo,
          l = document.createElement("iframe");
        return (
          (l.className = "vimeo-video gvideo"),
          (l.src = t),
          (l.style.width = "100%"),
          (l.style.height = "100%"),
          i && l.setAttribute("allow", i),
          (l.onload = function () {
            (l.onload = null), h(l, "node-ready"), T(n) && n();
          }),
          s && s.appendChild(l),
          l
        );
      })({ url: t.href, callback: n });
    (s.parentNode.style.maxWidth = t.width),
      (s.parentNode.style.height = t.height),
      s.appendChild(l);
  }
  var U = (function () {
      function e() {
        var i =
          arguments.length > 0 && void 0 !== arguments[0] ? arguments[0] : {};
        t(this, e),
          (this.defaults = {
            href: "",
            sizes: "",
            srcset: "",
            title: "",
            type: "",
            videoProvider: "",
            description: "",
            alt: "",
            descPosition: "bottom",
            effect: "",
            width: "",
            height: "",
            content: !1,
            zoomable: !0,
            draggable: !0,
          }),
          L(i) && (this.defaults = l(this.defaults, i));
      }
      return (
        n(e, [
          {
            key: "sourceType",
            value: function (e) {
              var t = e;
              if (
                null !==
                (e = e.toLowerCase()).match(
                  /\.(jpeg|jpg|jpe|gif|png|apn|webp|avif|svg)/
                )
              )
                return "image";
              if (
                e.match(
                  /(youtube\.com|youtube-nocookie\.com)\/watch\?v=([a-zA-Z0-9\-_]+)/
                ) ||
                e.match(/youtu\.be\/([a-zA-Z0-9\-_]+)/) ||
                e.match(
                  /(youtube\.com|youtube-nocookie\.com)\/embed\/([a-zA-Z0-9\-_]+)/
                )
              )
                return "video";
              if (e.match(/vimeo\.com\/([0-9]*)/)) return "video";
              if (null !== e.match(/\.(mp4|ogg|webm|mov)/)) return "video";
              if (null !== e.match(/\.(mp3|wav|wma|aac|ogg)/)) return "audio";
              if (e.indexOf("#") > -1 && "" !== t.split("#").pop().trim())
                return "inline";
              return e.indexOf("goajax=true") > -1 ? "ajax" : "external";
            },
          },
          {
            key: "parseConfig",
            value: function (e, t) {
              var i = this,
                n = l({ descPosition: t.descPosition }, this.defaults);
              if (L(e) && !k(e)) {
                O(e, "type") ||
                  (O(e, "content") && e.content
                    ? (e.type = "inline")
                    : O(e, "href") && (e.type = this.sourceType(e.href)));
                var s = l(n, e);
                return this.setSize(s, t), s;
              }
              var r = "",
                a = e.getAttribute("data-glightbox"),
                h = e.nodeName.toLowerCase();
              if (
                ("a" === h && (r = e.href),
                "img" === h && ((r = e.src), (n.alt = e.alt)),
                (n.href = r),
                o(n, function (s, l) {
                  O(t, l) && "width" !== l && (n[l] = t[l]);
                  var o = e.dataset[l];
                  I(o) || (n[l] = i.sanitizeValue(o));
                }),
                n.content && (n.type = "inline"),
                !n.type && r && (n.type = this.sourceType(r)),
                I(a))
              ) {
                if (!n.title && "a" == h) {
                  var d = e.title;
                  I(d) || "" === d || (n.title = d);
                }
                if (!n.title && "img" == h) {
                  var c = e.alt;
                  I(c) || "" === c || (n.title = c);
                }
              } else {
                var u = [];
                o(n, function (e, t) {
                  u.push(";\\s?" + t);
                }),
                  (u = u.join("\\s?:|")),
                  "" !== a.trim() &&
                    o(n, function (e, t) {
                      var s = a,
                        l = new RegExp("s?" + t + "s?:s?(.*?)(" + u + "s?:|$)"),
                        o = s.match(l);
                      if (o && o.length && o[1]) {
                        var r = o[1].trim().replace(/;\s*$/, "");
                        n[t] = i.sanitizeValue(r);
                      }
                    });
              }
              if (n.description && "." === n.description.substring(0, 1)) {
                var g;
                try {
                  g = document.querySelector(n.description).innerHTML;
                } catch (e) {
                  if (!(e instanceof DOMException)) throw e;
                }
                g && (n.description = g);
              }
              if (!n.description) {
                var v = e.querySelector(".glightbox-desc");
                v && (n.description = v.innerHTML);
              }
              return this.setSize(n, t, e), (this.slideConfig = n), n;
            },
          },
          {
            key: "setSize",
            value: function (e, t) {
              var i =
                  arguments.length > 2 && void 0 !== arguments[2]
                    ? arguments[2]
                    : null,
                n =
                  "video" == e.type
                    ? this.checkSize(t.videosWidth)
                    : this.checkSize(t.width),
                s = this.checkSize(t.height);
              return (
                (e.width =
                  O(e, "width") && "" !== e.width
                    ? this.checkSize(e.width)
                    : n),
                (e.height =
                  O(e, "height") && "" !== e.height
                    ? this.checkSize(e.height)
                    : s),
                i &&
                  "image" == e.type &&
                  ((e._hasCustomWidth = !!i.dataset.width),
                  (e._hasCustomHeight = !!i.dataset.height)),
                e
              );
            },
          },
          {
            key: "checkSize",
            value: function (e) {
              return M(e) ? "".concat(e, "px") : e;
            },
          },
          {
            key: "sanitizeValue",
            value: function (e) {
              return "true" !== e && "false" !== e ? e : "true" === e;
            },
          },
        ]),
        e
      );
    })(),
    $ = (function () {
      function e(i, n, s) {
        t(this, e), (this.element = i), (this.instance = n), (this.index = s);
      }
      return (
        n(e, [
          {
            key: "setContent",
            value: function () {
              var e = this,
                t =
                  arguments.length > 0 && void 0 !== arguments[0]
                    ? arguments[0]
                    : null,
                i =
                  arguments.length > 1 &&
                  void 0 !== arguments[1] &&
                  arguments[1];
              if (c(t, "loaded")) return !1;
              var n = this.instance.settings,
                s = this.slideConfig,
                l = w();
              T(n.beforeSlideLoad) &&
                n.beforeSlideLoad({ index: this.index, slide: t, player: !1 });
              var o = s.type,
                r = s.descPosition,
                a = t.querySelector(".gslide-media"),
                d = t.querySelector(".gslide-title"),
                u = t.querySelector(".gslide-desc"),
                g = t.querySelector(".gdesc-inner"),
                v = i,
                f = "gSlideTitle_" + this.index,
                p = "gSlideDesc_" + this.index;
              if (
                (T(n.afterSlideLoad) &&
                  (v = function () {
                    T(i) && i(),
                      n.afterSlideLoad({
                        index: e.index,
                        slide: t,
                        player: e.instance.getSlidePlayerInstance(e.index),
                      });
                  }),
                "" == s.title && "" == s.description
                  ? g && g.parentNode.parentNode.removeChild(g.parentNode)
                  : (d && "" !== s.title
                      ? ((d.id = f), (d.innerHTML = s.title))
                      : d.parentNode.removeChild(d),
                    u && "" !== s.description
                      ? ((u.id = p),
                        l && n.moreLength > 0
                          ? ((s.smallDescription = this.slideShortDesc(
                              s.description,
                              n.moreLength,
                              n.moreText
                            )),
                            (u.innerHTML = s.smallDescription),
                            this.descriptionEvents(u, s))
                          : (u.innerHTML = s.description))
                      : u.parentNode.removeChild(u),
                    h(a.parentNode, "desc-".concat(r)),
                    h(g.parentNode, "description-".concat(r))),
                h(a, "gslide-".concat(o)),
                h(t, "loaded"),
                "video" !== o)
              ) {
                if ("external" !== o)
                  return "inline" === o
                    ? (G.apply(this.instance, [t, s, this.index, v]),
                      void (
                        s.draggable &&
                        new V({
                          dragEl: t.querySelector(".gslide-inline"),
                          toleranceX: n.dragToleranceX,
                          toleranceY: n.dragToleranceY,
                          slide: t,
                          instance: this.instance,
                        })
                      ))
                    : void ("image" !== o
                        ? T(v) && v()
                        : j(t, s, this.index, function () {
                            var i = t.querySelector("img");
                            s.draggable &&
                              new V({
                                dragEl: i,
                                toleranceX: n.dragToleranceX,
                                toleranceY: n.dragToleranceY,
                                slide: t,
                                instance: e.instance,
                              }),
                              s.zoomable &&
                                i.naturalWidth > i.offsetWidth &&
                                (h(i, "zoomable"),
                                new H(i, t, function () {
                                  e.instance.resize();
                                })),
                              T(v) && v();
                          }));
                Z.apply(this, [t, s, this.index, v]);
              } else F.apply(this.instance, [t, s, this.index, v]);
            },
          },
          {
            key: "slideShortDesc",
            value: function (e) {
              var t =
                  arguments.length > 1 && void 0 !== arguments[1]
                    ? arguments[1]
                    : 50,
                i =
                  arguments.length > 2 &&
                  void 0 !== arguments[2] &&
                  arguments[2],
                n = document.createElement("div");
              n.innerHTML = e;
              var s = n.innerText,
                l = i;
              if ((e = s.trim()).length <= t) return e;
              var o = e.substr(0, t - 1);
              return l
                ? ((n = null),
                  o + '... <a href="#" class="desc-more">' + i + "</a>")
                : o;
            },
          },
          {
            key: "descriptionEvents",
            value: function (e, t) {
              var i = this,
                n = e.querySelector(".desc-more");
              if (!n) return !1;
              a("click", {
                onElement: n,
                withCallback: function (e, n) {
                  e.preventDefault();
                  var s = document.body,
                    l = u(n, ".gslide-desc");
                  if (!l) return !1;
                  (l.innerHTML = t.description), h(s, "gdesc-open");
                  var o = a("click", {
                    onElement: [s, u(l, ".gslide-description")],
                    withCallback: function (e, n) {
                      "a" !== e.target.nodeName.toLowerCase() &&
                        (d(s, "gdesc-open"),
                        h(s, "gdesc-closed"),
                        (l.innerHTML = t.smallDescription),
                        i.descriptionEvents(l, t),
                        setTimeout(function () {
                          d(s, "gdesc-closed");
                        }, 400),
                        o.destroy());
                    },
                  });
                },
              });
            },
          },
          {
            key: "create",
            value: function () {
              return m(this.instance.settings.slideHTML);
            },
          },
          {
            key: "getConfig",
            value: function () {
              k(this.element) ||
                this.element.hasOwnProperty("draggable") ||
                (this.element.draggable = this.instance.settings.draggable);
              var e = new U(this.instance.settings.slideExtraAttributes);
              return (
                (this.slideConfig = e.parseConfig(
                  this.element,
                  this.instance.settings
                )),
                this.slideConfig
              );
            },
          },
        ]),
        e
      );
    })(),
    J = w(),
    K =
      null !== w() ||
      void 0 !== document.createTouch ||
      "ontouchstart" in window ||
      "onmsgesturechange" in window ||
      navigator.msMaxTouchPoints,
    Q = document.getElementsByTagName("html")[0],
    ee = {
      selector: ".glightbox",
      elements: null,
      skin: "clean",
      theme: "clean",
      closeButton: !0,
      startAt: null,
      autoplayVideos: !0,
      autofocusVideos: !0,
      descPosition: "bottom",
      width: "900px",
      height: "506px",
      videosWidth: "960px",
      beforeSlideChange: null,
      afterSlideChange: null,
      beforeSlideLoad: null,
      afterSlideLoad: null,
      slideInserted: null,
      slideRemoved: null,
      slideExtraAttributes: null,
      onOpen: null,
      onClose: null,
      loop: !1,
      zoomable: !0,
      draggable: !0,
      dragAutoSnap: !1,
      dragToleranceX: 40,
      dragToleranceY: 65,
      preload: !0,
      oneSlidePerOpen: !1,
      touchNavigation: !0,
      touchFollowAxis: !0,
      keyboardNavigation: !0,
      closeOnOutsideClick: !0,
      plugins: !1,
      plyr: {
        css: "https://cdn.plyr.io/3.6.12/plyr.css",
        js: "https://cdn.plyr.io/3.6.12/plyr.js",
        config: {
          ratio: "16:9",
          fullscreen: { enabled: !0, iosNative: !0 },
          youtube: { noCookie: !0, rel: 0, showinfo: 0, iv_load_policy: 3 },
          vimeo: { byline: !1, portrait: !1, title: !1, transparent: !1 },
        },
      },
      openEffect: "zoom",
      closeEffect: "zoom",
      slideEffect: "slide",
      moreText: "See more",
      moreLength: 60,
      cssEfects: {
        fade: { in: "fadeIn", out: "fadeOut" },
        zoom: { in: "zoomIn", out: "zoomOut" },
        slide: { in: "slideInRight", out: "slideOutLeft" },
        slideBack: { in: "slideInLeft", out: "slideOutRight" },
        none: { in: "none", out: "none" },
      },
      svg: {
        close:
          '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 512 512" xml:space="preserve"><g><g><path d="M505.943,6.058c-8.077-8.077-21.172-8.077-29.249,0L6.058,476.693c-8.077,8.077-8.077,21.172,0,29.249C10.096,509.982,15.39,512,20.683,512c5.293,0,10.586-2.019,14.625-6.059L505.943,35.306C514.019,27.23,514.019,14.135,505.943,6.058z"/></g></g><g><g><path d="M505.942,476.694L35.306,6.059c-8.076-8.077-21.172-8.077-29.248,0c-8.077,8.076-8.077,21.171,0,29.248l470.636,470.636c4.038,4.039,9.332,6.058,14.625,6.058c5.293,0,10.587-2.019,14.624-6.057C514.018,497.866,514.018,484.771,505.942,476.694z"/></g></g></svg>',
        next: '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 477.175 477.175" xml:space="preserve"> <g><path d="M360.731,229.075l-225.1-225.1c-5.3-5.3-13.8-5.3-19.1,0s-5.3,13.8,0,19.1l215.5,215.5l-215.5,215.5c-5.3,5.3-5.3,13.8,0,19.1c2.6,2.6,6.1,4,9.5,4c3.4,0,6.9-1.3,9.5-4l225.1-225.1C365.931,242.875,365.931,234.275,360.731,229.075z"/></g></svg>',
        prev: '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 477.175 477.175" xml:space="preserve"><g><path d="M145.188,238.575l215.5-215.5c5.3-5.3,5.3-13.8,0-19.1s-13.8-5.3-19.1,0l-225.1,225.1c-5.3,5.3-5.3,13.8,0,19.1l225.1,225c2.6,2.6,6.1,4,9.5,4s6.9-1.3,9.5-4c5.3-5.3,5.3-13.8,0-19.1L145.188,238.575z"/></g></svg>',
      },
      slideHTML:
        '<div class="gslide">\n    <div class="gslide-inner-content">\n        <div class="ginner-container">\n            <div class="gslide-media">\n            </div>\n            <div class="gslide-description">\n                <div class="gdesc-inner">\n                    <h4 class="gslide-title"></h4>\n                    <div class="gslide-desc"></div>\n                </div>\n            </div>\n        </div>\n    </div>\n</div>',
      lightboxHTML:
        '<div id="glightbox-body" class="glightbox-container" tabindex="-1" role="dialog" aria-hidden="false">\n    <div class="gloader visible"></div>\n    <div class="goverlay"></div>\n    <div class="gcontainer">\n    <div id="glightbox-slider" class="gslider"></div>\n    <button class="gclose gbtn" aria-label="Close" data-taborder="3">{closeSVG}</button>\n    <button class="gprev gbtn" aria-label="Previous" data-taborder="2">{prevSVG}</button>\n    <button class="gnext gbtn" aria-label="Next" data-taborder="1">{nextSVG}</button>\n</div>\n</div>',
    },
    te = (function () {
      function e() {
        var i =
          arguments.length > 0 && void 0 !== arguments[0] ? arguments[0] : {};
        t(this, e),
          (this.customOptions = i),
          (this.settings = l(ee, i)),
          (this.effectsClasses = this.getAnimationClasses()),
          (this.videoPlayers = {}),
          (this.apiEvents = []),
          (this.fullElementsList = !1);
      }
      return (
        n(e, [
          {
            key: "init",
            value: function () {
              var e = this,
                t = this.getSelector();
              t &&
                (this.baseEvents = a("click", {
                  onElement: t,
                  withCallback: function (t, i) {
                    t.preventDefault(), e.open(i);
                  },
                })),
                (this.elements = this.getElements());
            },
          },
          {
            key: "open",
            value: function () {
              var e =
                  arguments.length > 0 && void 0 !== arguments[0]
                    ? arguments[0]
                    : null,
                t =
                  arguments.length > 1 && void 0 !== arguments[1]
                    ? arguments[1]
                    : null;
              if (0 === this.elements.length) return !1;
              (this.activeSlide = null),
                (this.prevActiveSlideIndex = null),
                (this.prevActiveSlide = null);
              var i = M(t) ? t : this.settings.startAt;
              if (k(e)) {
                var n = e.getAttribute("data-gallery");
                n &&
                  ((this.fullElementsList = this.elements),
                  (this.elements = this.getGalleryElements(this.elements, n))),
                  I(i) && (i = this.getElementIndex(e)) < 0 && (i = 0);
              }
              M(i) || (i = 0),
                this.build(),
                g(
                  this.overlay,
                  "none" === this.settings.openEffect
                    ? "none"
                    : this.settings.cssEfects.fade.in
                );
              var s = document.body,
                l = window.innerWidth - document.documentElement.clientWidth;
              if (l > 0) {
                var o = document.createElement("style");
                (o.type = "text/css"),
                  (o.className = "gcss-styles"),
                  (o.innerText = ".gscrollbar-fixer {margin-right: ".concat(
                    l,
                    "px}"
                  )),
                  document.head.appendChild(o),
                  h(s, "gscrollbar-fixer");
              }
              h(s, "glightbox-open"),
                h(Q, "glightbox-open"),
                J &&
                  (h(document.body, "glightbox-mobile"),
                  (this.settings.slideEffect = "slide")),
                this.showSlide(i, !0),
                1 === this.elements.length
                  ? (h(this.prevButton, "glightbox-button-hidden"),
                    h(this.nextButton, "glightbox-button-hidden"))
                  : (d(this.prevButton, "glightbox-button-hidden"),
                    d(this.nextButton, "glightbox-button-hidden")),
                (this.lightboxOpen = !0),
                this.trigger("open"),
                T(this.settings.onOpen) && this.settings.onOpen(),
                K && this.settings.touchNavigation && B(this),
                this.settings.keyboardNavigation && X(this);
            },
          },
          {
            key: "openAt",
            value: function () {
              var e =
                arguments.length > 0 && void 0 !== arguments[0]
                  ? arguments[0]
                  : 0;
              this.open(null, e);
            },
          },
          {
            key: "showSlide",
            value: function () {
              var e = this,
                t =
                  arguments.length > 0 && void 0 !== arguments[0]
                    ? arguments[0]
                    : 0,
                i =
                  arguments.length > 1 &&
                  void 0 !== arguments[1] &&
                  arguments[1];
              f(this.loader), (this.index = parseInt(t));
              var n = this.slidesContainer.querySelector(".current");
              n && d(n, "current"), this.slideAnimateOut();
              var s = this.slidesContainer.querySelectorAll(".gslide")[t];
              if (c(s, "loaded")) this.slideAnimateIn(s, i), p(this.loader);
              else {
                f(this.loader);
                var l = this.elements[t],
                  o = {
                    index: this.index,
                    slide: s,
                    slideNode: s,
                    slideConfig: l.slideConfig,
                    slideIndex: this.index,
                    trigger: l.node,
                    player: null,
                  };
                this.trigger("slide_before_load", o),
                  l.instance.setContent(s, function () {
                    p(e.loader),
                      e.resize(),
                      e.slideAnimateIn(s, i),
                      e.trigger("slide_after_load", o);
                  });
              }
              (this.slideDescription = s.querySelector(".gslide-description")),
                (this.slideDescriptionContained =
                  this.slideDescription &&
                  c(this.slideDescription.parentNode, "gslide-media")),
                this.settings.preload &&
                  (this.preloadSlide(t + 1), this.preloadSlide(t - 1)),
                this.updateNavigationClasses(),
                (this.activeSlide = s);
            },
          },
          {
            key: "preloadSlide",
            value: function (e) {
              var t = this;
              if (e < 0 || e > this.elements.length - 1) return !1;
              if (I(this.elements[e])) return !1;
              var i = this.slidesContainer.querySelectorAll(".gslide")[e];
              if (c(i, "loaded")) return !1;
              var n = this.elements[e],
                s = n.type,
                l = {
                  index: e,
                  slide: i,
                  slideNode: i,
                  slideConfig: n.slideConfig,
                  slideIndex: e,
                  trigger: n.node,
                  player: null,
                };
              this.trigger("slide_before_load", l),
                "video" === s || "external" === s
                  ? setTimeout(function () {
                      n.instance.setContent(i, function () {
                        t.trigger("slide_after_load", l);
                      });
                    }, 200)
                  : n.instance.setContent(i, function () {
                      t.trigger("slide_after_load", l);
                    });
            },
          },
          {
            key: "prevSlide",
            value: function () {
              this.goToSlide(this.index - 1);
            },
          },
          {
            key: "nextSlide",
            value: function () {
              this.goToSlide(this.index + 1);
            },
          },
          {
            key: "goToSlide",
            value: function () {
              var e =
                arguments.length > 0 && void 0 !== arguments[0] && arguments[0];
              if (
                ((this.prevActiveSlide = this.activeSlide),
                (this.prevActiveSlideIndex = this.index),
                !this.loop() && (e < 0 || e > this.elements.length - 1))
              )
                return !1;
              e < 0
                ? (e = this.elements.length - 1)
                : e >= this.elements.length && (e = 0),
                this.showSlide(e);
            },
          },
          {
            key: "insertSlide",
            value: function () {
              var e =
                  arguments.length > 0 && void 0 !== arguments[0]
                    ? arguments[0]
                    : {},
                t =
                  arguments.length > 1 && void 0 !== arguments[1]
                    ? arguments[1]
                    : -1;
              t < 0 && (t = this.elements.length);
              var i = new $(e, this, t),
                n = i.getConfig(),
                s = l({}, n),
                o = i.create(),
                r = this.elements.length - 1;
              (s.index = t),
                (s.node = !1),
                (s.instance = i),
                (s.slideConfig = n),
                this.elements.splice(t, 0, s);
              var a = null,
                h = null;
              if (this.slidesContainer) {
                if (t > r) this.slidesContainer.appendChild(o);
                else {
                  var d = this.slidesContainer.querySelectorAll(".gslide")[t];
                  this.slidesContainer.insertBefore(o, d);
                }
                ((this.settings.preload && 0 == this.index && 0 == t) ||
                  this.index - 1 == t ||
                  this.index + 1 == t) &&
                  this.preloadSlide(t),
                  0 === this.index && 0 === t && (this.index = 1),
                  this.updateNavigationClasses(),
                  (a = this.slidesContainer.querySelectorAll(".gslide")[t]),
                  (h = this.getSlidePlayerInstance(t)),
                  (s.slideNode = a);
              }
              this.trigger("slide_inserted", {
                index: t,
                slide: a,
                slideNode: a,
                slideConfig: n,
                slideIndex: t,
                trigger: null,
                player: h,
              }),
                T(this.settings.slideInserted) &&
                  this.settings.slideInserted({
                    index: t,
                    slide: a,
                    player: h,
                  });
            },
          },
          {
            key: "removeSlide",
            value: function () {
              var e =
                arguments.length > 0 && void 0 !== arguments[0]
                  ? arguments[0]
                  : -1;
              if (e < 0 || e > this.elements.length - 1) return !1;
              var t =
                this.slidesContainer &&
                this.slidesContainer.querySelectorAll(".gslide")[e];
              t &&
                (this.getActiveSlideIndex() == e &&
                  (e == this.elements.length - 1
                    ? this.prevSlide()
                    : this.nextSlide()),
                t.parentNode.removeChild(t)),
                this.elements.splice(e, 1),
                this.trigger("slide_removed", e),
                T(this.settings.slideRemoved) && this.settings.slideRemoved(e);
            },
          },
          {
            key: "slideAnimateIn",
            value: function (e, t) {
              var i = this,
                n = e.querySelector(".gslide-media"),
                s = e.querySelector(".gslide-description"),
                l = {
                  index: this.prevActiveSlideIndex,
                  slide: this.prevActiveSlide,
                  slideNode: this.prevActiveSlide,
                  slideIndex: this.prevActiveSlide,
                  slideConfig: I(this.prevActiveSlideIndex)
                    ? null
                    : this.elements[this.prevActiveSlideIndex].slideConfig,
                  trigger: I(this.prevActiveSlideIndex)
                    ? null
                    : this.elements[this.prevActiveSlideIndex].node,
                  player: this.getSlidePlayerInstance(
                    this.prevActiveSlideIndex
                  ),
                },
                o = {
                  index: this.index,
                  slide: this.activeSlide,
                  slideNode: this.activeSlide,
                  slideConfig: this.elements[this.index].slideConfig,
                  slideIndex: this.index,
                  trigger: this.elements[this.index].node,
                  player: this.getSlidePlayerInstance(this.index),
                };
              if (
                (n.offsetWidth > 0 && s && (p(s), (s.style.display = "")),
                d(e, this.effectsClasses),
                t)
              )
                g(
                  e,
                  this.settings.cssEfects[this.settings.openEffect].in,
                  function () {
                    i.settings.autoplayVideos && i.slidePlayerPlay(e),
                      i.trigger("slide_changed", { prev: l, current: o }),
                      T(i.settings.afterSlideChange) &&
                        i.settings.afterSlideChange.apply(i, [l, o]);
                  }
                );
              else {
                var r = this.settings.slideEffect,
                  a = "none" !== r ? this.settings.cssEfects[r].in : r;
                this.prevActiveSlideIndex > this.index &&
                  "slide" == this.settings.slideEffect &&
                  (a = this.settings.cssEfects.slideBack.in),
                  g(e, a, function () {
                    i.settings.autoplayVideos && i.slidePlayerPlay(e),
                      i.trigger("slide_changed", { prev: l, current: o }),
                      T(i.settings.afterSlideChange) &&
                        i.settings.afterSlideChange.apply(i, [l, o]);
                  });
              }
              setTimeout(function () {
                i.resize(e);
              }, 100),
                h(e, "current");
            },
          },
          {
            key: "slideAnimateOut",
            value: function () {
              if (!this.prevActiveSlide) return !1;
              var e = this.prevActiveSlide;
              d(e, this.effectsClasses), h(e, "prev");
              var t = this.settings.slideEffect,
                i = "none" !== t ? this.settings.cssEfects[t].out : t;
              this.slidePlayerPause(e),
                this.trigger("slide_before_change", {
                  prev: {
                    index: this.prevActiveSlideIndex,
                    slide: this.prevActiveSlide,
                    slideNode: this.prevActiveSlide,
                    slideIndex: this.prevActiveSlideIndex,
                    slideConfig: I(this.prevActiveSlideIndex)
                      ? null
                      : this.elements[this.prevActiveSlideIndex].slideConfig,
                    trigger: I(this.prevActiveSlideIndex)
                      ? null
                      : this.elements[this.prevActiveSlideIndex].node,
                    player: this.getSlidePlayerInstance(
                      this.prevActiveSlideIndex
                    ),
                  },
                  current: {
                    index: this.index,
                    slide: this.activeSlide,
                    slideNode: this.activeSlide,
                    slideIndex: this.index,
                    slideConfig: this.elements[this.index].slideConfig,
                    trigger: this.elements[this.index].node,
                    player: this.getSlidePlayerInstance(this.index),
                  },
                }),
                T(this.settings.beforeSlideChange) &&
                  this.settings.beforeSlideChange.apply(this, [
                    {
                      index: this.prevActiveSlideIndex,
                      slide: this.prevActiveSlide,
                      player: this.getSlidePlayerInstance(
                        this.prevActiveSlideIndex
                      ),
                    },
                    {
                      index: this.index,
                      slide: this.activeSlide,
                      player: this.getSlidePlayerInstance(this.index),
                    },
                  ]),
                this.prevActiveSlideIndex > this.index &&
                  "slide" == this.settings.slideEffect &&
                  (i = this.settings.cssEfects.slideBack.out),
                g(e, i, function () {
                  var t = e.querySelector(".ginner-container"),
                    i = e.querySelector(".gslide-media"),
                    n = e.querySelector(".gslide-description");
                  (t.style.transform = ""),
                    (i.style.transform = ""),
                    d(i, "greset"),
                    (i.style.opacity = ""),
                    n && (n.style.opacity = ""),
                    d(e, "prev");
                });
            },
          },
          {
            key: "getAllPlayers",
            value: function () {
              return this.videoPlayers;
            },
          },
          {
            key: "getSlidePlayerInstance",
            value: function (e) {
              var t = "gvideo" + e,
                i = this.getAllPlayers();
              return !(!O(i, t) || !i[t]) && i[t];
            },
          },
          {
            key: "stopSlideVideo",
            value: function (e) {
              if (k(e)) {
                var t = e.querySelector(".gvideo-wrapper");
                t && (e = t.getAttribute("data-index"));
              }
              console.log("stopSlideVideo is deprecated, use slidePlayerPause");
              var i = this.getSlidePlayerInstance(e);
              i && i.playing && i.pause();
            },
          },
          {
            key: "slidePlayerPause",
            value: function (e) {
              if (k(e)) {
                var t = e.querySelector(".gvideo-wrapper");
                t && (e = t.getAttribute("data-index"));
              }
              var i = this.getSlidePlayerInstance(e);
              i && i.playing && i.pause();
            },
          },
          {
            key: "playSlideVideo",
            value: function (e) {
              if (k(e)) {
                var t = e.querySelector(".gvideo-wrapper");
                t && (e = t.getAttribute("data-index"));
              }
              console.log("playSlideVideo is deprecated, use slidePlayerPlay");
              var i = this.getSlidePlayerInstance(e);
              i && !i.playing && i.play();
            },
          },
          {
            key: "slidePlayerPlay",
            value: function (e) {
              var t;
              if (
                !J ||
                (null !== (t = this.settings.plyr.config) &&
                  void 0 !== t &&
                  t.muted)
              ) {
                if (k(e)) {
                  var i = e.querySelector(".gvideo-wrapper");
                  i && (e = i.getAttribute("data-index"));
                }
                var n = this.getSlidePlayerInstance(e);
                n &&
                  !n.playing &&
                  (n.play(),
                  this.settings.autofocusVideos &&
                    n.elements.container.focus());
              }
            },
          },
          {
            key: "setElements",
            value: function (e) {
              var t = this;
              this.settings.elements = !1;
              var i = [];
              e &&
                e.length &&
                o(e, function (e, n) {
                  var s = new $(e, t, n),
                    o = s.getConfig(),
                    r = l({}, o);
                  (r.slideConfig = o),
                    (r.instance = s),
                    (r.index = n),
                    i.push(r);
                }),
                (this.elements = i),
                this.lightboxOpen &&
                  ((this.slidesContainer.innerHTML = ""),
                  this.elements.length &&
                    (o(this.elements, function () {
                      var e = m(t.settings.slideHTML);
                      t.slidesContainer.appendChild(e);
                    }),
                    this.showSlide(0, !0)));
            },
          },
          {
            key: "getElementIndex",
            value: function (e) {
              var t = !1;
              return (
                o(this.elements, function (i, n) {
                  if (O(i, "node") && i.node == e) return (t = n), !0;
                }),
                t
              );
            },
          },
          {
            key: "getElements",
            value: function () {
              var e = this,
                t = [];
              (this.elements = this.elements ? this.elements : []),
                !I(this.settings.elements) &&
                  E(this.settings.elements) &&
                  this.settings.elements.length &&
                  o(this.settings.elements, function (i, n) {
                    var s = new $(i, e, n),
                      o = s.getConfig(),
                      r = l({}, o);
                    (r.node = !1),
                      (r.index = n),
                      (r.instance = s),
                      (r.slideConfig = o),
                      t.push(r);
                  });
              var i = !1;
              return (
                this.getSelector() &&
                  (i = document.querySelectorAll(this.getSelector())),
                i
                  ? (o(i, function (i, n) {
                      var s = new $(i, e, n),
                        o = s.getConfig(),
                        r = l({}, o);
                      (r.node = i),
                        (r.index = n),
                        (r.instance = s),
                        (r.slideConfig = o),
                        (r.gallery = i.getAttribute("data-gallery")),
                        t.push(r);
                    }),
                    t)
                  : t
              );
            },
          },
          {
            key: "getGalleryElements",
            value: function (e, t) {
              return e.filter(function (e) {
                return e.gallery == t;
              });
            },
          },
          {
            key: "getSelector",
            value: function () {
              return (
                !this.settings.elements &&
                (this.settings.selector &&
                "data-" == this.settings.selector.substring(0, 5)
                  ? "*[".concat(this.settings.selector, "]")
                  : this.settings.selector)
              );
            },
          },
          {
            key: "getActiveSlide",
            value: function () {
              return this.slidesContainer.querySelectorAll(".gslide")[
                this.index
              ];
            },
          },
          {
            key: "getActiveSlideIndex",
            value: function () {
              return this.index;
            },
          },
          {
            key: "getAnimationClasses",
            value: function () {
              var e = [];
              for (var t in this.settings.cssEfects)
                if (this.settings.cssEfects.hasOwnProperty(t)) {
                  var i = this.settings.cssEfects[t];
                  e.push("g".concat(i.in)), e.push("g".concat(i.out));
                }
              return e.join(" ");
            },
          },
          {
            key: "build",
            value: function () {
              var e = this;
              if (this.built) return !1;
              var t = document.body.childNodes,
                i = [];
              o(t, function (e) {
                e.parentNode == document.body &&
                  "#" !== e.nodeName.charAt(0) &&
                  e.hasAttribute &&
                  !e.hasAttribute("aria-hidden") &&
                  (i.push(e), e.setAttribute("aria-hidden", "true"));
              });
              var n = O(this.settings.svg, "next")
                  ? this.settings.svg.next
                  : "",
                s = O(this.settings.svg, "prev") ? this.settings.svg.prev : "",
                l = O(this.settings.svg, "close")
                  ? this.settings.svg.close
                  : "",
                r = this.settings.lightboxHTML;
              (r = m(
                (r = (r = (r = r.replace(/{nextSVG}/g, n)).replace(
                  /{prevSVG}/g,
                  s
                )).replace(/{closeSVG}/g, l))
              )),
                document.body.appendChild(r);
              var d = document.getElementById("glightbox-body");
              this.modal = d;
              var g = d.querySelector(".gclose");
              (this.prevButton = d.querySelector(".gprev")),
                (this.nextButton = d.querySelector(".gnext")),
                (this.overlay = d.querySelector(".goverlay")),
                (this.loader = d.querySelector(".gloader")),
                (this.slidesContainer =
                  document.getElementById("glightbox-slider")),
                (this.bodyHiddenChildElms = i),
                (this.events = {}),
                h(this.modal, "glightbox-" + this.settings.skin),
                this.settings.closeButton &&
                  g &&
                  (this.events.close = a("click", {
                    onElement: g,
                    withCallback: function (t, i) {
                      t.preventDefault(), e.close();
                    },
                  })),
                g && !this.settings.closeButton && g.parentNode.removeChild(g),
                this.nextButton &&
                  (this.events.next = a("click", {
                    onElement: this.nextButton,
                    withCallback: function (t, i) {
                      t.preventDefault(), e.nextSlide();
                    },
                  })),
                this.prevButton &&
                  (this.events.prev = a("click", {
                    onElement: this.prevButton,
                    withCallback: function (t, i) {
                      t.preventDefault(), e.prevSlide();
                    },
                  })),
                this.settings.closeOnOutsideClick &&
                  (this.events.outClose = a("click", {
                    onElement: d,
                    withCallback: function (t, i) {
                      e.preventOutsideClick ||
                        c(document.body, "glightbox-mobile") ||
                        u(t.target, ".ginner-container") ||
                        u(t.target, ".gbtn") ||
                        c(t.target, "gnext") ||
                        c(t.target, "gprev") ||
                        e.close();
                    },
                  })),
                o(this.elements, function (t, i) {
                  e.slidesContainer.appendChild(t.instance.create()),
                    (t.slideNode =
                      e.slidesContainer.querySelectorAll(".gslide")[i]);
                }),
                K && h(document.body, "glightbox-touch"),
                (this.events.resize = a("resize", {
                  onElement: window,
                  withCallback: function () {
                    e.resize();
                  },
                })),
                (this.built = !0);
            },
          },
          {
            key: "resize",
            value: function () {
              var e =
                arguments.length > 0 && void 0 !== arguments[0]
                  ? arguments[0]
                  : null;
              if ((e = e || this.activeSlide) && !c(e, "zoomed")) {
                var t = y(),
                  i = e.querySelector(".gvideo-wrapper"),
                  n = e.querySelector(".gslide-image"),
                  s = this.slideDescription,
                  l = t.width,
                  o = t.height;
                if (
                  (l <= 768
                    ? h(document.body, "glightbox-mobile")
                    : d(document.body, "glightbox-mobile"),
                  i || n)
                ) {
                  var r = !1;
                  if (
                    (s &&
                      (c(s, "description-bottom") || c(s, "description-top")) &&
                      !c(s, "gabsolute") &&
                      (r = !0),
                    n)
                  )
                    if (l <= 768) n.querySelector("img");
                    else if (r) {
                      var a = s.offsetHeight,
                        u = n.querySelector("img");
                      u.setAttribute(
                        "style",
                        "max-height: calc(100vh - ".concat(a, "px)")
                      ),
                        s.setAttribute(
                          "style",
                          "max-width: ".concat(u.offsetWidth, "px;")
                        );
                    }
                  if (i) {
                    var g = O(this.settings.plyr.config, "ratio")
                      ? this.settings.plyr.config.ratio
                      : "";
                    if (!g) {
                      var v = i.clientWidth,
                        f = i.clientHeight,
                        p = v / f;
                      g = "".concat(v / p, ":").concat(f / p);
                    }
                    var m = g.split(":"),
                      x = this.settings.videosWidth,
                      b = this.settings.videosWidth,
                      S =
                        (b =
                          M(x) || -1 !== x.indexOf("px")
                            ? parseInt(x)
                            : -1 !== x.indexOf("vw")
                            ? (l * parseInt(x)) / 100
                            : -1 !== x.indexOf("vh")
                            ? (o * parseInt(x)) / 100
                            : -1 !== x.indexOf("%")
                            ? (l * parseInt(x)) / 100
                            : parseInt(i.clientWidth)) /
                        (parseInt(m[0]) / parseInt(m[1]));
                    if (
                      ((S = Math.floor(S)),
                      r && (o -= s.offsetHeight),
                      b > l || S > o || (o < S && l > b))
                    ) {
                      var w = i.offsetWidth,
                        T = i.offsetHeight,
                        C = o / T,
                        k = { width: w * C, height: T * C };
                      i.parentNode.setAttribute(
                        "style",
                        "max-width: ".concat(k.width, "px")
                      ),
                        r &&
                          s.setAttribute(
                            "style",
                            "max-width: ".concat(k.width, "px;")
                          );
                    } else
                      (i.parentNode.style.maxWidth = "".concat(x)),
                        r &&
                          s.setAttribute("style", "max-width: ".concat(x, ";"));
                  }
                }
              }
            },
          },
          {
            key: "reload",
            value: function () {
              this.init();
            },
          },
          {
            key: "updateNavigationClasses",
            value: function () {
              var e = this.loop();
              d(this.nextButton, "disabled"),
                d(this.prevButton, "disabled"),
                0 == this.index && this.elements.length - 1 == 0
                  ? (h(this.prevButton, "disabled"),
                    h(this.nextButton, "disabled"))
                  : 0 !== this.index || e
                  ? this.index !== this.elements.length - 1 ||
                    e ||
                    h(this.nextButton, "disabled")
                  : h(this.prevButton, "disabled");
            },
          },
          {
            key: "loop",
            value: function () {
              var e = O(this.settings, "loopAtEnd")
                ? this.settings.loopAtEnd
                : null;
              return (e = O(this.settings, "loop") ? this.settings.loop : e), e;
            },
          },
          {
            key: "close",
            value: function () {
              var e = this;
              if (!this.lightboxOpen) {
                if (this.events) {
                  for (var t in this.events)
                    this.events.hasOwnProperty(t) && this.events[t].destroy();
                  this.events = null;
                }
                return !1;
              }
              if (this.closing) return !1;
              (this.closing = !0),
                this.slidePlayerPause(this.activeSlide),
                this.fullElementsList &&
                  (this.elements = this.fullElementsList),
                this.bodyHiddenChildElms.length &&
                  o(this.bodyHiddenChildElms, function (e) {
                    e.removeAttribute("aria-hidden");
                  }),
                h(this.modal, "glightbox-closing"),
                g(
                  this.overlay,
                  "none" == this.settings.openEffect
                    ? "none"
                    : this.settings.cssEfects.fade.out
                ),
                g(
                  this.activeSlide,
                  this.settings.cssEfects[this.settings.closeEffect].out,
                  function () {
                    if (
                      ((e.activeSlide = null),
                      (e.prevActiveSlideIndex = null),
                      (e.prevActiveSlide = null),
                      (e.built = !1),
                      e.events)
                    ) {
                      for (var t in e.events)
                        e.events.hasOwnProperty(t) && e.events[t].destroy();
                      e.events = null;
                    }
                    var i = document.body;
                    d(Q, "glightbox-open"),
                      d(
                        i,
                        "glightbox-open touching gdesc-open glightbox-touch glightbox-mobile gscrollbar-fixer"
                      ),
                      e.modal.parentNode.removeChild(e.modal),
                      e.trigger("close"),
                      T(e.settings.onClose) && e.settings.onClose();
                    var n = document.querySelector(".gcss-styles");
                    n && n.parentNode.removeChild(n),
                      (e.lightboxOpen = !1),
                      (e.closing = null);
                  }
                );
            },
          },
          {
            key: "destroy",
            value: function () {
              this.close(),
                this.clearAllEvents(),
                this.baseEvents && this.baseEvents.destroy();
            },
          },
          {
            key: "on",
            value: function (e, t) {
              var i =
                arguments.length > 2 && void 0 !== arguments[2] && arguments[2];
              if (!e || !T(t))
                throw new TypeError("Event name and callback must be defined");
              this.apiEvents.push({ evt: e, once: i, callback: t });
            },
          },
          {
            key: "once",
            value: function (e, t) {
              this.on(e, t, !0);
            },
          },
          {
            key: "trigger",
            value: function (e) {
              var t = this,
                i =
                  arguments.length > 1 && void 0 !== arguments[1]
                    ? arguments[1]
                    : null,
                n = [];
              o(this.apiEvents, function (t, s) {
                var l = t.evt,
                  o = t.once,
                  r = t.callback;
                l == e && (r(i), o && n.push(s));
              }),
                n.length &&
                  o(n, function (e) {
                    return t.apiEvents.splice(e, 1);
                  });
            },
          },
          {
            key: "clearAllEvents",
            value: function () {
              this.apiEvents.splice(0, this.apiEvents.length);
            },
          },
          {
            key: "version",
            value: function () {
              return "3.1.0";
            },
          },
        ]),
        e
      );
    })();
  return function () {
    var e = arguments.length > 0 && void 0 !== arguments[0] ? arguments[0] : {},
      t = new te(e);
    return t.init(), t;
  };
});
(function ($) {
  "use strict";

  const swiper = new Swiper(".swiper-slider", {
    // Optional parameters
    centeredSlides: true,
    slidesPerView: 1,
    grabCursor: true,
    freeMode: false,
    loop: true,
    mousewheel: false,
    keyboard: {
      enabled: true,
    },

    // Enabled autoplay mode
    autoplay: {
      delay: 3000,
      disableOnInteraction: false,
    },

    // If we need pagination
    pagination: {
      el: ".swiper-pagination",
      dynamicBullets: false,
      clickable: true,
    },

    // If we need navigation
    navigation: {
      nextEl: ".swiper-button-next",
      prevEl: ".swiper-button-prev",
    },

    // Responsive breakpoints
    breakpoints: {
      640: {
        slidesPerView: 1.25,
        spaceBetween: 20,
      },
      1024: {
        slidesPerView: 2,
        spaceBetween: 20,
      },
    },
  });

  // input spinner
  var initQuantitySpinner = function () {
    $(".product-qty").each(function () {
      var $el_product = $(this);
      var quantity = 0;

      $el_product.find(".quantity-right-plus").click(function (e) {
        e.preventDefault();
        var quantity = parseInt($el_product.find("#quantity").val());
        $el_product.find("#quantity").val(quantity + 1);
      });

      $el_product.find(".quantity-left-minus").click(function (e) {
        e.preventDefault();
        var quantity = parseInt($el_product.find("#quantity").val());
        if (quantity > 0) {
          $el_product.find("#quantity").val(quantity - 1);
        }
      });
    });
  };

  // init jarallax parallax
  var initJarallax = function () {
    jarallax(document.querySelectorAll(".jarallax"));

    jarallax(document.querySelectorAll(".jarallax-keep-img"), {
      keepImg: true,
    });
  };

  var initGLightbox = function () {
    var lightbox = GLightbox();

    lightbox.on("open", (target) => {
      console.log("lightbox opened");
    });
    var lightboxDescription = GLightbox({
      selector: ".glightbox2",
    });
    var lightboxVideo = GLightbox({
      selector: ".glightbox3",
    });
    lightboxVideo.on("slide_changed", ({ prev, current }) => {
      console.log("Prev slide", prev);
      console.log("Current slide", current);

      const { slideIndex, slideNode, slideConfig, player } = current;

      if (player) {
        if (!player.ready) {
          // If player is not ready
          player.on("ready", (event) => {
            // Do something when video is ready
          });
        }

        player.on("play", (event) => {
          console.log("Started play");
        });

        player.on("volumechange", (event) => {
          console.log("Volume change");
        });

        player.on("ended", (event) => {
          console.log("Video ended");
        });
      }
    });
  };

  $(document).ready(function () {
    initJarallax();
    initQuantitySpinner();
    initSlider();
    initGLightbox();

    AOS.init({
      duration: 1200,
      once: true,
    });

    $(".navbar").on("click", ".search-toggle", function (e) {
      var selector = $(this).data("selector");

      $(selector).toggleClass("show").find(".search-input").focus();
      // $(selector).find('.autocomplete').focus();
      $(this).toggleClass("active");

      e.preventDefault();
    });

    // close when click off of container
    $(document).on("click touchstart", function (e) {
      if (
        !$(e.target).is(".search-toggle, .search-toggle *, .navbar, .navbar *")
      ) {
        $(".search-toggle").removeClass("active");
        $(".navbar").removeClass("show");
      }
    });

    // $('.main-slider').each(function () {

    //   $('.main-slider').slick({
    //     autoplay: false,
    //     autoplaySpeed: 2000,
    //     arrows: true,
    //     dots: true,
    //   });

    // });

    // $('.products-slider').each(function () {

    //   $('.products-slider').slick({
    //     slidesToShow: 4,
    //     slidesToScroll: 1,
    //     autoplay: false,
    //     autoplaySpeed: 2000,
    //     dots: true,
    //     responsive: [
    //       {
    //         breakpoint: 600,
    //         settings: {
    //           slidesToShow: 2,
    //           slidesToScroll: 1
    //         }
    //       },
    //       {
    //         breakpoint: 480,
    //         settings: {
    //           slidesToShow: 2,
    //           slidesToScroll: 1
    //         }
    //       }
    //       // You can unslick at a given breakpoint now by adding:
    //       // settings: "unslick"
    //       // instead of a settings object
    //     ]
    //   });
    // });

    // $('.testimonial-slider').each(function () {

    //   $('.testimonial-slider').slick({
    //     dots: true,
    //     arrows: false,
    //     infinite: true,
    //     speed: 500,
    //   });

    // });
  });
  // document ready
})(jQuery);

const swiper = new Swiper(".swiper-slider", {
  // Optional parameters
  centeredSlides: true,
  slidesPerView: 1,
  grabCursor: true,
  freeMode: false,
  loop: true,
  mousewheel: false,
  keyboard: {
    enabled: true,
  },

  // Enabled autoplay mode
  autoplay: {
    delay: 3000,
    disableOnInteraction: false,
  },

  // If we need pagination
  pagination: {
    el: ".swiper-pagination",
    dynamicBullets: false,
    clickable: true,
  },

  // If we need navigation
  navigation: {
    nextEl: ".swiper-button-next",
    prevEl: ".swiper-button-prev",
  },

  // Responsive breakpoints
  breakpoints: {
    640: {
      slidesPerView: 1.25,
      spaceBetween: 20,
    },
    1024: {
      slidesPerView: 2,
      spaceBetween: 20,
    },
  },
});
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".dropdown-menu").forEach(function (element) {
    element.addEventListener("click", function (e) {
      e.stopPropagation();
    });
  });

  if (window.innerWidth < 992) {
    document.querySelectorAll(".dropdown-menu a").forEach(function (element) {
      element.addEventListener("click", function (e) {
        let nextEl = this.nextElementSibling;
        if (nextEl && nextEl.classList.contains("submenu")) {
          e.preventDefault();
          if (nextEl.style.display == "block") {
            nextEl.style.display = "none";
          } else {
            nextEl.style.display = "block";
          }
        }
      });
    });
  }
});
