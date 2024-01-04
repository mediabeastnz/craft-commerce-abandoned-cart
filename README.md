<p align="center"><a href="https://plugins.craftcms.com/abandoned-cart" target="_blank"><img src="./src/icon.svg" width="100" height="100" alt="Abandoned Cart for Craft Commerce"></a></p>

# Abandoned Cart for Craft Commerce

## Requirements

This plugin requires Craft Commerce 3.0.0 or later.

## Abandoned Cart Overview

Abandoned Cart for Craft Commerce is a plugin that provides the ability to send multiple email
reminders to customers that have abandoned their carts. This is a proven way to increase what would normally be lost revenue.

Abandoned Carts will send a maximum of two emails, these emails can be configured to be sent after a certain amount of hours.

A responsive email template is included but can be overwritten with your own if preferred.

The email the customer receives includes a link that restores their cart. 
The plugin also uses this to detect clicks. Knowing if customers are opening/clicking emails is a great way to increase conversion.

Discounts can also be included in emails. Simply create a discount code in Craft Commerce and enter that code in
Abandoned Carts settings. I recommend setting one code per email address to prevent customers taking advantage.

All abandoned cart emails are created as jobs and placed in Craft's queue, this should provide a great platform
for high performing stores.

## The settings

Any cart will be marked as abandoned 1 hour after no activity. This is important to remember when adjusting the delay settings for the reminder
emails. For example by default the 1st email will be sent 2 hours after the cart was last interacted with. Just remember to allow for that 1 hour delay upfront.

## Test Mode
When test mode is enabled, you can click the "Find Abandoned Carts" button on the dashboard to bypass the queue and force emails to be sent instantly.
This is only recommended while in development as if you had many carts that meet your criteria then the system would try and send all those emails which is quite intensive on server resources.
To test I normally add an order to my cart, edit the dates of that order in the `commerce_orders` table to be in the past and then click the "Find abandoned Carts" button.

## Configuring Abandoned Cart

Ideally you'll want to setup a couple server cron jobs to trigger both the finding of abandoned carts and the triggering of Crafts queue.
However if you don't for some reason have access to server cron jobs a URL can be used to manually trigger the above. 
This allows you to use something like cron-job.org.

### Manual trigger
To manually trigger the jobs to find carts and process the queue you'll need to hit this URL `https://[www.website.com]/actions/abandoned-cart/base/find-carts&passkey={{passKey}}`. The `keyPass` by default is generated for you in the settings area but feel free to set this to whatever you like.

A good way to find this URL is to navigate to the Dashboard and in the top right there is a button labelled "Find Abandoned Carts".

Once you have the URL you want to fire this job every 5 minutes or what ever suits you requirements.

### Server based cron jobs
Once you've set your preferred email delay times all that's left to do is set up a few cron
jobs to run every few minutes (adjust this to suit your sites needs). The first cron job will look for new
abandoned carts and schedule emails.

```sh
*/5 * * * * php craft abandoned-cart/reminders/schedule-emails
```

Once abandoned cart emails have been put in the queue you'll need to tell Craft to process the queue.
You can do this by setting up a second cron job which processes any jobs in the queue.

```sh
*/1 * * * *  php craft queue/run
```

## Customize mail message trough events
The mailer implements a `BeforeMailSend` event where you can cancel or change the message that is sent by the plug-in. Usage:
```php
use yii\base\Event;
use mediabeastnz\abandonedcart\events\BeforeMailSend;
use mediabeastnz\abandonedcart\services\Carts;
Event::on(
    Carts::class,
    Carts::EVENT_BEFORE_MAIL_SEND,
    function (BeforeMailSend $event) {
        // implement
    }
);
```

## Abandoned Cart Roadmap

Some things to look forward to:

* Dashboard widget
* Better language support
* Clean up task to remove carts after specific time
* Improved dashboard