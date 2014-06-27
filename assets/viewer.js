var webfits = null;
var stretch = "arcsinh";
var spinner;
var marks = [];
var problem = null;
var fileid = null;
var expname = null;
var ccd = null;
var problem_default = null;
var has_reported_problems = false;

function addMark(prob, ctx) {
  var color = '#FFFF00';
  if (ctx === undefined)
    ctx = webfits.overlayCtx;
  else
    color = '#FFA500';
  ctx.beginPath();
  if (prob.problem[0] == "-") {
    ctx.moveTo(prob.x-28, prob.y-28);
    ctx.lineTo(prob.x+28, prob.y+28);
    ctx.moveTo(prob.x-28, prob.y+28);
    ctx.lineTo(prob.x+28, prob.y-28);
  }
  else
    ctx.arc(prob.x, prob.y, 40, 0, 2*Math.PI, true);
  ctx.lineWidth=2;
  ctx.strokeStyle=color;
  ctx.shadowColor = '#000000';
  ctx.shadowBlur = 10;
  ctx.shadowOffsetX = 6;
  ctx.shadowOffsetY = 3;
  ctx.stroke();

  ctx.font = '14px Helvetica';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillStyle = color;
  ctx.shadowColor = '#000000';
  ctx.shadowBlur = 3;
  ctx.shadowOffsetX = 2;
  ctx.shadowOffsetY = 1;
  ctx.fillText(prob.problem, prob.x, prob.y);
}

function overlayCallback(_this, opts, evt) {
  if (problem !== null) {
    // add circle around dbl-clicked location
    var rect = _this.canvas.getBoundingClientRect();
    var negative = '';
    if ($('#negative-button').hasClass('active'))
      negative = '-';
    var prob = {
      x: (evt.clientX - rect.left + 0.5), // for unknown reasons, there is a 0.5 pixel shift in rect.left/right
      y: (evt.clientY - rect.top),
      problem: negative + problem,
      detail: $('#problem-text').val() == "" ? null : $('#problem-text').val()
    };
    marks.push(prob);
    addMark(prob);
    
    // show the clear button
    $('#clear-button').show();
  }
}

function clearMarks(ctx) {
  if (ctx === undefined) {
    ctx = webfits.overlayCtx;
    marks = [];
  }
  ctx.clearRect(0,0,webfits.canvas.width, webfits.canvas.height);
}

function clearLastMark() {
  marks.pop();
  webfits.overlayCtx.clearRect(0,0,webfits.canvas.width, webfits.canvas.height);
  for (var i=0; i < marks.length; i++) {
    addMark(marks[i]);
  }
}

// Define callback to be executed after image is received from the server
function getImage(f, opts) {
  // Get image data unit
  var dataunit = f.getDataUnit(2);
  // Set options to pass to the next callback
  opts["dataunit"] = dataunit;
  opts["f"] = f;
  // Asynchronously get pixels representing the image passing a callback and options
  dataunit.getFrameAsync(0, createVisualization, opts);
}

// Define callback for when pixels have been read from file
function createVisualization(arr, opts) {
  var dataunit = opts.dataunit;
  var width = dataunit.width;
  var height = dataunit.height;
  var extent = dataunit.getExtent(arr);
  
  // Get the DOM element
  var el = $('#wicked-science-visualization').get(0);
  
  var callbacks = {
    onclick: overlayCallback
  };
  // Initialize the WebFITS context with a viewer of size width
  if (webfits === null) {
    webfits = new astro.WebFITS(el,width, height);
    // Add pan and zoom controls
    webfits.setupControls(callbacks, opts);
  }
  
  // Load array representation of image
  webfits.loadImage('exposure', arr, width, height);
  // Set the intensity range and stretch
  webfits.setExtent(extent[0], extent[1]);
  webfits.setStretch(stretch);
  
  // add weight/bad-pixel map
  var dataunit = opts.f.getDataUnit(3);
  // Set options to pass to the next callback
  opts["dataunit"] = dataunit;
  // Asynchronously get pixels representing the image passing a callback and options
  dataunit.getFrameAsync(0, addMaskLayer, opts);
}

function addMaskLayer(arr, opts) {
  webfits.loadImage('bpm', arr, 1024, 512);
  webfits.draw();
  completeVisualization(opts);
}

// to be done once all elements of webfits are in place
function completeVisualization(response) {
    // add marks if present in response
  if (response.marks !== undefined) {
    for (var i=0; i < response.marks.length; i++) {
      addMark(response.marks[i], webfits.reportCtx);
    }
    has_reported_problems = true;
  }
  // set file-dependent information
  fileid = response.fileid;
  expname = response.expname;
  ccd = response.ccd;
  release = response.release; // locally overwrite the default release to make sure it's from this file
  $('#image_name').html(expname + ', CCD ' + ccd + ", " + response.band + "-band, " + release);
  var newurl=window.location.pathname + '?release=' + release + '&expname=' + expname + '&ccd=' + ccd;
  // update browser url field
  window.history.replaceState(null, "Title", newurl);
  $('#share-url').val('http://' + window.location.host + newurl);
  $('#desdm-url').val('https://desar2.cosmology.illinois.edu/DESFiles/desardata/OPS/red/' + response.runname + '/red/' + expname + '/' + expname + '_' + ccd +'.fits.fz');
  $('#fov-url').html('https://cosmology.illinois.edu/~mjohns44/SingleEpoch/pngs/' + response.runname + '/mosaics/' + expname + '_mosaic.png');
  // after both image and mask are drawn: remove loading spinner
  $('#loading').hide();
  $('#wicked-science-visualization').find('canvas').fadeTo(200, 1);
}

function setNextImage(response) {
  if (response.error === undefined) { 
    var f = new astro.FITS.File(response.name, getImage, response);
  }
  else {
    $('#message_header').html(response.error);
    $('#message_text').html(response.message);
    $('#message_details').html(response.description);
    $('#message-modal').modal('show');
    $('#loading').hide();
  }
}

function userClass(uc) {
  // frequent users: color badge acording to # of focal planes done
  switch (uc) {
    case 1: return {class: 1, style: 'badge-success', title: 'Rookie'}; break;
    case 2: return {class: 2, style: 'badge-warning', title: 'Frequent checker'}; break;
    case 3: return {class: 3, style: 'badge-important', title: 'Top performer'}; break;
    case 4: return {class: 4, style: 'badge-info', title: 'Veteran'}; break;
    case 5: return {class: 5, style: 'badge-inverse', title: 'Chief inspector'}; break;
    default: return {class: 0}; break;
  }
}

function showCongrats(congrats) {
  $('#congrats_text').html(congrats.text);
  if (congrats.detail !== undefined) {
    $('#congrats_details').html(congrats.detail);
    if (congrats.userclass !== undefined) {
      var uc = userClass(congrats.userclass);
      $('#status_class').addClass(uc.style);
      $('#status_class').html(uc.title);
      $('#userrank').removeClass();
      $('#userrank').addClass("badge");
      $('#userrank').addClass(uc.style); // set the badge color
    }
  }
  else
    $('#congrats_details').html();
  $('#congrats-modal').modal('show');
}

function sendResponse(new_image) {
  // show spinner
  $('#loading').show();
  $('#wicked-science-visualization').find('canvas').fadeTo(400, 0.05);
  // update counters
  var number = parseInt($('#total-files').html());
  number += 1;
  $('#total-files').html(number);
  
  // send to DB
  var params = {'fileid': fileid, 'problems': marks, 'release': release};
  if (new_image !== undefined) {
    for (var attr in new_image)
      params[attr] = new_image[attr];
  }
  $.post('db.php', params,
    function(response) {
      if (response.congrats !== undefined)
        showCongrats(response.congrats);
      setNextImage(response);
    }, 'json')
    .fail(function() {
      alert('Failure when saving response');
  });
  
  // clear UI
  $('#mark-buttons').hide();
  $('#clear-button').hide();
  $('#problem-text').val('');
  $('#problem-name').html(problem_default);
  if (marks.length)
    clearMarks();
  if (has_reported_problems) {
    clearMarks(webfits.reportCtx);
    has_reported_problems = false;
  }
  problem = null;
}

function getMyData() {
  $.get('mydata.php', function(response) {
    // check if server has just invalidated session
    if (!checkSessionCookie())
      kickOut();
    
    $('#username').html(response.username);
    $('#userrank').html("#"+response.rank);
    $('#user-rank').html("#"+response.rank);
    var uc = userClass(response.userclass);
    if (uc.class > 0)
      $('#userrank').addClass(uc.style);
    $('#total-files').html(response.total_files);
    $('#flagged-files').html(response.flagged_files);
    $('#user-menu').removeClass('hide');
    
    var rankDetails = "";
    if (uc.class > 0) 
      rankDetails += "You have the rank of <span class='badge " + uc.style + "'>" + uc.title + "</span>.<br />";
    if (uc.class < 5) {
      var next_uc = userClass(uc.class + 1);
      rankDetails += "You need another <strong>" + response.missingfiles + "</strong> images to reach the rank of <span class='badge " + next_uc.style + "'>" + next_uc.title + "</span>.";
    }
    $('#user_rank_details').html(rankDetails);

  }, 'json');
}

function closeProblemModal() {
  $('#problem-modal').modal('hide');
  $('#problem-name').html(problem_default);
  problem = null;
}
