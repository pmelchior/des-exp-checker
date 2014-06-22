var webfits = null;
var stretch = "arcsinh";
var spinner;
var marks = [];
var problem = null;
var fileid = null;
var problem_default = null;
var has_reported_problems = false;
// Problem Color Dictionary
var colors = {
  "Column mask"   : "#ee8c37",
  "Cosmic ray"    : "#ee8c37",
  "Cross-talk"    : "#ee8c37",
  "Edge-bleed"    : "#ee8c37",
  "Excessive mask": "#ee8c37",
  "Dark rim"      : "#b7ee70",
  "Dark halo"     : "#b7ee70",
  "Quilted sky"   : "#b7ee70",
  "Wavy sky"      : "#b7ee70",
  "Anti-bleed"    : "#b7ee70",
  "A/B jump"      : "#4569ee",
  "Fringing"      : "#4569ee",
  "Tape bump"     : "#4569ee",
  "Tree rings"    : "#4569ee",
  "Vertical jump" : "#4569ee",
  "Vertical stripes": "#4569ee",
  "Ghost"         : "#49e7ee",
  "Bright spray"  : "#49e7ee",
  "Brush strokes" : "#49e7ee",
  "Bright arc"    : "#49e7ee",
  "Satellite"     : "#ab95ee",
  "Airplane"      : "#ab95ee",
  "Guiding"       : "#eee083",
  "Shutter"       : "#eee083",
  "Readout"       : "#eee083",
  "Haze"          : "#eee083",
  "Other..."      : "#2a00ee",
  "Awesome!"      : "#ee4b00"
}
// String for generating Cross Mark on SVG
var cross_mark = "M24.778,21.419 19.276,15.917 24.777,10.415 21.949,7.585 16.447,13.087 10.945,7.585 8.117,10.415 13.618,15.917 8.116,21.419 10.946,24.248 16.447,18.746 21.948,24.248z";
function addMark(id, prob, ctx) {
  var glow = false;
  if (ctx === undefined)
      ctx = OverlayCanvas;
  else
    glow = true; //Add Glow stroke to reported problems
  
  var color = colors[prob.problem];  //get problem color
  var cross = ctx.path(cross_mark)
  var bb = cross.getBBox(true); //get bounding box
  var translation = "t"+(prob.x-2*bb.x)+","+(prob.y-2*bb.y);  //set translation string
  cross.attr("fill",color).transform(translation);
  cross.node.setAttribute('id',id);
  if(glow){
    cross.glow({color:"#e01"})
  }
  // Adding Qtip to Cross Mark
  $('#'+id).qtip({
    content: prob.problem + prob.detail,
      style: {
          classes: 'qtip-blue qtip-shadow qtip-rounded'
      }
  });

  //Adding Tooltipster to Cross Mark
  // $('#'+id).tooltipster({
  //   content: prob.problem + prob.detail,
  //   delay: 50,
  //   position: 'top'
  // });
}

function clearMarks(ctx) {
  if (ctx === undefined) {
    ctx = OverlayCanvas;
    marks = [];
  }
  ctx.clear();
}

function clearLastMark() {
  marks.pop();
  OverlayCanvas.clear();
  for (var i=0; i < marks.length; i++) {
    addMark(i+1, marks[i]);
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

  // Initialize the WebFITS context with a viewer of size width
  if (webfits === null) {
    webfits = new astro.WebFITS(el,width, height);
    // Add pan and zoom controls
    //webfits.setupControls(callbacks, opts);
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
      addMark(i+1, response.marks[i], ReportCanvas);
    }
    has_reported_problems = true;
  }
  // set file-dependent information
  fileid = response.fileid;
  $('#image_name').html(response.expname + ', CCD ' + response.ccd + ", " + response.band + "-band");
  $('#share-url').val('http://' + window.location.host + window.location.pathname + '?expname=' + response.expname + '&ccd=' + response.ccd);
  $('#desdm-url').val('https://desar2.cosmology.illinois.edu/DESFiles/desardata/OPS/red/' + response.runname + '/red/' + response.expname + '/' + response.expname + '_' + response.ccd +'.fits.fz');
  $('#fov-url').html('https://cosmology.illinois.edu/~mjohns44/SingleEpoch/pngs/' + response.runname + '/mosaics/' + response.expname + '_mosaic.png');
  // after both image and mask are drawn: remove loading spinner
  $('#loading').addClass('hide');
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
    $('#loading').addClass('hide');
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

function sendResponse() {
  // show spinner
  $('#loading').removeClass('hide');
  $('#wicked-science-visualization').find('canvas').fadeTo(400, 0.05);
  // update counters
  var number = parseInt($('#total-files').html());
  number += 1;
  $('#total-files').html(number);
  
  // post to DB
  $.post('db.php', {'fileid': fileid, 'problems': marks},
    function(data) {
      var response = $.parseJSON(data);
      if (response.congrats !== undefined)
        showCongrats(response.congrats);
      setNextImage(response);
    })
    .fail(function() {
      alert('Failure when saving response');
  });
  
  // clear UI
  $("#problem-dialogue").addClass("hide");
  $('#mark-buttons').addClass('hide');
  $('#problem-text').val('');
  $('#problem-name').html(problem_default);
  if (marks.length)
    clearMarks();
  if (has_reported_problems) {
    clearMarks(ReportCanvas);
    has_reported_problems = false;
  }
  problem = null;
}

function checkSessionCookie() {
  return ($.cookie('sid') != null);    
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