<?php

namespace Drupal\ocha_reliefweb_chat\Plugin\ocha_reliefweb_chat\VectorStore;

use Drupal\ocha_reliefweb_chat\Plugin\VectorStorePluginBase;
use GuzzleHttp\Exception\BadResponseException;
use Psr\Http\Message\ResponseInterface;

/**
 * Light elasticsearch vector store.
 *
 * @OchaReliefWebChatVectorStore(
 *   id = "elasticsearch",
 *   label = @Translation("Elasticsearch"),
 *   description = @Translation("Use Elasticsearch as vector store."),
 * )
 */
class Elasticsearch extends VectorStorePluginBase {

  /**
   * URL of the elasticsearch cluster.
   *
   * @var string
   */
  protected string $url;

  /**
   * Indexing batch size.
   *
   * @var int
   */
  protected int $indexingBatchSize;

  /**
   * {@inheritdoc}
   */
  public function createIndex(string $index, int $dimensions = 384): bool {
    if ($this->indexExists($index)) {
      return TRUE;
    }

    // @todo add other fields, notably the sources (organizations) and
    // publication date so we can generate proper references.
    $payload = [
      'settings' => [
        'index.mapping.nested_objects.limit' => 100000,
        'number_of_shards' => 1,
        'number_of_replicas' => 1,
      ],
      'mappings' => [
        'properties' => [
          'id' => [
            'type' => 'integer',
          ],
          'title' => [
            'type' => 'text',
          ],
          'body' => [
            'type' => 'text',
          ],
          'url' => [
            'type' => 'text',
          ],
          'contents' => [
            'type' => 'nested',
            'properties' => [
              'type' => [
                'type' => 'text',
                'index' => FALSE,
              ],
              'url' => [
                'type' => 'text',
                'index' => FALSE,
              ],
              'title' => [
                'type' => 'text',
                'index' => FALSE,
              ],
              'pages' => [
                'type' => 'nested',
                'properties' => [
                  'page' => [
                    'type' => 'integer',
                    'index' => FALSE,
                  ],
                  'passages' => [
                    'type' => 'nested',
                    'properties' => [
                      'text' => [
                        'type' => 'text',
                        'index' => FALSE,
                      ],
                      'embedding' => [
                        'type' => 'dense_vector',
                        'dims' => $dimensions,
                        'index' => FALSE,
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    return !is_null($this->request('PUT', $index, $payload));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteIndex(string $index): bool {
    if (!$this->indexExists($index)) {
      return TRUE;
    }
    return !is_null($this->request('DELETE', $index));
  }

  /**
   * {@inheritdoc}
   */
  public function indexExists(string $index): bool {
    return !is_null($this->request('HEAD', $index));
  }

  /**
   * {@inheritdoc}
   */
  public function indexDocuments(string $index, array $documents): bool {
    // Skip if there is nothing to index.
    if (empty($documents)) {
      return TRUE;
    }

    // Ensure the index exist.
    if (!$this->createIndex($index)) {
      $this->logger->error(strtr('Unable to create elasticsearch index: @index', [
        '@index' => $index,
      ]));
      return FALSE;
    }

    // Bulk index the documents.
    foreach (array_chunk($documents, $this->getIndexingBatchSize(), TRUE) as $chunks) {
      $payload = [];
      foreach ($chunks as $id => $document) {
        $payload[] = json_encode(['index' => ['_id' => $id]]);
        $payload[] = json_encode($document);
        // Try to free up some memory.
        unset($documents[$id]);
      }
      $payload = implode("\n", $payload) . "\n";

      $response = $this->request('POST', $index . '/_bulk?refresh=true', $payload, 'application/x-ndjson');

      // Abort if there are issues with the indexing.
      if (is_null($response)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocuments(string $index, array $ids, array $fields = ['id']): array {
    if (!$this->indexExists($index)) {
      return [];
    }

    $query = [
      'query' => [
        'ids' => [
          'values' => $ids,
        ],
      ],
      'size' => count($ids),
      '_source' => $fields,
    ];

    $response = $this->request('POST', $index . '/_search', $query);

    if (!is_null($response)) {
      $data = json_decode($response->getBody()->getContents(), TRUE);

      $documents = [];
      foreach ($data['hits']['hits'] ?? [] as $item) {
        $documents[$item['_source']['id']] = $item['_source'];
      }
      return $documents;
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRelevantPassages(string $index, array $ids, string $query_text, array $query_embedding): array {
    if (!$this->indexExists($index)) {
      return [];
    }

    // @todo get that from the configuration.
    $topk = 5;

    $query = [
      '_source' => [
        'id',
        'title',
        'contents.url',
        'contents.type',
      ],
      'size' => $topk * count($ids),
      'query' => [
        'bool' => [
          'filter' => [
            'ids' => [
              'values' => $ids,
            ],
          ],
          'must' => [
            'nested' => [
              'path' => 'contents.pages.passages',
              'query' => [
                'script_score' => [
                  'query' => [
                    // Ensure this appears as an object when converted to JSON.
                    'match_all' => (object) [],
                  ],
                  'script' => [
                    'source' => 'cosineSimilarity(params.queryVector, "contents.pages.passages.embedding") + 1.0',
                    'params' => [
                      'queryVector' => $query_embedding,
                    ],
                  ],
                ],
              ],
              'inner_hits' => [
                '_source' => [
                  'contents.pages.passages.text',
                ],
                'size' => $topk,
              ],
            ],
          ],
        ],
      ],
    ];

    $response = $this->request('POST', $index . '/_search', $query);

    if (!is_null($response)) {
      $data = json_decode($response->getBody()->getContents(), TRUE);

      $passages = [];
      foreach ($data['hits']['hits'] ?? [] as $hit) {
        $id = $hit['_source']['id'];
        $title = $hit['_source']['title'];
        $contents = $hit['_source']['contents'];

        foreach ($hit['inner_hits']['contents.pages.passages']['hits']['hits'] ?? [] as $inner_hit) {
          $content = $contents[$inner_hit['_nested']['offset']];

          $source = [
            'id' => $id,
            'title' => $title,
            'url' => $content['url'],
          ];
          if ($content['type'] === 'file') {
            $source['page'] = $inner_hit['_nested']['_nested']['offset'] + 1;
          }

          $text = $inner_hit['_source']['text'];

          // Ensure uniqueness by using the text as key.
          $passages[mb_strtolower($text)] = [
            'text' => $text,
            'score' => $inner_hit['_score'],
            'source' => $source,
          ];
        }
      }

      $passages = array_values($passages);

      // @todo sort passages by score and limit the number.
      usort($passages, function ($a, $b) {
        return $b['score'] <=> $a['score'];
      });

      // @todo get the number of passages to return from the config.
      $passages = array_slice($passages, 0, $topk);
      return $passages;
    }

    return [];
  }

  /**
   * Perform a request against the elasticsearch cluster.
   *
   * @param string $method
   *   Request method.
   * @param string $endpoint
   *   Request endpoint.
   * @param mixed|null $payload
   *   Optional payload (will be converted to JSON if not content type is
   *   provided).
   * @param string|null $content_type
   *   Optional content type of the payload. If not defined it is assumed to be
   *   JSON.
   *
   * @return \Psr\Http\Message\ResponseInterface|null
   *   A guzzle response or NULL if the request was not successful.
   *
   * @todo handle exceptions.
   */
  protected function request(string $method, string $endpoint, $payload = NULL, ?string $content_type = NULL): ?ResponseInterface {
    $url = $this->getUrl() . '/' . ltrim($endpoint, '/');
    $options = [];

    if (isset($payload)) {
      if (empty($content_type)) {
        $options['json'] = $payload;
      }
      else {
        $options['body'] = $payload;
        $options['headers']['Content-Type'] = $content_type;
      }
    }

    try {
      /** @var \Psr\Http\Message\ResponseInterface $response */
      $response = $this->httpClient->request($method, $url, $options);
    }
    catch (BadResponseException $exception) {
      $response = $exception->getResponse();
      // @todo maybe add a paramete to the method with status codes that are
      // not considered errors like 404 when checking for the index existence.
      $this->logger->error(strtr('@method request to @endpoint failed with @status error: @error', [
        '@method' => $method,
        '@endpoint' => $endpoint,
        '@status' => $response->getStatusCode(),
        '@error' => $exception->getMessage(),
      ]));
      return NULL;
    }

    return $response;
  }

  /**
   * Get the URL of the elasticsearch cluster.
   *
   * @return string
   *   Elasticsearch URL.
   */
  protected function getUrl():string {
    if (!isset($this->url)) {
      // @todo throw an error if this is not defined.
      $this->url = rtrim($this->config->get('elasticsearch_url'), '/');
    }
    return $this->url;
  }

  /**
   * Get the indexing batch size.
   *
   * @return int
   *   Indexing batch size.
   */
  protected function getIndexingBatchSize(): int {
    if (!isset($this->indexingBatchSize)) {
      // @todo throw an error if this is not defined.
      $this->indexingBatchSize = (int) $this->config->get('elasticsearch_indexing_batch_size');
    }
    return $this->indexingBatchSize;
  }

  /**
   * Calculate the dot product of 2 vectors.
   *
   * @param array $a
   *   First vector.
   * @param array $b
   *   Second vector.
   *
   * @return float
   *   Dot product of the 2 vectors.
   */
  protected function dotProdcut(array $a, array $b): float {
    return array_sum(array_map(function ($x, $y) {
       return $x * $y;
    }, $a, $b));
  }

  /**
   * Calculate the cosine similarity of 2 vectors.
   *
   * @param array $a
   *   First vector.
   * @param array $b
   *   Second vector.
   *
   * @return float
   *   Cosine similarity of the 2 vectors.
   */
  protected function cosineSimilarity(array $a, array $b) {
    return $this->dotProdcut($a, $b) / sqrt($this->dotProdcut($a, $a) * $this->dotProdcut($b, $b));
  }

}
