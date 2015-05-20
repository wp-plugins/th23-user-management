jQuery(document).ready(function($) {

	// password strength meter
	$('#pass1, #pass2').focus(function() {
		$('#pass-strength-indicator').show();
	});
	$('#pass1, #pass2').blur(function() {
		if($('#pass1').val() == '' && $('#pass2').val() == '') {
			$('#pass-strength-indicator').hide();
		}
	});
	$('#reset').click(function() {
		$('#pass-strength-indicator').hide();
	});
	$('#pass1, #pass2').keyup(function() {
		$('#pass-strength-result').removeClass('short bad good strong');
		if($('#pass1').val() == '') {
			$('#pass-strength-result').val(tumJSlocalPW['n/a']);
			return;
		}
		strength = passwordStrength($('#pass1').val(), $('#user_login').val(), $('#pass2').val());
		switch(strength) {
			case 2:
				$('#pass-strength-result').addClass('bad').val(pwsL10n['bad']);
				break;
			case 3:
				$('#pass-strength-result').addClass('good').val(pwsL10n['good']);
				break;
			case 4:
				$('#pass-strength-result').addClass('strong').val(pwsL10n['strong']);
				break;
			case 5:
				$('#pass-strength-result').addClass('short').val(pwsL10n['mismatch']);
				break;
			default:
				$('#pass-strength-result').addClass('short').val(pwsL10n['short']);
		}
	});

});
