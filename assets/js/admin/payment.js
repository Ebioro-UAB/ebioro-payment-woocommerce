(function (e) {
    var t = {};  
  
    function n(r) {
      if (t[r]) return t[r].exports;
      var o = t[r] = { i: r, l: false, exports: {} };
      e[r].call(o.exports, o, o.exports, n);
      o.l = true;
      return o.exports;
    }
  
    n.m = e;  
    n.c = t;   
    n.d = function (e, t, r) {
      if (!n.o(e, t)) {
        Object.defineProperty(e, t, { enumerable: true, get: r });
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
    n(n.s = 6);  
  })({
    6: function (e, t) {
      let n = document.getElementById("woocommerce_ebioro_test_mode");
      let r = document.getElementById("woocommerce_ebioro_test_api_key").closest("tr");
      let o = document.getElementById("woocommerce_ebioro_test_api_secret").closest("tr");
  
      if (n.checked === true) {
        r.setAttribute("style", "display:inline-flex");
        o.setAttribute("style", "display:inline-flex");
      } else {
        r.setAttribute("style", "display:none");
        o.setAttribute("style", "display:none");
      }
  
      n.addEventListener("change", function () {
        if (this.checked === true) {
          r.setAttribute("style", "display:inline-flex");
          o.setAttribute("style", "display:inline-flex");
        } else {
          r.setAttribute("style", "display:none");
          o.setAttribute("style", "display:none");
        }
      });
    }
  });
  