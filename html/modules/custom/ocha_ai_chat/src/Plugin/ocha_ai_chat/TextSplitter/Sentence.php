<?php

namespace Drupal\ocha_ai_chat\Plugin\ocha_ai_chat\TextSplitter;

use Drupal\ocha_ai_chat\Plugin\TextSplitterPluginBase;

/**
 * Split a text in groups of sentences.
 *
 * @OchaAiChatTextSplitter(
 *   id = "sentence",
 *   label = @Translation("Sentence"),
 *   description = @Translation("Split a text in groups of sentences."),
 * )
 */
class Sentence extends TextSplitterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function splitText(string $text, int $length, int $overlap): array {
    // Split the text into paragraphs and setences.
    $paragraphs = [];

    // @todo review how to better split paragraphs.
    foreach (preg_split('/\n{2,}/u', $text, -1, \PREG_SPLIT_NO_EMPTY) as $paragraph) {
      $paragraph = preg_replace('/\s+/u', ' ', $paragraph);
      $paragraph = trim(preg_replace('/([;.!?。؟]+)\s+/u', "$1\n", $paragraph));

      $sentences = [];
      foreach (preg_split('/\n+/u', $paragraph, -1, \PREG_SPLIT_NO_EMPTY) as $sentence) {
        $sentence = trim($sentence);
        if (!empty($sentence)) {
          $sentences[] = $sentence;
        }
      }

      $paragraphs[] = $sentences;
    }

    // Generate groups of sentences.
    $groups = [];
    foreach ($paragraphs as $sentences) {
      $count = count($sentences);
      for ($i = 0; $i < $count; $i += $length) {
        $group = [];
        // Include $overlap previous sentences to the group to try to
        // preserve some context.
        // @todo Include  following sentences as well?
        for ($j = max(0, $i - $overlap); $j < $i + $length; $j++) {
          if (isset($sentences[$j])) {
            $group[] = $sentences[$j];
          }
        }
        $groups[] = implode(' ', $group);
      }
    }

    return $groups;
  }

}
