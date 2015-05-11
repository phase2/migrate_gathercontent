<?php
/**
 * @file
 * Migration class to support dynamic registration via
 * GatherContentMigrateWizard.
 */

/**
 * Class definition for generic, dynamic migration class for pages.
 */
class MigrateGatherContentPages extends MigrateGatherContentBase {
  protected $field_info = array();
  protected $field_instance = array();

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

    // Set up source.
    $appname = $args['appname'];
    $apikey = $args['apikey'];
    $project_id = $args['project']->id;
    $template_id = $args['template']->id;
    $this->source = new MigrateSourceGatherContent($appname, $apikey, $project_id, $template_id);

    // Set up destination.
    $this->destination = new MigrateDestinationNode($args['node_type']);

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
      MigrateDestinationNode::getKeySchema(),
      'default',
      array(
        'track_last_imported' => TRUE,
        'cache_map_lookups' => TRUE,
      )
    );

    // Add field mappings.
    foreach ($args['fields'] as $node_field => $template_field) {
      // Pull detailed target field info.
      $this->field_info[$node_field] = field_info_field($node_field);
      $this->field_instance[$node_field] = field_info_instance('node', $node_field, $args['node_type']);

      // Handle static source mapping fields with default to normal mapping.
      $field_parts = explode(':', $template_field->name);
      switch ($field_parts[0]) {
        case 'static':
          switch ($field_parts[1]) {
            case 'bool':
              $this->addFieldMapping($node_field)->defaultValue('true' == $field_parts[2]);
              break;

            case 'format':
              $this->addFieldMapping($node_field)->defaultValue($field_parts[2]);
              break;
          }
          break;

        default:
          $this->addFieldMapping($node_field, $template_field->name);
      }

      // Additional subfield settings based on mappings.
      if ('files' == $template_field->type) {
        $this->addFieldMapping($node_field . ':file_class')->defaultValue('MigrateFileGatherContent');
      }

      // Set separator value on text fields mapped to termrefs.
      if ('text' == $template_field->type && $this->field_info[$node_field] && 'taxonomy_term_reference' == $this->field_info[$node_field]['type']) {
        $mapping = $this->codedFieldMappings[$node_field];
        $mapping->separator(',');
      }
    }
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

    // Loop over destination mappings to preprocess row data.
    foreach ($this->codedFieldMappings as $dest_field => $mapping) {
      $source_field = $mapping->getSourceField();
      if (empty($source_field)) {
        continue;
      }

      // Strip tags from metadata-mapped source fields.
      if (0 === strpos($dest_field, 'metatag')) {
        $row->{$source_field} = strip_tags($row->{$source_field});
      }

      // Strip tags from data headed for textfields.
      if ('text' == $this->field_info[$dest_field]['type']) {
        $row->{$source_field} = strip_tags($row->{$source_field});
      }
    }
  }
}
