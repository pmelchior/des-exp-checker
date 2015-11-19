var release = null;

function setRelease(release_) {
  if (release_ == null) {
    var node = $('#release-switch').find('.release-button').last();
    release = node.id;
  }
  else
    release = release_;
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

