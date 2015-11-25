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

function signUp(evt) {
  var params = $('#sign-up-form').serializeArray();
  var p = {action: 'signup'};
  var box = $('#signup_message');
  for (var i in params) {
    if (params[i].value == "") {
      $('#signup-' + params[i].name).focus();
      box.html("Please fill out all fields.");
      box.addClass('alert-error');
      box.show();
      return evt.preventDefault();
    }
    p[params[i].name] = params[i].value;
  }
  p['hash'] = hex_sha1(p.password);
  delete p.password;

  // send params to server and see whether account creation is good
  var button = $('#signup_button');
  button.button('loading');
  $.post('signup.php', p, function(data) {
    box.html(data.message);
    box.show();
    if (data.success === false) {
      box.addClass('alert-error');
      button.button('reset');
      if (button.hasClass('active'))
        button.button('toggle');
    }
    else {
      box.removeClass('alert-error');
      button.button('reset');
      if (button.hasClass('active'))
        button.button('toggle');
      setTimeout(function() {
        // force reload to get inside
        window.location.href = "viewer.html";
      }, 2500);
    }
  }
    ,'json');
  return evt.preventDefault();
}

function resetPW(evt) {
  var params = $('#reset-pw-form').serializeArray();
  // send params to server and see whether account creation is good
  var button = $('#reset_button');
  var box = $('#reset_message');
  button.button('loading');
  
  $.post('login.php', {'reset_pw':true, 'email': params[0].value }, function() {
    // show message irrespective of submission result, which is always "OK"
    box.removeClass('hide');
    button.button('reset');
    if (button.hasClass('active'))
      button.button('toggle');
    // remove the reset modal, show login modal again
    setTimeout(function() {
      $('#resetPW').modal('hide');
      box.addClass('hide');
      $('#logIn').modal('show');
    }, 2500);
  }, 'json');
  return evt.preventDefault();
}
