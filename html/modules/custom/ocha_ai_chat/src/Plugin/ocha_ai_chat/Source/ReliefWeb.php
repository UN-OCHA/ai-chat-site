<?php

namespace Drupal\ocha_ai_chat\Plugin\ocha_ai_chat\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ocha_ai_chat\Plugin\SourcePluginBase;
use GuzzleHttp\Exception\BadResponseException;
use Symfony\Component\Uid\Uuid;

/**
 * ReliefWeb document source.
 *
 * @OchaAiChatSource(
 *   id = "reliefweb",
 *   label = @Translation("ReliefWeb"),
 *   description = @Translation("Use ReliefWeb as document source."),
 * )
 */
class ReliefWeb extends SourcePluginBase {

  /**
   * ReliefWeb API URL.
   *
   * @var string
   */
  protected string $apiUrl;

  /**
   * ReliefWeb API converter URL.
   *
   * @var string
   */
  protected string $converterUrl;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $plugin_type = $this->getPluginType();
    $plugin_id = $this->getPluginId();
    $config = $this->getConfiguration() + $this->defaultConfiguration();

    $form['plugins'][$plugin_type][$plugin_id]['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API URL'),
      '#description' => $this->t('ReliefWeb API URL.'),
      '#default_value' => $config['api_url'] ?? NULL,
      '#required' => TRUE,
    ];

    $form['plugins'][$plugin_type][$plugin_id]['converter_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('converter_url'),
      '#description' => $this->t('ReliefWeb search converter.'),
      '#default_value' => $config['converter_url'] ?? NULL,
      '#required' => TRUE,
    ];

    $form['plugins'][$plugin_type][$plugin_id]['appname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API appname'),
      '#description' => $this->t('ReliefWeb API appname.'),
      '#default_value' => $config['appname'] ?? NULL,
      '#required' => TRUE,
    ];

    $form['plugins'][$plugin_type][$plugin_id]['cache_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cache enabled'),
      '#description' => $this->t('Flag to indicate if API results should be cached.'),
      '#default_value' => !empty($config['cache_enabled']),
    ];

    $form['plugins'][$plugin_type][$plugin_id]['cache_lifetime'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache lifetime'),
      '#description' => $this->t('Number of seconds to keep the results of the API in cache.'),
      '#default_value' => $config['cache_lifetime'] ?? NULL,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'appname' => 'ocha-ai-chat',
      'cache_enabled' => TRUE,
      'cache_lifetime' => 300,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDocuments(string $url, int $limit = 10): array {
    // 1. Retrieve the API resource and payload for the river URL.
    // 2. Retrieve the API data.
    // 3. Convert the API data.
    if (!$this->checkRiverUrl($url)) {
      return [];
    }

    $url = $this->prepareRiverUrl($url);
    $cache_id = $this->getCacheId($url, $limit);

    // Attempt to retrieve the cached data for the query.
    $documents = $this->getCachedDocuments($cache_id);
    if (isset($documents)) {
      return $documents;
    }

    // Get the API resource and payload from the river URL.
    $request = $this->getApiRequest($url);
    if (empty($request)) {
      $this->getLogger()->error('Unable to retrieve API request for the ReliefWeb river URL: @url.', [
        '@url' => $url,
      ]);
      return $this->cacheDocuments($cache_id, []);
    }

    // Extract the API resource from the API url.
    $resource = basename(parse_url($request['url'], \PHP_URL_PATH));

    // Adjust the payload with limit, order etc.
    $payload = $this->adjustApiPayload($request['payload'], $limit);

    // Get the data from the API.
    $data = $this->getApiData($resource, $payload);

    // Prepare the documents.
    $documents = $this->parseApiData($resource, $data);

    return $this->cacheDocuments($cache_id, $documents);
  }

  /**
   * Validate a ReliefWeb river URL.
   *
   * @param string $url
   *   River URL.
   *
   * @return bool
   *   TRUE if the URL is valid.
   */
  protected function checkRiverUrl(string $url): bool {
    if (empty($url)) {
      $this->getLogger()->error('Missing ReliefWeb river URL.');
      return FALSE;
    }

    // Ensure the river URL is for reports.
    // @todo Handle other rivers at some point?
    if (basename(parse_url($url, \PHP_URL_PATH)) !== 'updates') {
      $this->getLogger()->error('URL not a ReliefWeb updates river.');
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Prepare the River URL to pass to the converter.
   *
   * @param string $url
   *   River URL.
   *
   * @return string
   *   River URL.
   */
  protected function prepareRiverUrl(string $url): string {
    // Add a the "report_only" view parameter to exclude Maps, Infographics and
    // interactive content since they don't really contain content to chat with.
    $url = preg_replace('/([?&])view=[^?&]*/u', '$1view=reports', $url);
    if (strpos($url, 'view=reports') !== FALSE) {
      return $url;
    }
    else {
      return $url . (strpos($url, '?') !== FALSE ? '&' : '?') . 'view=reports';
    }
  }

  /**
   * Adjust the API payload.
   *
   * @param array $payload
   *   API payload.
   * @param int $limit
   *   Maximum number of documents.
   *
   * @return array
   *   Adjusted API payload.
   */
  protected function adjustApiPayload(array $payload, int $limit): array {
    $payload['limit'] = $limit;
    $payload['sort'] = ['date.original:desc', 'id:desc'];

    // @todo Review which fields could be useful for filtering (ex: country).
    $payload['fields']['include'] = [
      'id',
      'url',
      'url_alias',
      'title',
      'body',
      'file.url',
      'file.mimetype',
      'source.name',
      'source.shortname',
      'date',
    ];

    return $payload;
  }

  /**
   * Get the API request (url + payload) for the given ReliefWeb river URL.
   *
   * @param string $url
   *   ReliefWeb River URL.
   * @param int $timeout
   *   Request timeout.
   *
   * @return array
   *   ReliefWeb API request data (url + payload) as an associative array.
   */
  public function getApiRequest(string $url, int $timeout = 5): array {
    try {
      $response = $this->httpClient->get($this->getConverterUrl(), [
        'query' => [
          'appname' => $this->getPluginSetting('appname', 'ocha-ai-chat'),
          'search-url' => $url,
        ],
        'timeout' => $timeout,
        'connect_timeout' => $timeout,
      ]);
    }
    catch (BadResponseException $exception) {
      // @todo handle timeouts and skip caching the result in that case?
      $this->getLogger()->error(strtr('Error @code while requesting the ReliefWeb API converter with @url: @exception', [
        '@code' => $exception->getResponse()?->getStatusCode(),
        '@url' => $url,
        '@exception' => $exception->getMessage(),
      ]));
      return [];
    }

    $body = (string) $response->getBody()?->getContents();
    if (!empty($body)) {
      try {
        // Decode the JSON response.
        $data = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);

        // Get the request data (API url + payload).
        return $data['output']['requests']['post'] ?? [];
      }
      catch (\Exception $exception) {
        $this->getLogger()->error(strtr('Unable to decode ReliefWeb API conversion data for @url', [
          '@url' => $url,
        ]));
      }
    }

    return [];
  }

  /**
   * Get the API data for the given resource and payload.
   *
   * @param string $resource
   *   API resource.
   * @param array $payload
   *   Request payload.
   * @param int $timeout
   *   Request timeout.
   *
   * @return array
   *   ReliefWeb API request data (url + payload) as an associative array.
   */
  public function getApiData(string $resource, array $payload, int $timeout = 5): array {
    $url = $this->getApiUrl() . '/' . trim($resource, '/');
    try {
      $response = $this->httpClient->post($url, [
        'query' => [
          'appname' => $this->getPluginSetting('appname', 'ocha-ai-chat'),
        ],
        'timeout' => $timeout,
        'connect_timeout' => $timeout,
        'json' => $payload,
      ]);
    }
    catch (BadResponseException $exception) {
      // @todo handle timeouts and skip caching the result in that case?
      $this->getLogger()->error(strtr('Error @code while requesting the ReliefWeb API with @url: @exception', [
        '@code' => $exception->getResponse()?->getStatusCode(),
        '@url' => $url,
        '@exception' => $exception->getMessage(),
      ]));
      return [];
    }

    $body = (string) $response->getBody()?->getContents();
    if (!empty($body)) {
      try {
        // Decode the JSON response.
        $data = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);

        // Get the API data.
        return $data ?? [];
      }
      catch (\Exception $exception) {
        $this->getLogger()->error(strtr('Unable to decode ReliefWeb API data for @url', [
          '@url' => $url,
        ]));
      }
    }

    return [];
  }

  /**
   * Parse the API data and return documents.
   *
   * @param string $resource
   *   API resource.
   * @param array $data
   *   API data.
   *
   * @return array
   *   Associative array with the resource as key and associative arrays of
   *   documents with their IDs as keys and with id, title, url,
   *   source and contents (associative array with type, title, url and optional
   *   content property dependending on the type) as values.
   */
  protected function parseApiData(string $resource, array $data): array {
    if (empty($data['data'])) {
      return [];
    }

    $documents = [];
    foreach ($data['data'] as $items) {
      $fields = $items['fields'];

      // Use the UUID from the document canonical URL as ID to avoid collisions.
      $id = $this->getUuidFromUrl($fields['url']);

      // @todo add additional metadata like country, organization, notably
      // to generate references and to help filtering the content and possibly
      // extend the research to othe documents than the ones returned by
      // the API query for example as "related documents" etc.
      $document = [
        'id' => $id,
        'title' => $fields['title'],
        'url' => $fields['url'],
        'source' => $fields['source'],
        'date' => $fields['date'],
        'contents' => [],
      ];

      $title = trim($fields['title']);
      $body = trim($fields['body'] ?? '');

      $document['contents'][] = [
        // @todo might not be so great to use the same id as the parent document
        // maybe use a prefix.
        'id' => $id,
        'url' => $fields['url_alias'] ?? $fields['url'],
        'type' => 'markdown',
        'content' => "# $title\n\n$body",
      ];

      // Attachments with their URL so they can be downloaded.
      foreach ($fields['file'] ?? [] as $file) {
        $document['contents'][] = [
          'id' => $this->getUuidFromUrl($file['url']),
          'url' => $file['url'],
          'type' => 'file',
          'mimetype' => $file['mimetype'],
        ];
      }

      $documents[$resource][$id] = $document;
    }
    return $documents;
  }

  /**
   * Get the ReliefWeb API URL.
   *
   * @return string
   *   ReliefWeb API URL.
   */
  protected function getApiUrl(): string {
    if (!isset($this->apiUrl)) {
      $api_url = $this->getPluginSetting('api_url');
      if (empty($api_url) && !is_string($api_url)) {
        throw new \Exception('Missing or invalid ReliefWeb API URL');
      }
      $this->apiUrl = rtrim($api_url, '/');
    }
    return $this->apiUrl;
  }

  /**
   * Get the ReliefWeb API converter URL.
   *
   * @return string
   *   ReliefWeb API converter URL.
   */
  protected function getConverterUrl(): string {
    if (!isset($this->converterUrl)) {
      $converter_url = $this->getPluginSetting('converter_url');
      if (empty($converter_url) && !is_string($converter_url)) {
        throw new \Exception('Missing or invalid ReliefWeb API converter URL');
      }
      $this->converterUrl = rtrim($converter_url, '/');
    }
    return $this->converterUrl;
  }

  /**
   * Get whether caching is enabled or not.
   *
   * @var bool
   *   TRUE if caching is enabled.
   */
  protected function isCacheEnabled(): bool {
    return $this->getPluginSetting('cache_enabled');
  }

  /**
   * Get the cache lifetime in seconds.
   *
   * @var int
   *   Cache lifetime.
   */
  protected function getCacheLifetime(): int {
    return $this->getPluginSetting('cache_lifetime');
  }

  /**
   * Get the cache ID based on the given URL and limit.
   *
   * @param string $url
   *   URL.
   * @param int $limit
   *   Maximum number of documents.
   *
   * @return string
   *   Cache ID.
   */
  protected function getCacheId(string $url, int $limit): string {
    return 'reliefweb_api:' . $this->getUuidFromUrl($url) . ':' . $limit;
  }

  /**
   * Get cached documents.
   *
   * @param string $cache_id
   *   Cache ID.
   *
   * @return array|null
   *   Cached documents.
   */
  protected function getCachedDocuments(string $cache_id): ?array {
    if ($this->isCacheEnabled()) {
      $cache = $this->cacheBackend->get($cache_id);
      if (isset($cache->data)) {
        return $cache->data;
      }
    }
    return NULL;
  }

  /**
   * Cache documents.
   *
   * @param string $cache_id
   *   Cache ID.
   * @param array $documents
   *   Documents.
   *
   * @return array
   *   Cached documents.
   */
  protected function cacheDocuments(string $cache_id, array $documents): array {
    if ($this->isCacheEnabled()) {
      $tags = ['reliefweb:documents'];
      $cache_expiration = $this->time->getRequestTime() + $this->getCacheLifetime();
      $this->cacheBackend->set($cache_id, $documents, $cache_expiration, $tags);
    }
    return $documents;
  }

  /**
   * Get the UUID for to the URL.
   *
   * @param string $url
   *   URL.
   *
   * @return string
   *   UUID.
   */
  protected function getUuidFromUrl(string $url): string {
    return Uuid::v3(Uuid::fromString(Uuid::NAMESPACE_URL), $url)->toRfc4122();
  }

}
