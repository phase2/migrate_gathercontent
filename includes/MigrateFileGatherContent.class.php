<?php
/**
 * @file
 * Contains the class definition for MigrateFileGatherContent.
 */

/**
 * Provides a file import class for GatherContent file attachments.
 */
class MigrateFileGatherContent extends MigrateFile {
  protected $base_path = 'https://gathercontent.s3.amazonaws.com/';

  /**
   * Implementation of MigrateFileInterface::processFiles().
   *
   * @param $value
   *  The URI or local filespec of a file to be imported.
   * @param $owner
   *  User ID (uid) to be the owner of the file.
   * @return object
   *  The file entity being created or referenced.
   */
  public function processFile($value, $owner) {
    // Identify the full path to the source file.
    $this->sourcePath = $this->base_path . $value->filename;

    // Identify the destination filename.
    $this->destinationFile = $value->original_filename;

    // MigrateFile has most of the smarts - the key is that it will call back
    // to our copyFile() implementation.
    $file = parent::processFile($value, $owner);

    return $file;
  }

  /**
   * Implementation of MigrateFile::copyFile().
   *
   * @param $destination
   *  Destination within Drupal.
   *
   * @return bool
   *  TRUE if the copy succeeded, FALSE otherwise.
   */
  protected function copyFile($destination) {
    if (!@copy($this->sourcePath, $destination)) {
      $migration = Migration::currentMigration();
      $migration->saveMessage(t('The specified file %file could not be copied to %destination.',
              array('%file' => $this->sourcePath, '%destination' => $destination)));
      return FALSE;
    }
    else {
      return TRUE;
    }
  }
}
