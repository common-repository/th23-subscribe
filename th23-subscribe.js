jQuery(document).ready(function($) {

	// password - show / hide hint
	$('input[name="th23_subscribe_pass1"]').focus(function() {
		$('.th23-subscribe-passhint').addClass('show');
	}).blur(function() {
		$('.th23-subscribe-passhint').removeClass('show');
	});

	// password - evaluate fields
	$('input[name^="th23_subscribe_pass"]').keyup(function() {

		var pass1 = $('input[name="th23_subscribe_pass1"]').val();
		var pass2 = $('input[name="th23_subscribe_pass2"]').val();

		// pass2 - show / hide
		if(pass1 != '' && pass2 != pass1) {
			$('.th23-subscribe-pass2').addClass('show');
		}
		else {
			$('.th23-subscribe-pass2').removeClass('show');
		}

		// pass2 - confirmation / match
		if(pass2 != '' && pass2 != pass1) {
			$('.th23-subscribe-passconfirm').html(pwsL10n['mismatch']);
			$('.th23-subscribe-pass2').addClass('pass-error');
		}
		else {
			$('.th23-subscribe-passconfirm').html('');
			$('.th23-subscribe-pass2').removeClass('pass-error');
		}

		// pass1 - strength / blacklist
		$('.th23-subscribe-pass1').removeClass('pass-error pass-bad pass-good pass-strong');
		if(pass1 == '') {
			$('.th23-subscribe-passstrength').html('');
			return;
		}
		var strength = passwordStrength(pass1, $('input[name="th23_subscribe_nickname"]').val());
		if(strength == 2) {
			$('.th23-subscribe-pass1').addClass('pass-bad');
			$('.th23-subscribe-passstrength').html(pwsL10n['bad']);
		}
		else if(strength == 3) {
			$('.th23-subscribe-pass1').addClass('pass-good');
			$('.th23-subscribe-passstrength').html(pwsL10n['good']);
		}
		else if(strength == 4) {
			$('.th23-subscribe-pass1').addClass('pass-strong');
			$('.th23-subscribe-passstrength').html(pwsL10n['strong']);
		}
		else {
			$('.th23-subscribe-pass1').addClass('pass-error');
			$('.th23-subscribe-passstrength').html(pwsL10n['short']);
		}

	});

});
