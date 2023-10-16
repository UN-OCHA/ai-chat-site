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
   * @param int $length
   *   Length of a group of texts (ex: number of characters or sentences).
   * @param int $overlap
   *   Number of previous elements (ex: characters, sentences) to include in the
   *   groups of sentences to try to preserve meaningful context.
   *
   * @return array
   *   List of text chunks
   *
   * @todo handle length differently, to limit the number of characters to have
   * more consistent output.
   */
  public function splitText(string $text, int $length, int $overlap): array;

}
