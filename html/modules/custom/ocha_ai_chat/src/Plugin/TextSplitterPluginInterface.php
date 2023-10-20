<?php

namespace Drupal\ocha_ai_chat\Plugin;

/**
 * Interface for the text splitter plugins.
 */
interface TextSplitterPluginInterface {

  /**
   * Split text.
   *
   * @param string $text
   *   Text to split.
   *
   * @return array
   *   List of text chunks
   */
  public function splitText(string $text): array;

}
