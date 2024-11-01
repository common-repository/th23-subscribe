<?php
/*
th23 Subscribe
Professional extension - Language strings

Copyright 2012-2020, Thorsten Hartmann (th23)
http://th23.net
*/

// This file should not be executed - but only be read by the gettext parser to prepare for translations
die();

// Function to extract i18n calls from PRO file
$file = file_get_contents('th23-subscribe-pro.php');
preg_match_all("/__\\(.*?'\\)|_n\\(.*?'\\)|\\/\\* translators:.*?\\*\\//s", $file, $matches);
foreach($matches[0] as $match) {
	echo $match . ";\n";
}

__('Login not allowed.', 'th23-subscribe');
/* translators: mail body, salutation in first line of mails to users - chosen user name to be parsed in */;
__('Hi %s,', 'th23-subscribe');
/* translators: star is added as per WP default for indication of a mandatory field */;
__('Email', 'th23-subscribe');
__('Email', 'th23-subscribe');
__('Name', 'th23-subscribe');
__('Name', 'th23-subscribe');
__('Terms of Usage', 'th23-subscribe');
/* translators: %s: link with/or title to sites terms & conditions, as defined by admin - star is added as per WP default for indication of a mandatory field */;
__('I accept the %s and agree with processing my data', 'th23-subscribe');
__('What?', 'th23-subscribe');
__('A captcha is a test to distinguish humans from computers.', 'th23-subscribe');
__('Why?', 'th23-subscribe');
__('Internet today fights a lot of spam and this test helps to keep this website clean.', 'th23-subscribe');
__('What?', 'th23-subscribe');
__('Why?', 'th23-subscribe');
/* translators: parses in "What? Why?" question into brackets and asociated tooltip, see strings before */;
__('Captcha (%s)', 'th23-subscribe');
__('Subscribe', 'th23-subscribe');
__('Invalid request - please use the form provided to subscribe', 'th23-subscribe');
__('Subscriptions are disabled', 'th23-subscribe');
/* translators: parses in the opening and closing tags of the logout link */;
__('Somebody is already logged in - please %slog out%s and try again', 'th23-subscribe');
__('Subscription failed', 'th23-subscribe');
__('Your subscription', 'th23-subscribe');
__('Please confirm, that you are a human', 'th23-subscribe');
__('Terms of Usage', 'th23-subscribe');
__('Your subscription', 'th23-subscribe');
/* translators: %s: title of terms & conditions, as defined by admin */;
__('Please accept the %s and agree with processing your data', 'th23-subscribe');
/* translators: string should start with an empty space, as it will become part of a sentence (see following strings) */;
_n(' within %s day', ' within %s days', (int) $this->options['delete_unconfirmed'], 'th23-subscribe');
__('Thank you', 'th23-subscribe');
/* translators: %s: validity of link provided, see above translation for " within %s day" / " within %s days" */;
__('We sent you an email - to complete your subscription, please confirm your email address by clicking the link provided in the mail%s', 'th23-subscribe');
__('Please enter your email address', 'th23-subscribe');
__('Please enter your valid email address', 'th23-subscribe');
__('Your subscription', 'th23-subscribe');
__('Subscription failed', 'th23-subscribe');
__('We are sorry, but we could not complete your subscription due to a server error. Please try again - if the error persists, contact the administrator of this site', 'th23-subscribe');
__('Already subscribed', 'th23-subscribe');
__('Your email address is already on our list for sending you a notification upon updates happening', 'th23-subscribe');
/* translators: mail title to new subscriber - blog name to be parsed in */;
__('[%s] Welcome / Your subscription', 'th23-subscribe');
/* translators: mail body (potentially after salutation), first line to new subscriber - blog name to be parsed in */;
__('Welcome to %s and thanks for your interest!', 'th23-subscribe');
/* translators: mail title to existing user upon new subscription - blog name to be parsed in */;
__('[%s] Your subscription', 'th23-subscribe');
/* translators: mail body (potentially after salutation and welcome message), upon new subscription - followed by subscription confirmation link */;
__('Just one more step and we will keep you up to date...', 'th23-subscribe');
/* translators: string should start with an empty space, as it will become part of a sentence (see following strings) */;
_n(' within %s day', ' within %s days', (int) $this->options['delete_unconfirmed'], 'th23-subscribe');
/* translators: mail body (potentially after salutation and welcome message, after main confirmation message), upon new subscription - 1: validity of link provided, see above translation for " within %s day" / " within %s days", 2: subscription confirmation link to be parsed in */;
__('Please confirm your interest and email address%1$s by visiting
%2$s', 'th23-subscribe');
__('Subscription failed', 'th23-subscribe');
__('We are sorry, but the required mail to confirm your subscription could not be sent due to a server error. Please contact the administrator of this site', 'th23-subscribe');
__('Updates', 'th23-subscribe');
__('Get notifications for new posts via mail', 'th23-subscribe');
__('Notify me upon responses and further comments', 'th23-subscribe');
/* translators: string should start with an empty space, as it will become part of a sentence (see following strings) */;
_n(' within %s day', ' within %s days', (int) $this->options['delete_unconfirmed'], 'th23-subscribe');
__('Thank you', 'th23-subscribe');
/* translators: %s: validity of link provided, see above translation for " within %s day" / " within %s days" */;
__('We sent you an email - to complete your subscription on answers and further comments, please confirm your email address by clicking the link provided in the mail%s', 'th23-subscribe');
__('Already subscribed', 'th23-subscribe');
__('Your email address is already on our list for sending you a notification upon answers and further comments', 'th23-subscribe');
__('Subscription failed', 'th23-subscribe');
__('We are sorry, but we could not complete your subscription - please contact the administrator of this site', 'th23-subscribe');
/* translators: mail title to visitor upon password reset requested - blog name to be parsed in */;
__('[%s] Password reset', 'th23-subscribe');
/* translators: %s: email address given to trigger the password reset */;
__('Someone has requested a password reset for the account linked to the email address "%s"', 'th23-subscribe');
__('To reset your password, visit the following address:');
__('If this was a mistake, just ignore this email and nothing will happen.');
__('We are sorry, but the required mail with the password reset link could not be sent due to a server error - please contact the administrator of this site', 'th23-subscribe');
__('The e-mail to reset your password could not be sent due to a server error. Please try again - if the error persists contact the administrator of this site', 'th23-subscribe');
__('The reset of your password has been initiated successfully - please check your inbox and follow the link provided', 'th23-subscribe');
/* translators: mail title to visitor upon registration attempt - blog name to be parsed in */;
__('[%s] User registration', 'th23-subscribe');
/* translators: %s: email address given to continue visitor upgrade */;
__('Someone started a user registration linked to the email address "%s"', 'th23-subscribe');
__('To continue your registration, visit the following address:', 'th23-subscribe');
__('If this was a mistake, just ignore this email and nothing will happen.');
__('Reset the password to join!', 'th23-subscribe');
__('<strong>Check your inbox</strong> and follow the confirmation link.', 'th23-subscribe');
__('Your email was already used as a visitor before. You can easily become a registered member.', 'th23-subscribe');
/* translators: intro of error messages, %s parses in the error message text */;
__('<strong>Error</strong>: %s', 'th23-subscribe');
__('Sorry, currently new users are not allowed', 'th23-subscribe');
/* translators: parses in the opening and closing tags of the logout link */;
__('Somebody is already logged in - please %slog out%s and follow the link again', 'th23-subscribe');
__('Error', 'th23-subscribe');
__('Invalid request - please use the form provided to join', 'th23-subscribe');
__('No valid visitor - please try again or contact an administrator', 'th23-subscribe');
__('Error', 'th23-subscribe');
__('Terms of Usage', 'th23-subscribe');
/* translators: %s: title of terms & conditions, as defined by admin */;
__('Please accept the %s, agree with processing your data and the usage of cookies', 'th23-subscribe');
__('Password can not be empty', 'th23-subscribe');
__('Password and confirmation do not match', 'th23-subscribe');
__('You are not human? Please solve the captcha', 'th23-subscribe');
/* translators: mail title to admin about new user pending approval - blog name to be parsed in */;
__('[%s] New user registration / Approval required', 'th23-subscribe');
/* translators: optional part of mail body to admin about new user pending approval (see following string) - 1: registration question, 2: user answer */;
__('Upon the registration question "%1$s" the user answered:
%2$s', 'th23-subscribe');
/* translators: mail body to admin about new user pending approval - 1: blog name, 2: user login, 3: user mail, 4: question upon registration and user answer (see previous string), 5: admin user management page link */;
__('A user with the following details upgraded from a being visitor on your site %1$s and is pending your approval before being able to sign in:

Username: %2$s
E-mail: %3$s

%4$sPlease visit the user management page for your actions:
%5$s', 'th23-subscribe');
__('Mail failure', 'th23-subscribe');
__('Your registration is complete, but requires approval by an administrator. Unfortunately there was an error sending the notification to the administrator - please contact the administrator', 'th23-subscribe');
__('Thank you', 'th23-subscribe');
__('Your registration is complete, but requires approval by an administrator - we will notify you via mail', 'th23-subscribe');
/* translators: mail title to admin about new user pending approval - blog name to be parsed in */;
__('[%s] Welcome', 'th23-subscribe');
__('Welcome to %1$s and thanks for joining!

Your username: %2$s

We are looking forward to your comments and feedback...', 'th23-subscribe');
__('Thank you, %s', 'th23-subscribe');
__('Thank you', 'th23-subscribe');
__('Your registration is complete and you are now logged in', 'th23-subscribe');
__('Confirmation link used could not be validated - please try again or contact an administrator', 'th23-subscribe');
__('Something went wrong - this user is not a visitor', 'th23-subscribe');
__('Error', 'th23-subscribe');
__('Name', 'th23-subscribe');
__('Name', 'th23-subscribe');
/* translators: star is added as per WP default for indication of a mandatory field */;
__('Password', 'th23-subscribe');
__('Password', 'th23-subscribe');
/* translators: star is added as per WP default for indication of a mandatory field */;
__('Confirm password', 'th23-subscribe');
__('Confirm password', 'th23-subscribe');
__('Terms of Usage', 'th23-subscribe');
/* translators: %s: link with/or title to sites terms & conditions, as defined by admin - star is added as per WP default for indication of a mandatory field */;
__('I accept the %s, agree with processing my data and the usage of cookies', 'th23-subscribe');
__('Are you human?', 'th23-subscribe');
__('Save', 'th23-subscribe');
__('Welcome', 'th23-subscribe');
__('Please complete the form below to join', 'th23-subscribe');
__('Error', 'th23-subscribe');
__('Continue reading', 'th23-subscribe');
__('- to read the full article please visit %s', 'th23-subscribe');
__('Continue reading', 'th23-subscribe');
__('- to read the full comment please visit %s', 'th23-subscribe');
__('No further notifications?', 'th23-subscribe');
__('Unsubscribe', 'th23-subscribe');
__('by visiting %s', 'th23-subscribe');
/* translators: string should start with an empty space, as it will become part of a sentence (see following strings) */;
_n(' within %s day', ' within %s days', (int) $this->options['delete_unconfirmed'], 'th23-subscribe');
__('Confirm subscription', 'th23-subscribe');
__('and your email address by visiting %s', 'th23-subscribe');
__('To continue please', 'th23-subscribe');
__('confirm', 'th23-subscribe');
__('by visiting %s', 'th23-subscribe');
__('To complete your registration please', 'th23-subscribe');
__('set your password', 'th23-subscribe');
__('by visiting %s', 'th23-subscribe');
__('Warning: Disabling this option, will delete all existing related subscriptions irreversably!', 'th23-subscribe');
__('Terms of Usage', 'th23-subscribe');
__('Important: Acceptance of terms of usage will NOT be requested for visitors commenting - consider to request this separately!', 'th23-subscribe');
__('Show / hide examples', 'th23-subscribe');
__('Example "subscribe":', 'th23-subscribe');
/* translators: %s: link with/or title to sites terms & conditions, as defined by admin */;
__('I accept the %s and agree with processing my data', 'th23-subscribe');
__('Example "upgrade":', 'th23-subscribe');
/* translators: %s: link with/or title to sites terms & conditions, as defined by admin */;
__('I accept the %s, agree with processing my data and the usage of cookies', 'th23-subscribe');
/* translators: %s: link to general options page in admin */;
__('Note: For changing title and link shown see %s', 'th23-subscribe');
__('General Settings');
/* translators: 1: "reCaptcha v2" as name of the service, 2: "Google" as provider name, 3: URL to reCaptcha sign-up */;
__('Important: %1$s is an external service by %2$s which requires <a href="%3$s" target="_blank">signing up for free keys</a> - usage will embed external scripts and transfer data to %2$s', 'th23-subscribe');
__('Number of days, after which visitors, that have not confirmed their subscription (via the link provided), will be deleted automatically - set to "0" to disable automatic deletion', 'th23-subscribe');
__('Color of "call to action" button in HTML notification mails in hex format - default is dark red "#820000", text on it is always white', 'th23-subscribe');
/* translators: %s: link to "WP Better Emails" plugin on WP.org */;
__('Note: Sending HTML formatted emails requires the %s plugin being installed', 'th23-subscribe');
__('Upload Professional extension?', 'th23-subscribe');
__('Go to plugin settings page for upload...', 'th23-subscribe');
/* translators: 1: "Professional" as name of the version, 2: "...-pro.php" as file name, 3: version number of the PRO file, 4: version number of main file, 5: link to WP update page, 6: link to "th23.net" plugin download page, 7: link to "Go to plugin settings page to upload..." page or "Upload updated Professional extension?" link */;
__('The version of the %1$s extension (%2$s, version %3$s) does not match with the overall plugin (version %4$s). Please make sure you update the overall plugin to the latest version via the <a href="%5$s">automatic update function</a> and get the latest version of the %1$s extension from %6$s. %7$s', 'th23-subscribe');
__('Error', 'th23-subscribe');
__('Legal information', 'th23-subscribe');
__('Title', 'th23-subscribe');
__('If left empty, &quot;Terms of Usage&quot; will be used', 'th23-subscribe');
__('URL', 'th23-subscribe');
__('Can be relative URL - if left empty, no link will be added', 'th23-subscribe');
__('Reference a page providing user with legally required information about terms of usage, impressum and data privacy policy', 'th23-subscribe');
/* translators: mail title to visitor after being upgrade by an administrator - blog name to be parsed in */;
__('[%s] Welcome / Your login and password', 'th23-subscribe');
/* translators: mail body to visitor after being upgrade by an administrator - 1: blog name, 2: password reset link */;
__('An administrator registered you with your mail address %1$s on %2$s.

Your login is %3$s', 'th23-subscribe');
__('Please set your password visiting the following address:', 'th23-subscribe');
__('Error', 'th23-subscribe');
__('An error occured, while sending the upgrade notification to visitor with ID: %s', 'th23-subscribe');
__('Done', 'th23-subscribe');
__('Selected visitor(s) have been upgraded', 'th23-subscribe');
__('Warning', 'th23-subscribe');
__('It is not recommended to edit visitors - changing their role or upgrading will notify them via mail!', 'th23-subscribe');
__('Delete');
__('Upgrade visitor', 'th23-subscribe');

?>
