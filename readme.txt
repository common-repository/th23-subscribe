=== th23 Subscribe ===
Contributors: th23
Donate link: http://th23.net/th23-subscribe
Tags: subscribe, subscription, subscriber, notification, updates, mail, e-mail, user, visitor, registration, comment, comments, new posts
Requires at least: 4.2
Tested up to: 5.4
Stable tag: 3.2.0
Requires PHP: 5.6.32
License: GPLv2 only
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Keep your users informed: Offer them to subscribe for notifications via mail upon updates (new posts or comments). Precious user data remain on your server, not need to engage 3rd party providers.


== Description ==

Provide your users the option to **subscribe to new updates** and **get notified via mail**. Make your subscribers curious about new posts published as well as responses to comments made and further comments on the same post.

No need for external providers, scripts or other resources. Making **GDPR (DSGVO)** compliant usage easier and allows you keeping in control of your user and visitor data.

Basic styling included with plugin, **highly adaptable** to fit your theme.

= Plugin options =

th23 Subscribe offers you various options to make it smooth and easy for your users:

* Subscribe option via **widget**
* Checkbox upon **registration** to subscribe
* Checkbox in the **comment form** to subscribe
* Both checkboxes can be pre-selected (admin option)
* Confirmation / feedback via **overlay messages** you can style via CSS
* **Log file** to keep track of subscription actions and mails (optional)
* Notification mails sent in batches to avoid spamming / overloading your mail server
* Easy configuration via plugin settings page in the admin area

= Professional options =

Further functionality is available as [Professional extension](https://th23.net/th23-subscribe/):

* **Personalized e-mails** using the user name as an introduction
* Subscriptions by **visitors** without registration as a user
* Consent with **terms and conditions** upon subscription to support legal compliance
* **E-Mail confirmation** of new visitor subscriptions to ensure valid address
* **Upgrade path for visitors** to become a fully registered user with profile etc.
* Handling **password reset / registrations** of users previously subscribing as a visitor

= Special opportunity =

If you are **interested in trying out the Professional version** for free, write a review for the plugin and in return get a year long license including updates, please [register at my website](https://th23.net/user-management/?register) and [contact me](https://th23.net/contact/). First come, first serve - limited opportunity for the first 10 people!

= Integration with other plugins =

For a good user experience this **plugin integrates** with the following plugins:

* **th23 User Management** offering subscription management on frontend page, enhancing "Last Visit" tracking for users and visitors following a link in the notification mail, integrating into frontend registration and password (reset) - find this plugin in the [WP plugin repository](https://wordpress.org/plugins/th23-user-management/) or the [plugins website](https://th23.net/th23-user-management/) for more details and its Professional version with even more features
* **th23 Social** showing a subscription button within follow bars, manageable via th23 Social settings in the admin area - find this plugin in the [WP plugin repository](https://wordpress.org/plugins/th23-social/) or the [plugins website](https://th23.net/th23-social/) for more details and its Professional version with even more features
* **WP Better Emails** sending mails in styled HTML and plain text format - find this plugin in the [WP plugin repository](https://wordpress.org/plugins/wp-better-emails/)
* th23 Featured including new post header images in HTML mails - this plugin is currently in a beta phase (not yet published)

For seeing the plugin in action, feel free to visit the [authors website](http://th23.net/) or for seeing some styled examples his [personal website](https://thorstenhartmann.de/) and [travel blog](https://whereverwetravel.com/).


== Installation ==

The plugin can be installed most easily through your admin panel:

1. Navigate to 'Plugins' on the left sidebar
1. Click 'Add new' button on the top
1. Type 'th23' into the search bar on the right - and hit Enter or wait a few seconds
1. Select 'th23 Subscribe' from the list show - and click 'Install'
1. Once install is completed press 'Active'

For a manual installation follow these steps:

1. Download the plugin and extract the ZIP file
1. Upload the plugin files and folders extracted to the `/wp-content/plugins/th23-susbcribe` directory on your webserver
1. Activate the plugin through the 'Plugins' screen in the WordPress admin area by clicking 'Activate'

That is it - you can now configure the plugin for users to subscribe. Simply navigate to 'Settings' and 'th23 Subscribe' on the left sidebar.

= Get and install the Professional extension =

For upgrading to the Professional extension, please follow the steps in our video tutorial:

Note: The upgrade is demonstrated with my th23 Upload plugin, but the steps are similar for th23 Subscribe!

[youtube https://www.youtube.com/watch?v=PlPJoYZMIWY]


== Frequently Asked Questions ==


= How can I see who is subscribed for what? =

Subscriptions are stored in the database and are not directly accessible via an interface.

If enabled via the plugin settings page, **a log file will be kept** with all user and mail actions. This log is kept within the plugins directory on your server (`/wp-content/plugins/th23-subscribe`) and named `log.csv` (for easier readability use eg Microsoft Excel to open it).

The file contains the following columns:
* User ID
* User login
* User mail
* Action
* Content (post/ comment ID)
* Timestamp (machine readable)
* Date/ Time (human readable)
* IP address

The file is protected from access via the browser by an htaccess rule from the public - you can simply access it via FTP, while it will be hidden from everybody else!


= How can I input field placeholder instead of labels for the visitor form? =

The plugin **provides both description options** for the input fields and adds them to the HTML output.

You can simply use CSS eg via your theme to show placeholders instead of the labels by adding the following:
`
/* widget and overlay: th23 Subscribe - form label/ placeholder */
.th23-subscribe-visitor-form label[for^="th23_subscribe_mail"],
.th23-subscribe-visitor-form label[for^="th23_subscribe_name"] {
	display: none;
}
.th23-subscribe-visitor-form input::placeholder {
	opacity: 1;
}
`


= How can I (initially) hide the name and terms field for visitors? =

This is best achieved with a combination of **added CSS and JS to your theme**, assuming that jQuery is available (WP default). By using the following example code the fields will "slide down" upon a user focusing on the e-mail field.

CSS:
`
.th23-subscribe-visitor-form .th23-subscribe-name,
.th23-subscribe-visitor-form .th23-subscribe-terms {
	display: block;
	visibility: hidden;
	opacity: 0;
	max-height: 0;
	transition: max-height .5s, visibility .3s, opacity .3s;
}
.th23-subscribe-visitor-form .th23-subscribe-name.show,
.th23-subscribe-visitor-form .th23-subscribe-terms.show {
	visibility: visible;
	opacity: 1;
	max-height: 200px;
}
`

JS:
`
$('input[name^="th23_subscribe_mail"').focus(function(){
	$(this).closest('form').find('.th23-subscribe-name, .th23-subscribe-terms').addClass('show');
});
`

In case you want to see this in action on the [authors personal website](https://thorstenhartmann.de/) and [travel blog](https://whereverwetravel.com/).


== Screenshots ==

01. Widget to subscribe for updates, ie new posts
02. Widget to subscribe for updates, visitor / not registered user (Pro extension)
03. Successful subscription indicated via overlay message
04. Option to receive notifications for responses and further comments, within comment form
05. Option to subscribe to updates upon registration, can be pre-checked by default (admin option)
06. Mail notification about new post (plain text)
07. Mail notification about new post (HTML format, Pro extension)
08. Mail notification about new post, complete overview (HTML format, Pro extension)
09. Plugin settings page in admin area
10. Widget provided for subscriptions (admin area)
11. Widget on frontend, embedded in 2017 default theme (unregistered visitor, Pro extension)
12. Widget on frontend, styled by custom theme (unregistered visitor, Pro extension)
13. Widget on frontend, styled by custom theme, initially hiding name and terms (unregistered visitor, Pro extension)
14. Widget on frontend, styled by custom theme, extended / fully visible (unregistered visitor, Pro extension)
15. Confirmation mail upon visitor subscription (plain text, Pro extension)
16. Confirmation mail upon visitor subscription (HTML format, Pro extension)
17. Checkbox within comment form to subscribe to responses and further comments
18. Subscriptions management page on frontend via th23 User Management plugin (custom theme), for registered user
19. Subscriptions management page on frontend via th23 User Management plugin (2017 default theme), for registered user


== Changelog ==

= v3.2.0 =
* [enhancement, Basic/Pro] - major update for plugin settings area, easy upload of Professional extension files via plugin settings, adding screen options, adding unit descriptions, simplified display (hide/show examples), improved error logging
* [enhancement, Basic/Pro] - remove outdated style using PNG images, moving style control to theme
* [enhancement, Basic/Pro] - optimize parameter gathering upon loading plugin
* [fix, Pro] - deletion of unconfirmed visitors not working properly
* [fix, Basic/Pro] - change deprecated widget loading approach
* [fix, Basic/Pro] - various small fixes for style, wording, etc

= v3.1.0 =
* [enhancement] switch to Google reCaptcha v2 instead of v3 due to better performance against spam
* [enhancement] add functionality to delete visitors which do not confirm their mail address after a specified time automatically
* [fix] assign comments done by a user who selected to sign up as a visitor to the newly created visitor / user ID

= v3.0.0 (first public release) =
* [enhancement] caching of content prepared for sending within notification
* [enhancement] better link validation
* [enhancement] ability to subscribe as visitor without registration (Pro)
* [enhancement] switch to new admin settings page
* [fix] various bugfixes


== Upgrade Notice ==

= v3.2.0 =
* Easier configuration and upgrades via the admin area - simplify your life

= v3.1.0 =
* Fight against spam registrations: reCaptcha v2 and automatic deletion of unconfirmed visitors

= v3.0.0 (first public release) =
* n/a
