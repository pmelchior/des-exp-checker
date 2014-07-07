var release = null;

function setRelease(release_) {
    if (release_ == null) {
        release_ = "Y1A1";
    }
    release = release_;
    $.cookie('default-release', release, {expires: 365});
    $('#release-switch').find('button').each(function(){
        release_ = this.id.split("-").pop();
        if (release_ == release)
            $(this).addClass('btn-primary');
        else
            $(this).removeClass('btn-primary');
    });
}

function checkSessionCookie() {
  return ($.cookie('sid') != null);    
}

