<?php
/**
 * @file
 * Contains class definition for MigrateGatherContentSQLMap.
 */

/**
 * Define class MigrateGatherContentSQLMap.
 * Override constructor to use MD5 hash of human-readable machine name.
 */
class MigrateGatherContentSQLMap extends MigrateSQLMap {
  /**
   * Constructor.
   *
   * @param string $machine_name
   *   The unique reference to the migration that we are mapping.
   * @param array $source_key
   *   The database schema for the source key.
   * @param array $destination_key
   *   The database schema for the destination key.
   * @param string $connection_key
   *   Optional - The connection used to create the mapping tables. By default
   *   this is the destination (Drupal). If it's not possible to make joins
   *   between the destination database and your source database you can specify
   *   a different connection to create the mapping tables on.
   * @param array $options
   *   Optional - Options applied to this source.
   */
  public function __construct($machine_name, array $source_key,
      array $destination_key, $connection_key = 'default', $options = array()) {
    $machine_name = md5($machine_name);
    parent::__construct($machine_name, $source_key, $destination_key, $connection_key, $options);
  }

}