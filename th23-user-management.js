jQuery(document).ready(function($) {

	// overlay message
	var omsgYloc = 0;
	if($('#th23-user-management-omsg').css('top')) {
		omsgYloc = parseInt($('#th23-user-management-omsg').css('top').substring(0, $('#th23-user-management-omsg').css('top').indexOf('px')));
	}
	// initial positioning - if page (re-)loaded "scrolled"
	if($(document).scrollTop() > 0) {
		var offset = omsgYloc + $(document).scrollTop() + "px";
		$('#th23-user-management-omsg').animate({top: offset}, {duration: 500, queue: false});
	}
	// re-positioning upon scrolling
	$(window).scroll(function() {
		var offset = omsgYloc + $(document).scrollTop() + "px";
		$('#th23-user-management-omsg').animate({top: offset}, {duration: 500, queue: false});
	});
	// close by user click
	$('#th23-user-management-omsg-close').click(function() {
		$('#th23-user-management-omsg').fadeOut(100);
	});
	// trigger automatic fade-out
	if(parseInt(tumJSlocal['omsg_timeout']) > 0) {
		setTimeout(function() { $('#th23-user-management-omsg.success').fadeOut(1000); }, tumJSlocal['omsg_timeout']);
	}

});
