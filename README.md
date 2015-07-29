# Introduction
This [Drupal 7](https://www.drupal.org/) module is build on top of the [Migrate](https://www.drupal.org/project/migrate) module's [Wizard UI](https://www.drupal.org/node/2002064) introduced in version 7.x-2.6.  It allows a site admin to dynamically create a migration task to import content (and optionally IA) from [GatherContent](https://gathercontent.com/) into a Drupal 7 CMS.

GatherContent has the concept of templates, which roughly translate into Drupal content types.  The service also has basic fields that can be added to templates.  New pages are added into the site IA and can be moved around to create the desired content hierarchy.

This module allows a site admin to pull a GatherContent project into Drupal, mapping templates to content types.  The Migrate module makes a best-effort attempt to translate data to the target field, but in the end it's up to the admin to make sure field mapping is correct.  Files uploaded into GatherContent are downloaded and imported into the Drupal public filesystem.  The migration wizard also provides an option to target an existing menu to import the IA into once all files and pages have been created in Drupal.

# Usage
Install and enable as you would any other Drupal module.  Keep in mind that Migrate 7.x-2.6 is the minimum required version, else you won't see a migration wizard.

Once installed, you should see a local task under admin/content/migrate labeled **Import from GatherContent**.  This is the entry point for the wizard.  Steps in the wizard are:

1. Credentials
2. Project selection
3. Template mapping
4. Field mapping
5. Target menu
6. Review

Once completed, a new migration task will be created that you can manage as you would a custom migration task.
