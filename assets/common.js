var release = null;

function setRelease(release_) {
  if (release_ != null) {
    // check if release is still present in the list
    var found = false;
    $('#release-switch').find('.release-button').each(function() {
      if (release_ == this.id.split("-").pop()) {
	found = true;
      }
    });
    if (found)
      release = release_;
    else
      release = null;
  }
  
  if (release == null) {
       var node = $('#release-switch').find('.release-button').last()[0];
    release = node.id.split("-").pop();
  }

  $.cookie('default-release', release, {expires: 365});
  $('.release_name').html(release);
  $('a[class*="release-button"]').on('click', function(evt) {
    setRelease(this.id.split("-").pop());
    window.location.reload(true);
  });
}

function checkSessionCookie() {
  return ($.cookie('sid') != null);    
}

