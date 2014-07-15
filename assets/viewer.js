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
  var extent = [-1, 1000]; // default values to prevent crazy pixels ruining min/max values
  
  // Get the DOM element
  var el = $('#wicked-science-visualization').get(0);
  
  var callbacks = {
    onclick: overlayCallback
  };
  // Initialize the WebFITS context with a viewer of size width
  if (webfits === null) {
    webfits = new astro.WebFITS(el,width, height);
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
  if (response.band == 'Y')
    $('#image_name').html(release + ", " + expname + ', CCD ' + ccd + ", <span class='badge badge-important'>" + response.band + "-band</span>");
  else
    $('#image_name').html(release + ", " + expname + ', CCD ' + ccd + ", " + response.band + "-band");
  var newurl=window.location.pathname + '?release=' + release + '&expname=' + expname + '&ccd=' + ccd;
  // update browser url field
  window.history.replaceState(null, "Title", newurl);
  $('#share-url').val('http://' + window.location.host + newurl);
  $('#desdm-url').val('https://desar2.cosmology.illinois.edu/DESFiles/desardata/OPS/red/' + response.runname + '/red/' + expname + '/' + expname + '_' + ccd +'.fits.fz');
  $('#fov-url').html('getImage.php?type=fov&release=' + release + "&runname=" + response.runname + "&expname=" + expname);
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

function clearUI() {
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

function sendResponse(image_props) {
  if (image_props === undefined)
    image_props = {'release': release};
  image_props['fileid'] = fileid;
  image_props['problems'] = marks;
  getNextImage(image_props);
  
  // update image counters
  if (checkSessionCookie()) {
    var number = parseInt($('#total-files').html());
    number += 1;
    $('#total-files').html(number);
  }
}

function getNextImage(image_props) {
  // show spinner
  $('#loading').show();
  $('#wicked-science-visualization').find('canvas').fadeTo(400, 0.05);

  // send to DB
  var params = {'release': release};
  if (image_props !== undefined) {
    for (var attr in image_props)
      params[attr] = image_props[attr];
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
  clearUI();
}

function getMyData() {
  $.get('mydata.php', {'release': release}, function(response) {
    // initial call: create typeahead
    if ($('#total_files').html() == "")
      $('#problem-text').typeahead({source: response.problems});
    else // just update array afterwards
      $('#problem-text').typeahead().data('typeahead').source = response.problems;
    
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

function setChipLayout() {
  var WIDTH = 530., HEIGHT = 479.;
  var GAP = [1.25, 2.];
  var PAD = [14., 12.];
  var ROWS = [[3,2,1], // chips per row
              [7,6,5,4], 
              [12,11,10,9,8], 
              [18,17,16,15,14,13],
              [24,23,22,21,20,19],
              [31,30,29,28,27,26,25],
              [38,37,36,35,34,33,32],
              [44,43,42,41,40,39],
              [50,49,48,47,46,45],
              [55,54,53,52,51],
              [59,58,57,56],
              [62,61,60]];
  var NROWS = ROWS.length;
  var NCCDS = [3, 4, 5, 6, 6, 7, 7, 6, 6, 5, 4, 3];
  var i, j, xpad, ypad;
  if (release == "SVA1") {
    HEIGHT = 454.;
    GAP = [0.5,0.5];
    PAD = [1.,0.];
    ROWS.reverse();
    for (i = 0; i < NROWS; i++)
      ROWS[i].reverse();
  }
  var CCD_SIZE = [(WIDTH-6*GAP[0]-2*PAD[0])/7, (HEIGHT-11*GAP[1]-2*PAD[1])/NROWS];
  
  var html = "<style> .ccdshape { width: " + Math.round(CCD_SIZE[0]-2) + "px; height: " + Math.round(CCD_SIZE[1]-2) + "px; }</style>";
  var xmin, ymax;
  for (i=0; i < NROWS; i++) {
    var ccds = ROWS[i];
    for (j=0; j < ccds.length; j++) {
      xmin = Math.round(PAD[0] + j*(GAP[0] + CCD_SIZE[0]) + (WIDTH - 2*PAD[0] - ccds.length*(CCD_SIZE[0]+GAP[0]))/2);
      ymax = Math.round((PAD[1] + i*(GAP[1] + CCD_SIZE[1])));
      html += "<div class='ccdshape' style='left:" + xmin + "px; top:" + ymax + "px' title='CCD " + ccds[j] + "'></div>";
    }
  }
  $('#ccdmap').html(html);
  // Connect the ccd outline in FoV image to image loading
  $('#ccdmap').children('.ccdshape').on('click', function(evt) {
    var ccdnum = evt.target.title.split(" ").pop();
    var new_image = {'release': release, 'expname': expname, 'ccd': ccdnum};
    if (marks.length)
      sendResponse(new_image);
    else
      getNextImage(new_image);
    $('#fov-modal').modal('hide');
  });
}
