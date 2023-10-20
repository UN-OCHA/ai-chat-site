<?php

declare(strict_types=1);

namespace Drupal\ocha_ai_chat\Plugin\ocha_ai_chat\Completion;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ocha_ai_chat\Plugin\CompletionPluginBase;

/**
 * AWS Bedrock completion generator.
 *
 * @OchaAiChatCompletion(
 *   id = "aws_bedrock",
 *   label = @Translation("AWS Bedrock"),
 *   description = @Translation("Use AWS Bedrock as completion generator."),
 * )
 */
class AwsBedrock extends CompletionPluginBase {

  /**
   * AWS Bedrock API client.
   *
   * @var \Aws\BedrockRuntime\BedrockRuntimeClient
   */
  protected BedrockRuntimeClient $apiClient;

  /**
   * {@inheritdoc}
   */
  public function answer(string $question, string $context): string {
    if (empty($question) || empty($context)) {
      return '';
    }

    // @todo review what is a good template for AWS titan model.
    $prompt = strtr($this->getPluginSetting('prompt_template'), [
      '{{ context }}' => $context,
      '{{ question }}' => $question,
    ]);

    $payload = [
      'accept' => 'application/json',
      'body' => json_encode([
        'inputText' => $prompt,
        'textGenerationConfig' => [
          'maxTokenCount' => $this->getPluginSetting('max_tokens', 512),
          // @todo adjust based on the prompt.
          'stopSequences' => [],
          'temperature' => 0.0,
          'topP' => 0.9,
        ],
      ]),
      'contentType' => 'application/json',
      'modelId' => $this->getPluginSetting('model'),
    ];

    try {
      /** @var \Aws\Result $response */
      $response = $this->getApiClient()->invokeModel($payload);
    }
    catch (\Exception $exception) {
      $this->getLogger()->error(strtr('Completion request failed with error: @error.', [
        '@error' => $exception->getMessage(),
      ]));
      return '';
    }

    try {
      $data = json_decode($response->get('body')->getContents(), TRUE);
    }
    catch (\Exception $exception) {
      $this->getLogger()->error('Unable to decode completion response.');
      return '';
    }

    return trim($data['results'][0]['outputText'] ?? '');
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
