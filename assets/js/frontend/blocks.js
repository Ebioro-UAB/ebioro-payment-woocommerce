(function (e) {
    var t = {};
  
    function n(o) {
      if (t[o]) return t[o].exports;
      var r = t[o] = { i: o, l: false, exports: {} };
      e[o].call(r.exports, r, r.exports, n);
      r.l = true;
      return r.exports;
    }
  
    n.m = e;
    n.c = t;
  
    n.d = function (e, t, o) {
      if (!n.o(e, t)) {
        Object.defineProperty(e, t, { enumerable: true, get: o });
      }
    };

    n.r = function (e) {
      if (typeof Symbol !== 'undefined' && Symbol.toStringTag) {
        Object.defineProperty(e, Symbol.toStringTag, { value: 'Module' });
      }
      Object.defineProperty(e, "__esModule", { value: true });
    };
  
    n.t = function (e, t) {
      if (1 & t && (e = n(e)), 8 & t) return e;
      if (4 & t && 'object' === typeof e && e && e.__esModule) return e;
      var r = Object.create(null);
      n.r(r);
      Object.defineProperty(r, 'default', { enumerable: true, value: e });
      if (2 & t && 'string' !== typeof e)
        for (var o in e) n.d(r, o, function (t) {
          return e[t];
        }.bind(null, o));
      return r;
    };
  
    n.n = function (e) {
      var t = e && e.__esModule ? function () { return e.default; } : function () { return e; };
      n.d(t, 'a', t);
      return t;
    };
  
    n.o = function (e, t) {
      return Object.prototype.hasOwnProperty.call(e, t);
    };
  
    n.p = "";
    n(n.s = 5);
  })([
    function (e, t) {
      e.exports = window.wp.element;  
    },
    function (e, t) {
      e.exports = window.wp.htmlEntities;  
    },
    function (e, t) {
      e.exports = window.wp.i18n;  
    },
    function (e, t) {
      e.exports = window.wc.wcBlocksRegistry;  
    },
    function (e, t) {
      e.exports = window.wc.wcSettings;  
    },
    function (e, t, n) {
      "use strict";
      n.r(t);
      var o = n(0),
          r = n(2),
          c = n(3),
          i = n(1),
          u = n(4);
  
      const l = Object(u.getSetting)("ebioro_data", {});
  
      const a = Object(r.__)("Ebioro Payments", "woo-gutenberg-products-block");

      const s = Object(i.decodeEntities)(l.title) || a;
  
      const f = () => Object(i.decodeEntities)(l.description || "");
  
      const d = {
        name: "ebioro",
        label: Object(o.createElement)(e => {
          const { PaymentMethodLabel: t } = e.components;
          return Object(o.createElement)(t, { text: s });
        }, null),
        content: Object(o.createElement)(f, null),
        edit: Object(o.createElement)(f, null),
        canMakePayment: () => true,
        ariaLabel: s,
        supports: { features: l.supports }
      };

      Object(c.registerPaymentMethod)(d);
    }
  ]);
  