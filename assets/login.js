var seed = null;

function sendPassword(params, button) {
    params.hash = hex_sha1(hex_sha1(params.hash) + seed);
    params['login'] = seed;
    $.post('login.php',params, function(data, status, jqXHR) {
            button.button('reset');
            button.button('toggle');
            if (data.seed !== undefined)
                seed = data.seed;
            if (data.message != "OK") {
                button.html(data.message);
                button.removeClass('btn-success');
                button.addClass('btn-danger');
            } else {
                button.removeClass('btn-danger');
                button.addClass('btn-success');
                // reload the page: now with valid session id
                $.cookie("sid", 1);
                window.location.href = 'viewer.html';
            }
    }, 'json');
}
    
function logIn(evt) {
    var button = $('#login_button');
    button.button('loading');
    var params = $('#log-in-form').serializeArray();
    var p = {};
    for (i in params) {
        p[params[i].name] = params[i].value
    }
    var message = "";
    // initiate challenge by requesting salt from server
    if (seed === null) {
        $.post('login.php',{'send_seed':true}, function(data) {
                    seed = data.seed;
                    sendPassword(p, button);
                }, 'json');
    }
    else {
        // with the salt and the password, create hashed response
        // if successfull: server sets session cookie and we change location
        sendPassword(p, button);
    }
    return evt.preventDefault();
}

function showLogIn() {
    $('#logIn').modal('show');
    $('#log-in').find('#username').focus();
}

function signUp(evt) {
    var button = $('#signup_button');
    button.button('loading');
    var params = $('#sign-up-form').serializeArray();
    var p = {action: 'signup'};
    for (i in params) {
        p[params[i].name] = params[i].value;
    }
    p['hash'] = hex_sha1(p.password);
    delete p.password;
          
    // send params to server and see whether account creation is good
    $.post('signup.php', p, function(data) {
        var box = $('#signup_message');
        box.html(data.message);
        box.removeClass('hide');
        if (data.success === false) {
            box.addClass('alert-error');
            button.button('reset');
            button.button('toggle');
        }
        else {
            box.removeClass('alert-error');
            setTimeout(function() {
            // force reload to get inside
                window.location.href = "viewer.html";
            }, 2500);
          }
      }
    ,'json');
    return evt.preventDefault();
}
