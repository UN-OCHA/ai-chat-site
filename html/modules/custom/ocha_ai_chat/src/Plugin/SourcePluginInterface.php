<?php

namespace Drupal\ocha_ai_chat\Plugin;

/**
 * Interface for the Source plugins.
 */
interface SourcePluginInterface {

  /**
   * Generate Sources for the given text.
   *
   * @param string $url
   *   The URL where to retrieve the documents.
   * @param int $limit
   *   Maximum number of documents to retrieve.
   *
   * @return array
   *   Associative array with the resource as key and associative arrays of
   *   documents with their IDs as keys and with id, title, url,
   *   source and contents (associative array with type, title, url and optional
   *   content property dependending on the type) as values.
   */
  public function getDocuments(string $url, int $limit = 10): array;

}
