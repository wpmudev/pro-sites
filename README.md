# Pro Sites

**INACTIVE NOTICE: This plugin is unsupported by WPMUDEV, we've published it here for those technical types who might want to fork and maintain it for their needs.**

## Translations

Translation files can be found at https://github.com/wpmudev/translations

## Create and monetize your own WordPress.com or Edublogs.org type network with premium services and paid upgrades using Pro Sites.

Pro Sites can power a network with millions of blogs – and does. We developed Pro Sites to manage Edublogs.org, one of the largest and most profitable networks in the world. It makes light work of site management, leaving you to get on with growing your business. 

![features](https://premium.wpmudev.org/wp-content/uploads/2009/01/features-583x373.png)

 Offer plans that connect with your clients.

### Offer Premium Upgrades

Charge users for access to your premium hosting services. Allow users on your network with a basic account and invite them to upgrade for ad-free blogging, expert 24/7 support and top shelf products.

 

### Charge for Your Services

Pro Sites includes all the “extras” you need to create a feature rich hosting service. Offer theme upgrades, plugin upgrades, expert support, ad-free blogging, unlimited publishing, domain mapping, increased storage and BuddyPress support.

## Pro Sites User Gallery

**Here's what other users are doing with Pro Sites. What will you build?**

![edublogs](https://premium.wpmudev.org/wp-content/uploads/2009/01/edublogs-1x-compressor.jpg)

[Edublogs](https://edublogs.org/)

![areavoices](https://premium.wpmudev.org/wp-content/uploads/2009/01/areavoices-1x-compressor.jpg)

[Areavoices](http://areavoices.com/)

### Sell Package Deals

Create tiered membership levels with products and services that cater to a variety of users. Add as many levels as you like and bundle your network’s best services.

It includes all the extras you need to build a feature-rich hosting service.

 

### Built-in Pricing Table

Auto-generate pricing and features tables and quickly layout, style and highlight everything your site has to offer. Give free users access to only your basic content and charge top dollar for things like custom domains and access to eCommerce.

![price-table](https://premium.wpmudev.org/wp-content/uploads/2009/01/price-table-583x373.png)

 Create upgrade levels that fit your clients needs.

 

![money-735x470](https://premium.wpmudev.org/wp-content/uploads/2015/05/money-735x4701-583x373.jpg)

 Packaged with everything you need to start taking payments out of the box – no expensive extensions.

### Three Reliable Payment Options

Our simple checkout process guides customers through their purchase so you don’t have to worry about last minute cart abandonment. Pro Sites provides seamless front-end checkout and includes the option to reserve a site name and domain for 48 hours.

 Choose from three payment options in 24 currencies and get paid with PayPal Express, Stripe as well as manual payments. Each gateway is rigorously tested and backed by our expert support team. Let new customers create a user account at checkout for a smooth transaction, and even set up integration with Taxamo.com to handle your EU VAT requirements. 

## Rich Statistics and Analytics

Track new users and their sites and monitor all transactions on your network with detailed graphical statistics.

*   Active Sites
*   New Signups
*   New Upgrades
*   Cancellations

*   Active Trials
*   Weekly Activity
*   Monthly Activity
*   Ratio pie charts

*   Pro Sites Level
*   Current Gateway User
*   History Graph
*   Term History Graph

Pro Sites makes setting up advanced eCommerce analytics a piece of cake. If you already use Google Analytics, simply turn on integration in Pro Sites’ settings so you can start monitoring your eCommerce information right away.

 

### Simple Site Management

Whether you’re hosting tens or even millions of blogs, site management is a snap. Quickly search sites, set what members can and can’t access, add one-time setup fees and save time with recurring subscriptions.

![bulk-upgrade-735x470](https://premium.wpmudev.org/wp-content/uploads/2015/05/bulk-upgrade-735x470-583x373.jpg)

 Offer loyal users with multiple sites on your network special bulk upgrade packages.

 

![buddypress-735x470](https://premium.wpmudev.org/wp-content/uploads/2015/05/buddypress-735x470-583x373.jpg)

 Pair with BuddyPress for incredible control over your social network hosting.

### BuddyPress Integration

Encourage users to upgrade their account for access to BuddyPress group creation and messaging. And don’t forget to let them know they can sign up, participate in groups and generally enjoy your site for free.

 

## Even More Functionality With Modules

Multisite networks can get complicated and not every site or user needs the same things. Pro Sites offers features as modules so you can turn on only the functionality you need, making your network easier to manage.

*   Advertising
*   Bulk Upgrades
*   Limit BuddyPress Features
*   Limit Publishing
*   Pay To Blog

*   Post/Page Quotas
*   Premium Plugins
*   Premium Support
*   Premium Themes
*   Upgrade Admin Menu Links

*   Pro Widget
*   Restrict XML-RPC
*   Unfilter HTML
*   Post Throttling
*   Upload Quota

 

![auto-email-735x470](https://premium.wpmudev.org/wp-content/uploads/2015/05/auto-email-735x470-583x373.jpg)

 Built-in auto-response emails help keep users connected.

### Automated Email Notifications

Email new users automatically when they sign up or offer members who cancel a special discount if they come back. Automated emails help free up your time by sending receipts and follow up communication for you.

 

### Free Trials and Coupons

Show customers the benefits of upgrading with the ability to browse and trial premium features like themes and plugins. Offering discounts can be a powerful way to increase conversion rates. Pro Sites lets you set up and manage coupons to help you secure customer loyalty.

![prosite-coupons-735x470](https://premium.wpmudev.org/wp-content/uploads/2015/05/prosite-coupons-735x470-583x373.jpg)

 Make and manage coupons.

  

### Integration With WPMU DEV Plugins

Make full use of your WPMU DEV membership and integrate Pro Sites with any of our 100+ collection of plugins. Pro Sites includes special integration with: Domain Mapping, Pretty Plugins, Multisite Theme Manager.

## Usage

### About Pro Sites

The Pro Sites plugin is designed for users to pay for additional features for their site. The idea is you offer features that make them want to sign up for a paid site rather than use a free site.  We refer to the paid blog as a 'pro site' and the free blog as a 'non-pro site' however you can change it's name on the Settings page in **Network Admin » Pro Sites » Settings**. Pro Sites is per site, not per user.  When they sign up for a single Pro subscription, the dashboard they are logged into when they subscribe is the site that is upgraded, and Pro features are only applied to that site, so if they are a member of another site they will not see Pro features on that site.

### Important notes about Pro Sites levels

When setting up and working with Pro Sites levels, there are a few things to keep in mind, as follows:

*   **Free levels** - When new sites are created, they are technically Free. You can remove this Free site functionality by enabling the **Pay to Blog** module.
*   **Downgrades** - When users downgrade a site's level, Pro Sites creates a new subscription at lower rate. Their level will drop when the next scheduled payment (lesser) comes through.
*   **Upgrades** - When a site's level is upgraded, the difference is calculated automatically by the plugin and the date of their next payment is adjusted to take into account any balance from the previous subscription. Basically, it's pro-rated by time and this is done because when upgrading you want them to immediately have access to the higher level.

**Advanced:** If you need to run Pro Sites on multiple WP installs but with the same PayPal account, you will need to setup an IPN forwarding script. This is due to limitations in the PayPal APIs regarding subscriptions and only being able to set one IPN URL in PayPal settings. Instructions for this can be [found here](http://premium.wpmudev.org/forums/topic/multiples-ipn-dynamically-setting-the-notification-url).

### To install

For help with installing plugins please see our [Plugin installation guide](https://wpmudev.com/docs/using-wordpress/installing-wordpress-plugins/). Once installed log into to your admin panel, visit **Network Admin » Plugins** and **Network Activate** the plugin.

### To Configure

You will need to take some time to configure Pro Sites correctly.

### Levels

Pro Sites lets you create unlimited levels of subscriptions. You should plan these out beforehand, deciding what you wish to give to each level. It is easy to add a new level. Just insert the name and the different prices. You can also un-check payment options that you don't want to offer, like "12 Months". 

![image](http://premium.wpmudev.org/wp-content/uploads/2011/10/addnewlevel.png)

### Settings

You can use the settings to re-brand Pro Sites, add free trials and create email notifications. 

![image](https://premium.wpmudev.org/wp-content/uploads/2009/01/settings.png)

### Enable Modules

Navigate to **Pro Sites » Modules/Gateways** to choose which modules you wish to use. These are:

*   **Advertising** - Allows you to disable ads for a Pro Site level, or give a Pro Site level the ability to disable ads on a number of other sites.
*   **Bulk Upgrades** - Allows you to sell Pro Site level upgrades in bulk packages.
*   **Limit BuddyPress Features** - Allows you to limit BuddyPress group creation and messaging to users of a Pro Site.
*   **Limit Publishing** - Allows you to only enable writing posts and/or pages for selected Pro Site levels.
*   **Pay to Blog** - Allows you to completely disable a site both front end and back until paid.
*   **Post/Page Quotas** - Allows you to limit the number of post types for selected Pro Site levels. You can use this, for example, to limit the number of [Products](http://premium.wpmudev.org/project/e-commerce) or [Wikis](http://premium.wpmudev.org/project/wordpress-wiki) a Pro site is able to create.
*   **Premium Plugins** - Allows you to create plugin packages only available to selected Pro Site levels.
*   **Premium Support** - Allows you to provide a premium direct to email support page for selected Pro Site levels.
*   **Premium Themes** - Allows you to give access to selected themes to a Pro Site level.
*   **Restrict XML-RPC** - Allows you to only enable XML-RPC and Atom Publishing for selected Pro Site levels.
*   **Unfiltered HTML** - Allows you provide the "unfiltered_html" permission to specific user types for selected Pro Site levels. This will let Pro level sites utilize a wider array of HTML content
*   **Upload Quota** - Allows you to give additional upload space to Pro Sites.

_Note:_

*   _If you add the Premium Plugins and Premium Themes they will get their own menu items._
*   _All of the other modules will appear under **Pro Sites » Settings**._
*   _Each of the modules has an individual settings box for you to tweak all of the options._

![image](http://premium.wpmudev.org/wp-content/uploads/2011/10/problogsmeny.png)

### Choose Payment Gateways

Navigate to **Pro Sites » Modules/Gateways** and choose whether you want to use Manual Payments or PayPal. 

![image](http://premium.wpmudev.org/wp-content/uploads/2011/10/gateways.png)

### Subscription Selection

With Pro Sites configured, users will be able to upgrade their site to a Pro level via the Pro Sites sidebar option in their Admin dashboard or by clicking the Pro Sites link in the Admin bar. Pro Sites displays a table of subscriptions with the site's current level highlighted for easy reference. 

![Pro Sites Subscription Table](http://premium.wpmudev.org/wp-content/uploads/2011/11/pro-sites-subscription-selection.png)
