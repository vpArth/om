function log(){console.log(arguments);}

var cook = {
    authToken: 'auth_token',
    backView: 'last_view',
    currView: 'curr_view'
}

var errors = {
    INVALID_PARAMS: 22004,
    UNIQUE_FAILED: 22005,
    NOT_AUTHORIZED: 21001,
    BAD_TOKEN: 21002,
    BAD_CREDENTIALS: 21003,
    USER_NOT_FOUND: 21004
};

function APIError(msg, code) {
    var self = this;
    self.name = 'APIError';
    self.message = msg || 'Some API error happened';
    self.code = code || 'UNKNOWN';
    self.getCode = function getCode() { return self.code; }
}
APIError.prototype = new Error;
APIError.prototype.constructor = APIError;

function API(baseUri) {
    function getBaseURI() { return baseUri; }
    function setBaseUri(uri) { baseUri = uri; }
    this.setBaseUri = setBaseUri;

    function request(method, path, params, cb) {
        var self = this;
        var data = method==='GET' ? params : JSON.stringify(params);
        $('.view.shadow').show();
        $("body").css("cursor", "progress");
        $.ajax({
            type: method,
            url: getBaseURI() + path,
            contentType: "application/json",
            data: data,
            // timeout: 3000,
            context: self
        }).always(function(data, status){
            if (status === 'timeout') {
                data = {
                    status: -1,
                    message: 'Server not responding...'
                };
            }
            if (data.responseText) {
                try {
                    data = JSON.parse(data.responseText);
                } catch (e) {
                    data = {
                        status: -1,
                        message: 'Internal Server Error'
                    }
                }
            }
            $("body").css("cursor", "default");
            $('.view.shadow').hide();
            console.debug(data);
            cb(data);
        });
    }
    function debug(data) {
        if (!data) { $('.debug-info').text("No data");return;}
        var time = (data.time*1000).toFixed(3) + 'ms';
        var dbtime = (data.db.time*1000).toFixed(3) + 'ms';
        var cachecount =
            (data.cache.count.get) + '/' +
            (data.cache.count.set) + '/' +
            (data.cache.count.del) + '';
        var cachetime =
            (data.cache.time.get*1000).toFixed(3) + '/' +
            (data.cache.time.set*1000).toFixed(3) + '/' +
            (data.cache.time.del*1000).toFixed(3) + 'ms';

        $('.debug-info').text(
            [
                time,
                'DB: '+data.db.count+' ('+dbtime+')',
                'Cache: '+cachecount+' ('+cachetime+')',
            ].join('; ')
        );
    }
    function response(data, cb) {
        if(data.status != 0) {
            return cb(new APIError(data.message, data.status));
        }
        debug(data.debug);
        return cb(null, data.result, data.debug);
    }
    //pages
    /**
     * Receive users widget
     * @param params object {page, size}
     * @param cb function(APIError, result)
     */
    function users(params, cb) {
        params.token = cookie.get(cook.authToken);
        request('GET', 'users', params, function(data){
            return response(data, cb);
        });
    }
    function messages(params, cb) {
        params.token = cookie.get(cook.authToken);
        request('GET', 'messages', params, function(data){
            return response(data, cb);
        });
    }
    function dialog() {
    }
    function profile(id, cb) {
        params = {};
        params.token = cookie.get(cook.authToken);
        request('GET', 'user/'+id, params, function(data){
            return response(data, cb);
        });
    }
    function options(cb) {
        params = {};
        params.token = cookie.get(cook.authToken);
        request('GET', 'user/me', params, function(data){
            return response(data, cb);
        });
    }
    // actions
    function register(data, cb) {
        request('POST', 'register', data, function(data){
            return response(data, cb);
        });
    }
    function login(data, cb) {
        request('GET', 'login', data, function(data){
            return response(data, function(err, result, debug){
                if(err) return cb(err);
                return cb(null, result.token, debug);
            });
        });
    }
    function checkAuth(cb) {
        var token = cookie.get(cook.authToken);
        request('GET', 'ok', {token: token}, function(data){
            return response(data, function(err, result, debug){
                if(err) return cb(err);
                return cb(null, result.token, debug);
            });
        });
    }
    function logout(cb) {
        var token = cookie.get(cook.authToken);
        request('GET', 'logout', {token: token}, function(data){
            return cb(null, true);
        });
    }
    function postMsg() {
    }
    function updateProfile(data, cb) {
        data.token = cookie.get(cook.authToken);
        request('PUT', 'user', data, function(data){
            return response(data, cb);
        });
    }

    this.users = users;
    this.messages = messages;
    this.options = options;
    this.dialog = dialog;
    this.profile = profile;

    this.login = login;
    this.checkAuth = checkAuth;
    this.register = register;
    this.logout = logout;
    this.postMsg = postMsg;
    this.updateProfile = updateProfile;

    this.request = request;
}

function App(api) {
    var self = this;
    var views = [
        'greet',
        'register',
        'login',
        'main',
        'users',
        'profile',
        'messages',
        'options',
        'updateFields',
        'updatePass'
    ];
    var updates = {
        users: [users, 10000],
        messages: [messages, 5000],
    }
    function updateUsername() {
        $('.head .me').text(self.username || '');
    }
    function toggleView(id, timeout) {
        var lastId = views[$('.view.active').index()];
        if(!timeout) timeout = 50;
        setTimeout(function(){
            var index = views.indexOf(id);
            if (index == -1) {
                console.log('Wrong view id: '+id);
                return;
            }
            if(self.refreshId) { clearInterval(self.refreshId); self.refreshId = null; }
            cookie.set(cook.backView, lastId);
            cookie.set(cook.currView, id);
            updateUsername();
            $('.view').removeClass('active');
            $('.view:eq('+index+')').addClass('active');

            if(updates[id])
                self.refreshId = setInterval(updates[id][0], updates[id][1]);

        }, timeout);
    }
    function getView(id) {
        var index = id ? views.indexOf(id) : $('.view.active').index();
        if (index == -1) {
            console.log('Wrong view id: '+id);
            return false;
        }
        return $('.view:eq('+index+')');
    }
    function init() {
        var token = cookie.get(cook.authToken);
        if (token) {
            return api.checkAuth(function(err){
                if (!err) {
                    toggleView('main');
                }
            })
        }
        return 0;
    }
    function error(view, msg) {
        var message = msg&&msg.message || msg || "Error";
        console.error(msg);
        if (msg instanceof APIError) {
            switch (msg.code) {
                case errors.BAD_TOKEN:
                    setTimeout(function(){toggleView('greet');}, 3000);
                break;
            }
        }
        // var
        var info = $('li.info', view);
        if(!info.attr('info'))
            info.attr('info', info.html());
        var th = info.attr('timeout');
        if(th) {
            console.info('cancelled '+th);
            clearTimeout(th);
        }
        th = setTimeout(function() {
            info.html(info.attr('info'));
            info.removeAttr('info');
            console.info('finished '+info.attr('timeout'));
            info.removeAttr('timeout');
        }, 3000);
        info.attr('timeout', th);
        console.info('started '+th);
        info.html($('<span style="color: #f66; font-size: 14px;">'+message+'</span>'));
    }
    function greetPage() {
        toggleView('greet');
    }
    function registerPage() {
        getView('register').find('input').val('');
        toggleView('register');
    }
    function loginPage() {
        getView('login').find('input').val('');
        toggleView('login');
    }
    function register() {
        var data = {};
        var view = getView('register');
        view.find('input').each(function(index, el){
            data[$(el).attr('name')] = $(el).val();
        });
        if (data.password.length < 8)
            return error(view, 'Password should be at least 8 chars');
        if (data.password !== data.confirm)
            return error(view, 'Passwords do not equals');
        if (!data.username||!data.email)
            return error(view, 'Please fill all fields');
        if (!$('input[name=email]', view)[0].validity.valid)
            return error(view, 'Email format incorrect');
        api.register(data, function(err, result){
            if(err) return error(view, err);
            toggleView('login');
        })
        return true;
    }
    function updateFields() {
        var data = {};
        var view = getView('updateFields');
        view.find('input').each(function(index, el){
            data[$(el).attr('name')] = $(el).val();
        });
        if (!data.username||!data.email)
            return error(view, 'Please fill all fields');
        if (!$('input[name=email]', view)[0].validity.valid)
            return error(view, 'Email format incorrect');
        if ((data.username === self.userdata.username) && (data.email === self.userdata.email))
            return error(view, 'Nothing changed...');
        api.updateProfile(data, function(err, result){
            if(err) return error(view, err);
            setTimeout(function(){options()}, 2000);
        })
    }
    function updatePass() {
        var data = {};
        var view = getView('updatePass');
        view.find('input').each(function(index, el){
            data[$(el).attr('name')] = $(el).val();
        });
        if (data.password.length < 8)
            return error(view, 'Password should be at least 8 chars');
        if (data.password !== data.confirm)
            return error(view, 'Passwords do not equals');
        api.updateProfile(data, function(err, result){
            if(err) return error(view, err);
            setTimeout(function(){options()}, 2000);
        })
    }
    function login() {
        var data = {};
        var view = getView('login');
        view.find('input').each(function(index, el){
            data[$(el).attr('name')] = $(el).val();
        });
        if (!data.username||!data.password)
            return error(view, 'Please fill all fields');

        api.login(data, function(err, token, debug){
            if(err) return error(view, err);
            cookie.set(cook.authToken, token);
            self.username = data.username;
            toggleView('main');
        });
        return true;
    }
    function logout() {
        api.logout(function(err, success){
            if(err) return error(getView(), err);
            if(success) {
                cookie.del(cook.authToken);
                return toggleView('greet');
            }
        });
    }
    function main() {
        toggleView('main');
    }
    function getOnlineBullet(online) {
        return '<div class="bullet '+(online?'online':'offline')+'" ></div>';
    }
    function users(page) {
        var view = getView();
        // if(typeof page === 'undefined') page = self.user_page || 0;
        if(typeof page === 'undefined') page = self.user_page || 0;
        api.users({
            page: page,
            size: 5
        }, function(err, result) {
            if(err) return error(view, err);
            self.user_pages = Math.ceil(result.count/result.size);

            var view = getView('users');

            var start = +result.page * parseInt(result.size)+1;
            var end = Math.min(start-1 + parseInt(result.size), result.count);
            $('.info', view).text('Users ('+start+' - '+end+' from '+result.count+')');

            //hide prev/next
            var prev_arrow = (page == 0) ? 'hide' : 'show';
            var next_arrow = (page == self.user_pages - 1) ? 'hide' : 'show';
            $('.paginator.left', view)[prev_arrow]();
            $('.paginator.right', view)[next_arrow]();

            var ul = $('.list', view);
            var html = '';
            for (var i = 0, n = result.data.length; i<n; i++) {
                var row = result.data[i];
                html +=
                '<li onclick="app.profile('+row.id+');">'+
                    getOnlineBullet(row.online)+
                    '<span class="link">'+(row.username)+'</span>'+
                '</li>';
            }
            ul.html(html);

            toggleView('users');
        });
    }
    function usersPrev () {
        self.user_page = Math.max(0, -1 + (self.user_page||0));
        return users();
    }
    function usersNext () {
        self.user_page = Math.min((self.user_pages || 1)-1,  (self.user_page||0) + 1);
        return users();
    }
    function profile(id) {
        api.profile(id, function (err, result) {
            if (err) {
                return error(getView(), err);
            }

            var view = getView('profile');
            var ul = $('.list', view);
            $('.info', view).html('<span>'+(result&&result.username||'')+'</span>'+getOnlineBullet(result.online));
            var html = [
                '<li><span>Email: '+result.email+'</span></li>',
                '<li><span>Messages: '+result.msgs_to_me+'</span></li>',
                '<li></li>'
            ].join('');
            ul.html(html);
            toggleView('profile');
        });
    }
    function messages(page) {
        var view = getView();
        if(typeof page === 'undefined') page = self.msg_page || 0;
        api.messages({
            page: page,
            size: 15,
            cut: 25
        }, function(err, result){
            if(err) return error(view, err);
            self.msg_pages = Math.ceil(result.count/result.size);
            var view = getView('messages');
            var start = +result.page * parseInt(result.size)+1;
            var end = Math.min(start-1 + parseInt(result.size), result.count);
            $('.info', view).text('Messages ('+start+' - '+end+' from '+result.count+')');

            //hide prev/next
            var prev_arrow = (page == 0) ? 'hide' : 'show';
            var next_arrow = (page == self.user_pages - 1) ? 'hide' : 'show';
            $('.paginator.left', view)[prev_arrow]();
            $('.paginator.right', view)[next_arrow]();

            var ul = $('.list', view);
            var html = '';
            for (var i = 0, n = result.data.length; i<n; i++) {
                var row = result.data[i];
                html +=
                '<li onclick="app.profile('+row.from_id+');">'+
                    getOnlineBullet(row.online)+
                    '<span class="link">'+(row.from)+':&nbsp;</span>'+
                    '<span>'+(row.text)+'</span>'+
                '</li>';
            }
            ul.html(html);

            toggleView('messages');
        });
    }
    function options() {
        api.options(function (err, result) {
            if (err) {
                return error(getView(), err);
            }
            self.userdata = result;
            var view = getView('options');
            var ul = $('.list', view);
            $('.info', view).html('<span>My Profile</span>');
            var html = [
                '<li><span>Username: '+result.username+'</span></li>',
                '<li><span>Email: '+result.email+'</span></li>',
                '<li></li>'
            ].join('');
            ul.html(html);
            toggleView('options');
        });
    }
    function updateFieldsPage() {
        if (!self.userdata) return error(getView(), 'Something wrong happened')
        var view = getView('updateFields');
        $('[name=username]', view).val(self.userdata.username);
        $('[name=email]', view).val(self.userdata.email);
        toggleView('updateFields');
    }
    function updatePassPage() {
        var view = getView();
        if (!self.userdata) return error(view, 'Something wrong happened')
        getView('updatePass').find('input').val('');
        toggleView('updatePass');
    }

    function back() {
        var last = cookie.get(cook.backView, 'main');
        toggleView(last)
    }
    // var exports = [
    //     'init', 'toggleView',
    //     'greetPage', 'loginPage', 'registerPage', 'main', 'options',
    //     'updateFieldsPage', 'updatePassPage', 'users', 'messages',
    //     'login', 'profile', 'logout', 'register', 'updateFields', 'updatePass',
    // ];
    // for (var i = exports.length - 1; i >= 0; i--) {
    //     self[exports[i]] = eval(exports[i]);
    // };

    self.init = init;
    self.toggleView = toggleView;

    self.greetPage = greetPage;
    self.loginPage = loginPage;
    self.registerPage = registerPage;
    self.main = main;
    self.options = options;
    self.updateFieldsPage = updateFieldsPage;
    self.updatePassPage = updatePassPage;
    self.users = users;
    self.usersNext = usersNext;
    self.usersPrev = usersPrev;
    self.messages = messages;

    self.login = login;
    self.profile = profile;
    self.logout = logout;
    self.register = register;
    self.updateFields = updateFields;
    self.updatePass = updatePass;

    self.back = back;

    self.error = error;
    self.getView = getView;
}

wait(
    function(){return typeof $ !== 'undefined';},
    function(){
        console.info('api loaded...');
        var api = new API('http://localhost:8000/messenger/');
        var app = new App(api);
        window.app = app;
        window.api = api;
        app.init();
});
