# Sector Migration Scripts 2019

In the end of 2019 we needed to change some of our contact segments and corresponding communities.
These scripts are used to migrate our contactsegments (org.civicoop.contactsegment) and communities to the new one.

The extension is licensed under [GPL-3.0](LICENSE).

## Requirements

* PHP 5.6 (Tested, might work with other versions, but not tested)
* CiviCRM 4.4.8 (Tested, might work with other versions, but not tested)
* org.civicoop.contactsegment 1.19 (Tested, might work with other versions, but not tested)

## Installation / Configuration

These scripts are build to be used once for migration.

We put all contents in the root of drupal directory
Folder _sectormigration should be located in /path/to/your/drupal/
Community scripts should be located in /path/to/your/drupal/
So a git clone of this repository in root of drupal-directory: /path/to/your/drupal/ should be enough

## Usage

After the scripts are placed in the right directory, they should be runned as php-script using the URL of your site.
For example:

https://website.local/_sectormigration/1_sector_building_migration.php

https://website.local/7_community_building_migration.php

It depends on how many records needs to be processed, but in our case it took only a couple of seconds per script.