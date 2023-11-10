<?php

namespace Drupal\ocha_ai_chat\Plugin;

/**
 * Interface for the embedding plugins.
 */
interface EmbeddingPluginInterface {

  /**
   * Generate embeddings for the given texts.
   *
   * @param array $texts
   *   List of texts.
   *
   * @return array
   *   List of embeddings. Each contains a text property with the original text
   *   and an embedding property with the vector.
   *
   * @throws \Exception
   *   Throw an exception if the generation of the embedddings fails.
   */
  public function generateEmbeddings(array $texts): array;

  /**
   * Generate embedding for the given text.
   *
   * @param string $text
   *   Text.
   *
   * @return array
   *   Embedding for the text or empty array in case of failure.
   */
  public function generateEmbedding(string $text): array;

  /**
   * Get the number of dimensions for the embeddings.
   *
   * @return int
   *   Dimensions.
   */
  public function getDimensions(): int;

  /**
   * Get the model name.
   *
   * @return string
   *   Model name.
   */
  public function getModelName(): string;

}
