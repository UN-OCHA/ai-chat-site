<?php

namespace Drupal\ocha_ai_chat\Plugin\ocha_ai_chat\Embedding;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ocha_ai_chat\Plugin\EmbeddingPluginBase;

/**
 * AWS embedding generator.
 *
 * @OchaAiChatEmbedding(
 *   id = "aws_bedrock",
 *   label = @Translation("AWS Bedrock"),
 *   description = @Translation("Use AWS Bedrock as embedding generator."),
 * )
 */
class AwsBedrock extends EmbeddingPluginBase {

  /**
   * AWS Bedrock API client.
   *
   * @var \Aws\BedrockRuntime\BedrockRuntimeClient
   */
  protected BedrockRuntimeClient $apiClient;

  /**
   * {@inheritdoc}
   */
  public function generateEmbeddings(array $texts): array {
    if (empty($texts)) {
      return [];
    }

    // Maximum number of embeddings to request at once.
    $batch_size = $this->getPluginSetting('batch_size');
    // Maximum number of input tokens accepted by the model (with a margin).
    $max_tokens = $this->getPluginSetting('max_tokens') - 30;

    // We batch the generation by passing several texts at once as long as their
    // size doesn't exceed the max number of input tokens.
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

    // Process the leftover from the loop if any.
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
    // Bedrock doesn't seem to support generating multiple embeddings at once
    // so we need to perform individual requests for each text.
    $embeddings = [];
    foreach ($texts as $text) {
      $embeddings[] = $this->requestEmbedding($text);
    }
    return $embeddings;
  }

  /**
   * Perform a request against the API to get the embeddings for the text.
   *
   * @param string $text
   *   Text.
   *
   * @return array
   *   Embedding for the text.
   */
  protected function requestEmbedding(string $text): array {
    $payload = [
      'accept' => 'application/json',
      'body' => json_encode([
        'inputText' => $text,
      ]),
      'contentType' => 'application/json',
      'modelId' => $this->getPluginSetting('model'),
    ];

    try {
      /** @var \Aws\Result $response */
      $response = $this->getApiClient()->invokeModel($payload);
    }
    catch (\Exception $exception) {
      $this->getLogger()->error(strtr('Embedding request failed with error: @error.', [
        '@error' => $exception->getMessage(),
      ]));
      return [];
    }

    try {
      $data = json_decode($response->get('body')->getContents(), TRUE);
    }
    catch (\Exception $exception) {
      $this->getLogger()->error('Unable to decode embedding response.');
      return [];
    }

    return $data['embedding'];
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
   * Get the Bedrock API Client.
   *
   * @return \Aws\BedrockRuntime\BedrockRuntimeClient
   *   API Client.
   */
  protected function getApiClient(): BedrockRuntimeClient {
    if (!isset($this->apiClient)) {
      $options = [
        'credentials' => [
          'key' => $this->getPluginSetting('api_key'),
          'secret' => $this->getPluginSetting('api_secret'),
        ],
        'region'  => $this->getPluginSetting('region'),
      ];

      $endpoint = $this->getPluginSetting('endpoint', NULL, FALSE);
      if (!empty($endpoint)) {
        $options['endpoint'] = $endpoint;
      }

      $this->apiClient = new BedrockRuntimeClient($options);
    }
    return $this->apiClient;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $plugin_type = $this->getPluginType();
    $plugin_id = $this->getPluginId();

    $form['plugins'][$plugin_type][$plugin_id]['endpoint']['#required'] = FALSE;
    $form['plugins'][$plugin_type][$plugin_id]['endpoint']['#description'] = $this->t('Endpoint of the API. Leave empty to use the official one.');

    return $form;
  }

}
