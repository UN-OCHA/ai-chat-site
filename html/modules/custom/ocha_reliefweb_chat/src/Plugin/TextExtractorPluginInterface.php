<?php

namespace Drupal\ocha_reliefweb_chat\Plugin;

/**
 * Interface for the text extractor plugins.
 */
interface TextExtractorPluginInterface {

  /**
   * Get the text for the entire document.
   *
   * @param string $path
   *   File path.
   *
   * @return string
   *   Extracted text.
   */
  public function getText(string $path): string;

  /**
   * Get the text of each page of a PDF file.
   *
   * @param string $path
   *   File path.
   *
   * @return array
   *   List of the text of each page.
   */
  public function getPageTexts(string $path): array;

  /**
   * Get the number of pages of a file.
   *
   * @param string $path
   *   File path.
   *
   * @return int
   *   Number of pages.
   */
  public function getPageCount(string $path): int;

}
