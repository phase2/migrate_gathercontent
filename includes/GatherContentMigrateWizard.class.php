<?php
/**
 * @file
 * Contains the Migrate UI Wizard class for GatherContent migrations.
 */

/**
 * Defines Migrate Wizard for creating GatherContent migrations.
 */
class GatherContentMigrateWizard extends MigrateUIWizard {
  /**
   * State variables for wizard.
   */
  protected $gt = NULL;
  protected $appname = NULL;
  protected $apikey = NULL;
  protected $projects = array();
  protected $project = NULL;
  protected $templates = array();
  protected $mappings = array();
  protected $template_fields = array();
  protected $menu = NULL;
  protected $default_node_type = NULL;

  /**
   * Constructor.
  */
  public function __construct() {
    parent::__construct();
    $this->addStep(t('Credentials'), 'authForm');
    $this->addStep(t('Project selection'), 'selectProjectForm');
    $this->addStep(t('Template mapping'), 'mapTemplatesForm');
    $this->addStep(t('Field mapping'), 'mapFieldsForm');
    $this->addStep(t('Target menu'), 'selectMenuForm');
    $this->addStep(t('Review'), 'reviewForm');

    // Prepop some state variables that might be provided by settings.php.
    $this->appname = variable_get('migrate_gathercontent_appname', '');
    $this->apikey = variable_get('migrate_gathercontent_apikey', '');
    $this->project = variable_get('migrate_gathercontent_project', '');
  }

  /**
   * Provides public name for migration source.
   */
  public function getSourceName() {
    return t('GatherContent');
  }

  /**
   * Form callback for authForm.
   */
  protected function authForm(&$form_state) {
    $form = array();

    $form['credentials'] = array(
        '#tree' => FALSE,
    );
    // Use fieldset so we can flag child fields on auth error.
    $credentials = &$form['credentials'];
    $credentials['appname'] = array(
      '#type' => 'textfield',
      '#title' => t('Application short name'),
      '#field_prefix' => 'https://',
      '#field_suffix' => '.gathercontent.com',
      '#required' => TRUE,
      '#default_value' => isset($form_state['values']['appname']) ? $form_state['values']['appname'] : $this->appname,
    );
    $credentials['apikey'] = array(
      '#type' => 'textfield',
      '#title' => t('API key'),
      '#required' => TRUE,
      '#default_value' => isset($form_state['values']['apikey']) ? $form_state['values']['apikey'] : $this->apikey,
    );

    return $form;
  }

  /**
   * Validate callback for authForm.
   */
  protected function authFormValidate(&$form_state) {
    // Test credentials by seeing if we can retrieve user info.
    $values = $form_state['values'];
    $this->gt = new GatherContentAPI($values['appname'], $values['apikey']);
    $user = $this->gt->get_me();
    if (!$user) {
      form_set_error('credentials', t('Unable to access GatherContent using the provided credentials.  Please check them and try again.'));
    }
    else {
      $this->appname = $values['appname'];
      $this->apikey = $values['apikey'];
    }
  }

  /**
   * Form callback for selectProjectForm.
   */
  protected function selectProjectForm(&$form_state) {
    $form = array();
    $values = $form_state['values'];

    // Retrieve list of projects from GatherContent.
    $this->projects = $this->gt->get_projects();
    if (!empty($this->projects)) {
      $options = array();
      foreach ($this->projects as $project) {
        $options[$project->id] = $project->name;
      }

      $form['project'] = array(
        '#type' => 'select',
        '#title' => t('Select project to import from'),
        '#options' => $options,
        '#default_value' => isset($values['project']) ? $values['project'] : NULL,
        '#required' => TRUE,
      );
    }
    else {
      $form['project'] = array(
        '#type' => 'value',
        '#default_value' => NULL,
      );
      $form['help'] = array(
        '#prefix' => '<p>',
        '#suffix' => '</p>',
        '#markup' => 'No projects are defined for this account.'
      );
    }

    return $form;
  }

  /**
   * Validate callback for selectProjectForm.
   */
  protected function selectProjectFormValidate(&$form_state) {
    $project_id = $form_state['values']['project'];
    if ($project_id && !empty($this->projects[$project_id])) {
      // Reset all mappings if the project changed.
      if ($this->project && $this->project->id != $project_id) {
        $this->mappings = array();
      }
      $this->project = $this->projects[$project_id];

      // Set group name and title.
      $this->groupName = 'gathercontent_' . $this->project->id . '_';
      $this->groupTitle = 'GatherContent: ' . $this->project->name;
    }
    else {
      $this->project = NULL;
      form_set_error('project', t('No project selected!'));
    }
  }

  /**
   * Form callback for mapTemplatesForm.
   */
  protected function mapTemplatesForm(&$form_state) {
    $form = array();
    $values = $form_state['values'];

    // Get GatherContent template list for this project.
    $this->templates = $this->gt->get_templates_by_project($this->project->id);
    $templates = array();
    if (!empty($this->templates)) {
      foreach ($this->templates as $template) {
        $templates[$template->id] = $template->name;
      }

      // Pull all Drupal content types.
      $node_types = node_type_get_names();
      $form['mappings'] = array(
          '#tree' => TRUE,
      );
      $mappings = &$form['mappings'];

      // Generate form fields for mappings.
      foreach ($templates as $template_id => $template_name) {
        $mappings[$template_id] = array(
          '#title' => $template_name,
          '#type' => 'select',
          '#options' => $node_types,
          '#default_value' => isset($values['mappings'][$template_id]) ? $values['mappings'][$template_id] : NULL,
          '#required' => FALSE,
          '#empty_option' => '- None -',
          '#empty_value' => NULL,
        );
      }

    }
    else {
      $form['mappings'] = array(
        '#type' => 'value',
        '#default_value' => NULL,
      );
      $form['help'] = array(
        '#prefix' => '<p>',
        '#suffix' => '</p>',
        '#markup' => 'No templates are defined for this project.'
      );
    }

    return $form;
  }

  /**
   * Validate callback for mapTemplatesForm.
   */
  protected function mapTemplatesFormValidate(&$form_state) {
    $mappings = $form_state['values']['mappings'];
    if ($mappings) {
      // If we have mappings, reset the state variable and repop it.
      $this->mappings = array();
      foreach ($mappings as $template_id => $node_type) {
        if ($node_type) {
          $this->mappings[$template_id]['node_type'] = $node_type;
        }
      }
    }
    else {
      form_set_error('mappings', t('No templates mapped!'));
    }
  }

  /**
   * Form callback for mapFieldsForm.
   */
  protected function mapFieldsForm(&$form_state) {
    $form = array();
    $values = $form_state['values'];
    $node_types = node_type_get_names();
    $gt_source_statics = array();

    // Pull available text formats and add to statics.
    $formats = filter_formats();
    foreach ($formats as $key => $format) {
      $name = 'static:format:' . $key;
      $gt_source_statics[$name] = (object) array (
        'name' => $name,
        'label' => t('Text format: @format', array('@format' => $format->name)),
        'type' => 'text',
        'mapping_label' => t('Text format: @format', array('@format' => $format->name)),
      );
    }

    // Set constants.
    $gt_source_statics += array(
      'static:bool:true' => (object) array(
        'name' => 'static:bool:true',
        'label' => t('TRUE'),
        'type' => 'text',
        'mapping_label' => t('TRUE'),
      ),
      'static:bool:false' => (object) array(
        'name' => 'static:bool:false',
        'label' => t('FALSE'),
        'type' => 'text',
        'mapping_label' => t('FALSE'),
      ),
    );

    // Set page meta fields from GatherContent.
    $gt_page_metafields = array(
      'id' => (object) array(
        'name' => 'id',
        'label' => t('Page ID'),
        'type' => 'text',
        'mapping_label' => t('Page ID'),
      ),
      'name' => (object) array(
        'name' => 'name',
        'label' => t('Name'),
        'type' => 'text',
        'mapping_label' => t('Name'),
      ),
      'project_id' => (object) array(
        'name' => 'project_id',
        'label' => t('Project ID'),
        'type' => 'text',
        'mapping_label' => t('Project ID'),
      ),
      'parent_id' => (object) array(
        'name' => 'parent_id',
        'label' => t('Parent ID'),
        'type' => 'text',
        'mapping_label' => t('Parent ID'),
      ),
      'type' => (object) array(
        'name' => 'type',
        'label' => t('Type'),
        'type' => 'text',
        'mapping_label' => t('Type'),
      ),
      'position' => (object) array(
        'name' => 'position',
        'label' => t('Position'),
        'type' => 'text',
        'mapping_label' => t('Position'),
      ),
      'custom_state' => (object) array(
        'name' => 'custom_state',
        'label' => t('Custom state'),
        'type' => 'text',
        'mapping_label' => t('Custom state'),
      ),
      'created_at' => (object) array(
        'name' => 'created_at',
        'label' => t('Created at'),
        'type' => 'text',
        'mapping_label' => t('Created at'),
      ),
      'updated_at' => (object) array(
        'name' => 'updated_at',
        'label' => t('Updated at'),
        'type' => 'text',
        'mapping_label' => t('Updated at'),
      ),
      'due_date' => (object) array(
        'name' => 'due_date',
        'label' => t('Due date'),
        'type' => 'text',
        'mapping_label' => t('Due date'),
      ),
      'template_id' => (object) array(
        'name' => 'template_id',
        'label' => t('Template ID'),
        'type' => 'text',
        'mapping_label' => t('Template ID'),
      ),
    );
    $this->template_fields = $gt_page_metafields + $gt_source_statics;

    // Check for template mappings, and iterate over if they exist.
    if (!empty($this->mappings)) {
      foreach ($this->mappings as $template_id => $mapping) {
        $node_type = $mapping['node_type'];
        $template = $this->templates[$template_id];
        $template_fields = array();
        $form[$template_id] = array(
          '#type' => 'fieldset',
          '#title' => t('Map %template to %type', array('%type' => $node_types[$node_type], '%template' => $template->name)),
          '#description' => t('Map fields from the GatherContent template to the Drupal node type'),
          '#collapsed' => TRUE,
          '#collapsible' => TRUE,
          '#tree' => TRUE,
        );
        $fieldset = &$form[$template_id];

        // Assemble field list from node.
        $node_fields = $this->get_node_fields($node_type);

        // Assemble field list from template w default for page name meta and static values.
        foreach ($gt_source_statics as $field) {
          $template_fields[t('Static values')][$field->name] = $field->label;
        }
        foreach ($gt_page_metafields as $field) {
          $template_fields[t('Page metadata')][$field->name] = $field->label;
        }

        foreach ($template->config as $tab) {
          foreach ($tab->elements as $element) {
            if ($element->type != 'section') {
              $element->mapping_label = $tab->label . ': ' . $element->label;
              $template_fields[$tab->label][$element->name] = $element->label;
              $this->template_fields[$element->name] = $element;
            }
          }
        }

        // Generate each Drupal <> GatherContent field mapping form element.
        foreach ($node_fields as $field_name => $field_label) {
          $fieldset[$field_name] = array(
            '#type' => 'select',
            '#title' => $field_label,
            '#options' => $template_fields,
            '#default_value' => isset($values[$template_id][$field_name]) ? $values[$template_id][$field_name] : NULL,
            '#required' => FALSE,
            '#empty_option' => '- None -',
            '#empty_value' => NULL,
          );

          // Default the title field to Page Name if not already set.
          if ('title' == $field_name && !$fieldset[$field_name]['#default_value']) {
            $fieldset[$field_name]['#default_value'] = 'name';
          }

          // Override some field items if this is a subfield.
          if ($field_base = strstr($field_name, ':', TRUE)) {
            $fieldset[$field_name]['#prefix'] = '<div style="padding-left: 4em;">';
            $fieldset[$field_name]['#suffix'] = '</div>';
          }
        }
      }
    }
    else {
      $form['help'] = array(
        '#prefix' => '<p>',
        '#suffix' => '</p>',
        '#markup' => 'No templates are defined for this project.'
      );
    }

    return $form;
  }

  /**
   * Validate callback for mapFieldsForm.
   */
  protected function mapFieldsFormValidate(&$form_state) {
    $values = $form_state['values'];
    foreach ($this->mappings as $template_id => &$mapping) {
      // Reset field mappings and repop with new ones.
      $mapping['fields'] = array();
      if (!empty($values[$template_id])) {
        foreach ($values[$template_id] as $node_field => $template_field_id) {
          if (!empty($template_field_id)) {
            $mapping['fields'][$node_field] = $this->template_fields[$template_field_id];
          }
        }
      }
    }
  }

  /**
   * Form callback for selectMenuForm.
   */
  public function selectMenuForm(&$form_state) {
    $form = array();

    $menus = menu_get_menus();
    $node_types = node_type_get_names();

    $form['menu'] = array(
      '#type' => 'select',
      '#title' => t('Menu to import navigation structure into (optional)'),
      '#options' => $menus,
      '#default_value' => isset($form_state['values']['menu']) ? $form_state['values']['menu'] : '',
      '#required' => FALSE,
      '#empty_option' => '- None -',
      '#empty_value' => '',
    );

    /*
    $form['default_node_type'] = array(
      '#type' => 'select',
      '#title' => t('Default node type'),
      '#description' => t('If a target menu is selected above, the node type to create for an item in the GatherContent IA that has not been mapped to any other content type.'),
      '#options' => $node_types,
      '#default_value' => isset($form_state['values']['default_node_type']) ? $form_state['values']['default_node_type'] : '',
      '#required' => FALSE,
      '#empty_option' => '- None -',
      '#empty_value' => '',
    );
    */
    $form['default_node_type'] = array(
      '#type' => 'value',
      '#value' => NULL,
    );

    return $form;
  }

  /**
   * Validation callback for selectMenuForm.
   */
  public function selectMenuFormValidate(&$form_state) {
    $this->menu = NULL;
    $this->default_node_type = NULL;

    $values = $form_state['values'];
    if (!empty($values['menu'])) {
      $this->menu = $values['menu'];
    }
    if (!empty($values['default_node_type'])) {
      $this->default_node_type = $values['default_node_type'];
    }
  }

  /**
   * Form callback for review.
   */
  protected function reviewForm(&$form_state) {
    $form = array();
    $node_types = node_type_get_names();
    $menus = menu_get_menus();

    // Generate review items for high-level items.
    $form['review_account'] = array(
      '#type' => 'item',
      '#title' => t('Application name'),
      '#markup' => $this->appname,
    );
    $form['review_apikey'] = array(
      '#type' => 'item',
      '#title' => t('API key'),
      '#markup' => $this->apikey,
    );
    $form['review_project'] = array(
      '#type' => 'item',
      '#title' => t('Project'),
      '#markup' => $this->project->name,
    );
    if (!empty($this->menu)) {
      $form['review_menu'] = array(
        '#type' => 'item',
        '#title' => t('Menu'),
        '#markup' => $menus[$this->menu],
      );
    }
    if (!empty($this->default_node_type)) {
      $form['review_default_node_type'] = array(
        '#type' => 'item',
        '#title' => t('Default node type'),
        '#markup' => $node_types[$this->default_node_type],
      );
    }

    // Process content mapping fieldsets for review and add migrations.
    $menu_deps = array();
    foreach ($this->mappings as $template_id => $mapping) {
      $node_type = $mapping['node_type'];
      $template = $this->templates[$template_id];
      $form[$template_id] = array(
        '#type' => 'fieldset',
        '#title' => t('Map %template to %type', array('%type' => $node_types[$node_type], '%template' => $template->name)),
        '#description' => t('Field mappings from the GatherContent template to the Drupal content type'),
        '#collapsed' => TRUE,
        '#collapsible' => TRUE,
        '#tree' => TRUE,
      );
      $fieldset = &$form[$template_id];

      // Assemble field list from node.
      $node_fields = $this->get_node_fields($node_type);

      // Render table of field mappings.
      $header = array(
        'Drupal field',
        'GatherContent field',
      );
      $rows = array();
      foreach ($mapping['fields'] as $node_field => $template_field) {
        $rows[] = array(
          $node_fields[$node_field],
          $template_field->mapping_label,
        );
      }
      $fieldset['fields'] = array(
        '#type' => 'markup',
        '#markup' => theme('table', array(
          'header' => $header,
          'rows' => $rows,
          'attributes' => array(),
          'caption' => '',
          'colgroups' => array(),
          'sticky' => FALSE,
          'empty' => '',
        )),
      );

      // Now add migration.
      $migration_name = preg_replace('/[^a-z0-9_]+/', '_', strtolower($template->name));
      $menu_deps[$template->id] = $migration_name;
      $this->addMigration(
        $migration_name,
        'MigrateGatherContentPages',
        array(
          'appname' => $this->appname,
          'apikey' => $this->apikey,
          'project' => $this->project,
          'template' => $template,
          'node_type' => $node_type,
          'fields' => $mapping['fields'],
        )
      );
    }

    // Add menu migration with hard dependencies to everything else if set up.
    if ($this->menu) {
      $this->addMigration(
        'menu',
        'MigrateGatherContentMenu',
        array(
          'appname' => $this->appname,
          'apikey' => $this->apikey,
          'project' => $this->project,
          'menu' => $this->menu,
          'default_node_type' => $this->default_node_type,
          'dependencies' => array_values($menu_deps),
          'mappings' => $menu_deps,
        )
      );
    }

    return $form;
  }

  /**
   * Return list of node fields for mappings.
   */
  protected function get_node_fields($node_type) {
    $dest = new MigrateDestinationNode($node_type);
    $node_fields = $dest->fields();

    // Fix certain fields' labels.
    if (isset($node_fields['metatag_publisher']) && empty($node_fields['metatag_publisher'])) {
      $node_fields['metatag_publisher'] = t('Publisher metatag');
    }

    // Exclude some subfields that we set internally.
    $subfields_exclude = array(
        'file_class',
    );
    $field_names = array_keys($node_fields);
    foreach ($field_names as $name) {
      $parts = explode(':', $name);
      if (isset($parts[1]) && in_array($parts[1], $subfields_exclude)) {
        unset($node_fields[$name]);
      }
    }

    return $node_fields;
  }
}
