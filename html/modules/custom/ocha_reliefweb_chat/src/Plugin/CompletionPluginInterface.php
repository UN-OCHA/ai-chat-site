<?php

namespace Drupal\ocha_reliefweb_chat\Plugin;

/**
 * Interface for the completion plugins.
 */
interface CompletionPluginInterface {

  /**
   * Generate completions for the given text.
   *
   * @param string $question
   *   Question.
   * @param string $context
   *   Context to answer the question.
   *
   * @return string
   *   Answer to the question.
   */
  public function answer(string $question, string $context): string;

}
