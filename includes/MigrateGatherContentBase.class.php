<?php
/**
 * @file
 * Contains base class for GatherContent migrations.
 */

/**
 * Class definition for MigrateGatherContentBase.
 */
class MigrateGatherContentBase extends Migration {
  var $dest_ids = array();

  /**
   * Simple initialization.
   *
   * @param array $args
   *  Arguments used to dynamically create a migration, contains:
   *   @param string appname
   *    The short app name from GatherContent.
   *   @param string apikey
   *    The API key from GatherContent.
   *   @param object project
   *    The project to load pages and templates from.
   *   @param object template
   *    The template to filter pages on.
   *   @param string node_type
   *    Drupal content type to migrate into.
   *   @param array fields
   *    The field mappings to add.
   */
  public function __construct($args) {
    parent::__construct($args);
  }

  /**
   * Provide one-stop-shop for getDestID
   */
  public function getDestID($source_id, $migration) {
    if (!isset($this->dest_ids[$migration][$source_id])) {
      $migration = md5($migration);
      $dest_id = db_query("SELECT destid1 FROM {migrate_map_{$migration}} m WHERE sourceid1 = :sid", array(':sid' => $source_id))->fetchField();
      if ($dest_id) {
        $this->dest_ids[$migration][$source_id] = $dest_id;
      }
    }
    return isset($this->dest_ids[$migration][$source_id]) ? $this->dest_ids[$migration][$source_id] : FALSE;
  }

  /**
   * Preprocess the entity prior to saving it.
   */
  public function prepare($entity, $row) {
    /*
     * If workbench_moderation is enabled, add correct flags if node status
     * is set to published.
     * https://www.drupal.org/node/1452016#comment-8503687
     */
    if (module_exists('workbench_moderation') && isset($entity->status)) {
      $entity->revision = TRUE;
      $entity->is_new = !(isset($entity->nid) && ($entity->nid));
      $entity->workbench_moderation_state_current = 'published';
      $entity->workbench_moderation_state_new = 'published';
    }
  }
}