<?php
/**
 * @file
 * Declares our migrations.
 */

/**
 * Implements hook_migrate_api().
 */
function migrate_gathercontent_migrate_api() {
  // Required for migration registrations.
  $items['api'] = 2;

  // Migrate Wizard UI classes.
  $items['wizard classes'] = array(
    'GatherContentMigrateWizard',
  );

  return $items;
}
