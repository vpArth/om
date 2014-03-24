if (typeof String.prototype.trimLeft !== "function") {
    String.prototype.trimLeft = function() {
        return this.replace(/^\s+/, "");
    };
}
if (typeof String.prototype.trimRight !== "function") {
    String.prototype.trimRight = function() {
        return this.replace(/\s+$/, "");
    };
}
if (typeof String.prototype.trim !== "function") {
    String.prototype.trim = function() {
        return this.replace(/^\s+|\s+$/g, "");
    };
}
if (typeof Array.prototype.map !== "function") {
    Array.prototype.map = function(callback, thisArg) {
        for (var i=0, n=this.length, a=[]; i<n; i++) {
            if (i in this) a[i] = callback.call(thisArg, this[i]);
        }
        return a;
    };
}
var cookie = {
    all: function() {
        var c = document.cookie, v = 0, cookies = {};
        if (document.cookie.match(/^\s*\$Version=(?:"1"|1);\s*(.*)/)) {
            c = RegExp.$1;
            v = 1;
        }
        if (v === 0) {
            c.split(/[,;]/).map(function(cookie) {
                var parts = cookie.split(/=/, 2),
                    name = decodeURIComponent(parts[0].trimLeft()),
                    value = parts.length > 1 ? decodeURIComponent(parts[1].trimRight()) : null;
                cookies[name] = value;
            });
        } else {
            c.match(/(?:^|\s+)([!#$%&'*+\-.0-9A-Z^`a-z|~]+)=([!#$%&'*+\-.0-9A-Z^`a-z|~]*|"(?:[\x20-\x7E\x80\xFF]|\\[\x00-\x7F])*")(?=\s*[,;]|$)/g).map(function($0, $1) {
                var name = $0,
                    value = $1.charAt(0) === '"'
                        ? $1.substr(1, -1).replace(/\\(.)/g, "$1")
                        : $1;
                cookies[name] = value;
            });
        }
        return cookies;
    },
    get: function(key, def) {
        return this.all()[key] || def;
    },
    set: function(key, value, options) {
        if (typeof options == 'undefined') options = {};
        if (typeof options.expires === 'number') {
            var secs = options.expires, t = options.expires = new Date();
            t.setTime(+t + secs * 1e+3);
        }
        var data = [];
        data.push(encodeURIComponent(key)+ '='+ String(value));
        if (options.expires) data.push('expires=' + options.expires.toUTCString());
        if (options.path) data.push('path=' + options.path);
        if (options.domain) data.push('domain=' + options.domain);
        if (options.secure) data.push('secure');
        data = data.join('; ');
        document.cookie = data;
        return data;
    },
    del: function(key) {
        this.set(key, '');
    }
};

function wait(check, callable, args, self) {
    if (check()) return callable.apply(self || callable, args);
    return setTimeout(function(){
        wait(check, callable, args, self);
    }, 100);
}