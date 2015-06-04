jQuery(document).ready(function($){
	
	// handle toggles for options
	$('#users_can_register').click(function() {
		if($('#users_can_register').attr('checked') == 'checked') {
			$('.user-registration-settings').show();
			if($('#user_approval').attr('checked') == 'checked') {
				$('.user-approval-settings').show();
			}
		}
		else {
			$('.user-registration-settings').hide();
			$('.user-approval-settings').hide();
		}
	});
	$('#user_approval').click(function() {
		if($('#user_approval').attr('checked') == 'checked') {
			$('.user-approval-settings').show();
		}
		else {
			$('.user-approval-settings').hide();
		}
	});
	$('#captcha').click(function() {
		if($('#captcha').attr('checked') == 'checked') {
			$('.captcha-settings').show();
		}
		else {
			$('.captcha-settings').hide();
		}
	});

	// remove "disabled" before submitting - to fetch/ perserve values
	$('#th23-user-management-options-submit').click(function() {
		$('input[name="th23-user-management-options-do"]').val('submit');
		$('#th23-user-management-options :input').removeProp('disabled');
		$('#th23-user-management-options').submit();
	});

	// add approval to bulk actions on user admin screen
	$('#bulk-action-selector-top option:eq(0)').after($('<option></option>').attr('value','approve').text(tumadminJSlocal['approve']));
	$('#bulk-action-selector-bottom option:eq(0)').after($('<option></option>').attr('value','approve').text(tumadminJSlocal['approve']));

});
