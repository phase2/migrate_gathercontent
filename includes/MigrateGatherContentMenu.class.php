<?php
/**
 * @file
 * Contains class that migrates the menu structure from GatherContent.
 */

/**
 * Class definition for MigrateGatherContentMenu.
 */
class MigrateGatherContentMenu extends MigrateGatherContentBase {
  // Template > migration mappings.
  protected $mappings = array();
  protected $group_name = '';

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

    // Copy args to properties for later use.
    $this->mappings = $args['mappings'];
    $this->group_name = $args['group_name'];

    // Set up source.
    $appname = $args['appname'];
    $apikey = $args['apikey'];
    $project_id = $args['project']->id;
    $this->source = new MigrateSourceGatherContent($appname, $apikey, $project_id);

    // Set up destination.
    $this->destination = new MigrateDestinationMenuLinks();

    // Set source-dest ID map.
    $source_key = array(
      'id' => array('type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
      )
    );
    $this->map = new MigrateGatherContentSQLMap(
      $this->getMachineName(),
      $source_key,
      MigrateDestinationMenuLinks::getKeySchema(),
      'default',
      array(
        'track_last_imported' => TRUE,
        'cache_map_lookups' => TRUE,
      )
    );

    // Add dynamic field mappings.
    $this->addFieldMapping('menu_name')->defaultValue($args['menu']);
    $this->addFieldMapping('plid', 'parent_id')->sourceMigration($this->getMachineName());
    $this->addFieldMapping('link_title', 'name');
    $this->addFieldMapping('weight', 'position');

    // Add default/static field mappings.
    $this->addFieldMapping('link_path', 'link_path')->defaultValue('node');
    $this->addFieldMapping('router_path')->defaultValue('node/%');
    $this->addFieldMapping('module')->defaultValue('node');
    $this->addFieldMapping('external')->defaultValue('0');
    $this->addFieldMapping('expanded')->defaultValue('1');
    $this->addFieldMapping('customized')->defaultValue('0');
    $this->addFieldMapping('has_children')->defaultValue('0');
    $this->addFieldMapping('depth')->defaultValue('1');
  }

  /**
   * Preprocess source row before things get passed to the destination.
   */
  public function prepareRow($row) {
    // Always include this fragment at the beginning of every prepareRow()
    // implementation, so parent classes can ignore rows.
    if (parent::prepareRow($row) === FALSE) {
      return FALSE;
    }

    // Strip tags from name, if any.
    $row->name = strip_tags($row->name);

    // If template is given, look up source id to map to existing node.
    if ($row->template_id && isset($this->mappings[$row->template_id])) {
      $row->nid = $this->getDestID($row->id, $this->group_name . $this->mappings[$row->template_id]);
      if ($row->nid) {
        $row->link_path = 'node/' . $row->nid;
      }
    }
  }

  /**
   * Creates a stub menu link, for when a child is imported before its parent.
   * http://drupal.org/node/1403044#comment-5790748
   *
   * @param $migration
   *  The source migration
   * @return
   *  int $mlid on success
   *  FALSE on failure
   */
  protected function createStub($migration) {
    // If plid is 0, that means it has no parent, so don't create a stub.
    if (!$migration->sourceValues->parent_id) {
      return FALSE;
    }
    $menu_link = array (
      'menu_name' => $migration->sourceValues->name,
      'link_path' => 'node',
      'router_path' => 'node',
      'link_title' => t('Stub title'),
    );
    $mlid = menu_link_save($menu_link);
    return $mlid ? array($mlid) : FALSE;
  }
}
