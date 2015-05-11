<?php
/**
 * @file
 * Contains the class definition for MigrateSourceGatherContent.
 */

/**
 * Provides a source class for GatherContent API integration.
 */
class MigrateSourceGatherContent extends MigrateSource {
  /**
   * Variables related directly to GatherContent structure.
   */
  public $gt = NULL;
  public $project = NULL;
  public $template = NULL;

  /**
   * Variables used for managing source traversal.
   */
  public $pages = array();
  public $files = array();
  public $offset = 0;
  public $highwaterField = 'updated_at';

  /**
   * Simple initialization.
   *
   * @param string $account
   *  The account shortname used to access the API.
   * @param string $api_key
   *  The API key from GatherContent.
   * @param string $project_id
   *  The numeric project ID to load pages and templates from.
   * @param string $template_id
   *  The numeric template ID to filter pages on.
   */
  public function __construct($account, $api_key, $project_id, $template_id = NULL) {
    // Construct new GatherContent object and load project and template.
    $this->gt = new GatherContentAPI($account, $api_key);
    $this->project = $this->gt->get_project($project_id);
    $this->template = $template_id ? $this->gt->get_template($template_id) : NULL;

    // Make sure we have a valid project
    if ($this->project) {
      // Retrieve all pages in project and filter based on template ID.
      if ($pages = $this->gt->get_pages_by_project($this->project->id)) {
        if ($this->template) {
          foreach ($pages as $page) {
            if ($this->template->id == $page->template_id) {
              $this->pages[] = $page;
            }
          }
        }
        else {
          $this->pages = array_values($pages);
        }
      }

      // Retrieve all files in project and map to page ID.
      if ($files = $this->gt->get_files_by_project($this->project->id)) {
        foreach ($files as $file) {
          $this->files[$file->page_id][$file->field][] = $file;
        }
      }
    }
  }

  /**
   * Return a string representing the source query.
   *
   * @return string
   */
  public function __toString() {
    return $this->gt->get_url() . '/' . $this->project->id . ($this->template ? ('/' . $this->template->id) : '');
  }

  /**
   * Returns a list of fields available to be mapped from the source query.
   *
   * @return array
   *  Keys: machine names of the fields (to be passed to addFieldMapping)
   *  Values: Human-friendly descriptions of the fields.
   */
  public function fields() {
    $fields = array();

    // Add page-level metadata.
    $fields += array(
      'id' => t('Page ID'),
      'name' => t('Page name'),
      'project_id' => t('Project ID'),
      'parent_id' => t('Parent ID'),
      'type' => t('Type'),
      'position' => t('Position'),
      'custom_state' => t('Custom state'),
      'created_at' => t('Created at'),
      'updated_at' => t('Updated at'),
      'due_date' => t('Due date'),
      'template_id' => t('Template ID'),
    );

    // Check for tab (aka field group) definitions on template, and iterate.
    if ($this->template && !empty($this->template->config) && is_array($this->template->config)) {
      foreach ($this->template->config as $tab) {
        // Check for field elements on tab, and iterate.
        if (!empty($tab->elements) && is_array($tab->elements)) {
          foreach ($tab->elements as $field) {
            if ($field->type != 'section') {
              $fields[$field->name] = $tab->label . ': ' . $field->label;
            }
          }
        }
      }
    }

    return $fields;
  }

  /**
   * Return a count of all available source records.
   */
  public function computeCount() {
    return count($this->pages);
  }

  /**
   * Implementation of MigrateSource::performRewind().
   *
   * @return void
   */
  public function performRewind() {
    $this->offset = 0;
  }

  /**
   * Implementation of MigrateSource::getNextRow().
   * Return the next line of the source as an object.
   *
   * @return null|object
   */
  public function getNextRow() {
    $row = NULL;

    if (isset($this->pages[$this->offset])) {
      // Init row object, store GatherContent page metadata fields.
      $page = $this->pages[$this->offset];
      $row = clone $page;
      $this->offset++;

      // Check for tab (aka field group) definitions on page, and iterate.
      if (!empty($page->config) && is_array($page->config)) {
        foreach ($page->config as $tab) {
          // Check for field elements on tab, and iterate.
          if (!empty($tab->elements) && is_array($tab->elements)) {
            foreach ($tab->elements as $field) {
              switch ($field->type) {
                case 'text':
                  $row->{$field->name} = $field->value;
                  break;

                case 'files':
                  if (!empty($this->files[$page->id][$field->name]) && is_array($this->files[$page->id][$field->name])) {
                    foreach ($this->files[$page->id][$field->name] as $file) {
                      $row->{$field->name}[] = $file;
                    }
                  }
                  break;

                case 'choice_checkbox':
                case 'choice_radio':
                  if (!empty($field->options) && is_array($field->options)) {
                    foreach ($field->options as $option) {
                      if ($option->selected) {
                        $row->{$field->name}[] = $option->label;
                      }
                    }
                  }
                  break;
              }
            }
          }
        }
      }
    }

    return $row;
  }
}
