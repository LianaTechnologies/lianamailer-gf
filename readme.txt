=== LianaMailer for Gravity Forms ===
Contributors: lianatechnologies, jaakkoperoliana, timopohjanvirtaliana
Tags: newsletter, automation
Requires at least: 5.8
Tested up to: 6.6.2
Requires PHP: 7.4
Stable tag: 1.0.73
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0-standalone.html

== Description ==

LianaMailer for Gravity Forms is an integration plugin which integrates LianaMailer into [Gravity Forms](https://www.gravityforms.com/). It enables you to have newsletter opt-ins automatically exported from Gravity Forms form to LianaMailer email marketing solution.

= Collect contacts easily from your WordPress site =

LianaMailer allows you to segment contacts, collect email contact lists, and send device-optimized messages in line with your brand with just a few clicks. With this tool, you can develop your performance based on real data and comprehensive reports.

Save time and reduce manual work by using LianaMailer for Gravity Forms to collect contacts, such as newsletter and guide subscribers, from your WordPress site.

= Contribute and translate =

LianaMailer for Gravity Forms plugin is developed and supported by Liana Technologies Oy. Join our crew at GitHub or contact us using other means.

== Screenshots ==

1. Email field in form configuration
2. LianaMailer settings panel in Gravity Forms

== Frequently Asked Questions ==

= Where can I find LianaMailer documentation and user guides? =

For help setting up and configuring Liana Mailer, please refer to [LianaMailer support site](https://support.lianatech.com/hc/en-us/categories/4409288848529-LianaMailer).

= Where can I report bugs? =

Report bugs on our [GitHub repository](https://github.com/LianaTechnologies/lianamailer-gf/issues).

You can also notify us via our support channels.

= Where can I request new features, themes, and extensions? =

Request new features and extensions and vote on existing suggestions on our official customer support channels. Our product team regularly review requests and consider them valuable for product planning.

= LianaMailer for Gravity Forms is awesome! Can I contribute? =

Yes, you can! Join in on our [GitHub repository](https://github.com/LianaTechnologies/lianamailer-gf)

= Where can I find REST API documentation? =

REST API documentation of LianaMailer product is available at [our support site](https://support.lianatech.com/hc/en-us/articles/5339910408989-LianaMailer-REST-API).

== Changelog ==

= 1.0.73 2024-10-22 =
* **Fixed:** Avoid excessive REST API calls

= 1.0.72 2024-10-04 =
* **Fixed:** Errors if LianaMailer API not responding

= 1.0.71 2024-09-16 =
* **Fixed:** Don't list replaced/redirected LianaMailer sites

= 1.0.70 2024-09-16 =
* **Fixed:** Version numbering ...
* **Fixed:** Fixed logic related to welcome emails to mimic LianaMailer
* **Support:** Tested to be working with WordPress 6.6.2

= 1.0.7 2024-08-29 =
* **Fixed:** Passing 'now' instead of null (deprecated) as first param for DateTime
* **Fixed:** HTML in consent label doesn't break the page anymore
* **Support:** Tested to be working with WordPress 6.6.1

= 1.0.6 2023-03-31 =
* **Fixed:** Possibility to send multi valued inputs eg. choices into LianaMailer. Values are sent as imploded (by ", ") string.
* **Fixed:** admin_url() in use for "No properties found" error message.
* **Changed:** Apply is_connection_valid use in add_lianamailer_field().
* **Support** Support for WordPress 6.2

= 1.0.57 2022-12-15 =
* **Fixed:** LianaMailer opt-in label did not updated properly in form editor with Gravity Forms version 2.6.8.1

= 1.0.56 2022-10-11 =
* **Fixed:** Fetch mailing list properly from LianaMailer when "multiple lists" was not enabled.

= 1.0.55 2022-09-23 = Initial public release
