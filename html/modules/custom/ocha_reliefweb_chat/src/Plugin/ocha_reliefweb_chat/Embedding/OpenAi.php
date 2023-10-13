<?php

namespace Drupal\ocha_reliefweb_chat\Plugin\ocha_reliefweb_chat\Embedding;

use Drupal\ocha_reliefweb_chat\Plugin\EmbeddingPluginBase;
use Guzzle\Http\Exception\BadResponseException;

/**
 * OpenAI embedding generator.
 *
 * @OchaReliefWebChatEmbedding(
 *   id = "openai",
 *   label = @Translation("OpenAI"),
 *   description = @Translation("Use OpenAI as embedding generator."),
 * )
 */
class OpenAi extends EmbeddingPluginBase {

  /**
   * Embedding model.
   *
   * @var string
   */
  protected string $model;

  /**
   * Embedding URL.
   *
   * @var string
   */
  protected string $url;

  /**
   * {@inheritdoc}
   */
  public function generateEmbeddings(array $texts): array {
    if (empty($texts)) {
      return [];
    }

    // @todo retrieve that from the configuration.
    $batch_size = 16;
    $max_tokens = 256 - 30;

    $accumulator = [];
    $embeddings = [];
    foreach ($texts as $index => $text) {
      $token_count = $this->estimateTokenCount($text);
      if (
        count($accumulator) < $batch_size &&
        array_sum($accumulator) + $token_count < $max_tokens
      ) {
        $accumulator[$index] = $token_count;
      }
      else {
        $batch = array_values(array_intersect_key($texts, $accumulator));
        $embeddings = array_merge($embeddings, $this->requestEmbeddings($batch));
        $accumulator = [$index => $token_count];
      }
    }

    if (!empty($accumulator)) {
      $batch = array_values(array_intersect_key($texts, $accumulator));
      $embeddings = array_merge($embeddings, $this->requestEmbeddings($batch));
    }

    return $embeddings;
  }

  /**
   * {@inheritdoc}
   */
  public function generateEmbedding(string $text): array {
    if (empty($text)) {
      return [];
    }

    return $this->requestEmbeddings([$text])[0] ?? [];
  }

  /**
   * Perform a request against the API to get the embeddings for the texts.
   *
   * @param array $texts
   *   List of texts.
   *
   * @return array
   *   List of embeddings for the texts.
   */
  protected function requestEmbeddings(array $texts): array {
    if (empty($texts)) {
      return [];
    }

    $payload = [
      'input' => $texts,
      'model' => $this->getModel(),
    ];

    try {
      /** @var \Psr\Http\Message\ResponseInterface $response */
      $response = $this->httpClient->request('POST', $this->getUrl(), [
        'json' => $payload,
      ]);
    }
    catch (BadResponseException $exception) {
      $response = $exception->getResponse();
      $this->logger->error(strtr('Embedding request failed with @status error: @error.', [
        '@status' => $response->getStatusCode(),
        '@error' => $exception->getMessage(),
      ]));
      return [];
    }

    try {
      $data = json_decode($response->getBody()->getContents(), TRUE);
    }
    catch (\Exception $exception) {
      $this->logger->error('Unable to decode embedding response.');
      return [];
    }

    return array_map(function ($item) {
      return $item['embedding'] ?? [];
    }, $data['data'] ?? array_fill(0, count($texts), []));
  }

  /**
   * Estimate the number of tokens for a text.
   *
   * @param string $text
   *   Text.
   *
   * @return int
   *   Estimated number of tokens in the text.
   */
  protected function estimateTokenCount(string $text): int {
    $word_count = count(preg_split('/[^\p{L}\p{N}\']+/u', $text));
    return floor($word_count * 0.75);
  }

  /**
   * Get the embedding URL.
   *
   * @return string
   *   Embedding URL.
   */
  protected function getUrl():string {
    if (!isset($this->url)) {
      // @todo throw an error if this is not defined.
      $this->url = trim($this->config->get('openai_embedding_url'));
    }
    return $this->url;
  }

  /**
   * Get the embedding model.
   *
   * @return string
   *   Model name.
   */
  protected function getModel(): string {
    if (!isset($this->model)) {
      // @todo throw an error if this is not defined.
      $this->model = trim($this->config->get('openai_embedding_model'));
    }
    return $this->model;
  }

}
