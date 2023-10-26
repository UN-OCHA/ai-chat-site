<?php

namespace Drupal\ocha_ai_chat\Services;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\ocha_ai_chat\Plugin\CompletionPluginInterface;
use Drupal\ocha_ai_chat\Plugin\CompletionPluginManagerInterface;
use Drupal\ocha_ai_chat\Plugin\EmbeddingPluginInterface;
use Drupal\ocha_ai_chat\Plugin\EmbeddingPluginManagerInterface;
use Drupal\ocha_ai_chat\Plugin\SourcePluginInterface;
use Drupal\ocha_ai_chat\Plugin\SourcePluginManagerInterface;
use Drupal\ocha_ai_chat\Plugin\TextExtractorPluginInterface;
use Drupal\ocha_ai_chat\Plugin\TextExtractorPluginManagerInterface;
use Drupal\ocha_ai_chat\Plugin\TextSplitterPluginInterface;
use Drupal\ocha_ai_chat\Plugin\TextSplitterPluginManagerInterface;
use Drupal\ocha_ai_chat\Plugin\VectorStorePluginInterface;
use Drupal\ocha_ai_chat\Plugin\VectorStorePluginManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * OCHA AI Chat service.
 */
class OchaAiChat {

  /**
   * OCHA AI Chat config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Completion plugin manager.
   *
   * @var \Drupal\ocha_ai_chat\Plugin\CompletionPluginManagerInterface
   */
  protected CompletionPluginManagerInterface $completionPluginManager;

  /**
   * Embedding plugin manager.
   *
   * @var \Drupal\ocha_ai_chat\Plugin\EmbeddingPluginManagerInterface
   */
  protected EmbeddingPluginManagerInterface $embeddingPluginManager;

  /**
   * Source plugin manager.
   *
   * @var \Drupal\ocha_ai_chat\Plugin\SourcePluginManagerInterface
   */
  protected SourcePluginManagerInterface $sourcePluginManager;

  /**
   * Text extractor plugin manager.
   *
   * @var \Drupal\ocha_ai_chat\Plugin\TextExtractorPluginManagerInterface
   */
  protected TextExtractorPluginManagerInterface $textExtractorPluginManager;

  /**
   * Text splitter plugin manager.
   *
   * @var \Drupal\ocha_ai_chat\Plugin\TextSplitterPluginManagerInterface
   */
  protected TextSplitterPluginManagerInterface $textSplitterPluginManager;

  /**
   * Vector store manager.
   *
   * @var \Drupal\ocha_ai_chat\Plugin\VectorStorePluginManagerInterface
   */
  protected VectorStorePluginManagerInterface $vectorStorePluginManager;

  /**
   * Static cache for the settings.
   *
   * @var array
   */
  protected array $settings;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Database\Connection $database
   *   The database.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\ocha_ai_chat\Plugin\CompletionPluginManagerInterface $completion_plugin_manager
   *   The completion plugin manager.
   * @param \Drupal\ocha_ai_chat\Plugin\EmbeddingPluginManagerInterface $embedding_plugin_manager
   *   The embedding plugin manager.
   * @param \Drupal\ocha_ai_chat\Plugin\SourcePluginManagerInterface $source_plugin_manager
   *   The source plugin manager.
   * @param \Drupal\ocha_ai_chat\Plugin\TextExtractorPluginManagerInterface $text_extractor_plugin_manager
   *   The text extractor plugin manager.
   * @param \Drupal\ocha_ai_chat\Plugin\TextSplitterPluginManagerInterface $text_splitter_plugin_manager
   *   The text splitter plugin manager.
   * @param \Drupal\ocha_ai_chat\Plugin\VectorStorePluginManagerInterface $vector_store_plugin_manager
   *   The vector store plugin manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    StateInterface $state,
    AccountProxyInterface $current_user,
    Connection $database,
    TimeInterface $time,
    CompletionPluginManagerInterface $completion_plugin_manager,
    EmbeddingPluginManagerInterface $embedding_plugin_manager,
    SourcePluginManagerInterface $source_plugin_manager,
    TextExtractorPluginManagerInterface $text_extractor_plugin_manager,
    TextSplitterPluginManagerInterface $text_splitter_plugin_manager,
    VectorStorePluginManagerInterface $vector_store_plugin_manager
  ) {
    $this->config = $config_factory->get('ocha_ai_chat.settings');
    $this->logger = $logger_factory->get('ocha_ai_chat');
    $this->state = $state;
    $this->currentUser = $current_user;
    $this->database = $database;
    $this->time = $time;
    $this->completionPluginManager = $completion_plugin_manager;
    $this->embeddingPluginManager = $embedding_plugin_manager;
    $this->sourcePluginManager = $source_plugin_manager;
    $this->textExtractorPluginManager = $text_extractor_plugin_manager;
    $this->textSplitterPluginManager = $text_splitter_plugin_manager;
    $this->vectorStorePluginManager = $vector_store_plugin_manager;
  }

  /**
   * Answer the question against the ReliefWeb documents from the river URL.
   *
   * 1. Retrieve the documents from the ReliefWeb API URL.
   * 2. Embed the documents if not already.
   * 3. Generate the embedding for the question
   * 4. Find the documents relevant to the question.
   * 5. Generate the prompt context.
   * 6. Answer the question.
   *
   * @param string $question
   *   Question.
   * @param string $url
   *   Document source URL.
   * @param int $limit
   *   Number of documents to retrieve.
   * @param \Drupal\ocha_ai_chat\Plugin\CompletionPluginInterface $completion_plugin
   *   Optional completion plugin override.
   *
   * @return array
   *   Associative array with the qestion, the answer, the source URL,
   *   the limit, the plugins, the stats and the relevant passages.
   */
  public function answer(string $question, string $url, int $limit = 10, ?CompletionPluginInterface $completion_plugin = NULL): array {
    $completion_plugin = $completion_plugin ?? $this->getCompletionPlugin();
    $embedding_plugin = $this->getEmbeddingPlugin();
    $vector_store_plugin = $this->getVectorStorePlugin();

    // Stats to record the time of each operation.
    // @todo either store the stats elsewhere (log etc.) or remove.
    $data = [
      'source_url' => $url,
      'source_limit' => $limit,
      'question' => $question,
      'answer' => '',
      'passages' => [],
      'status' => 'error',
      'timestamp' => $this->time->getRequestTime(),
      'duration' => 0,
      'uid' => $this->currentUser->id(),
      'completion_plugin_id' => $completion_plugin->getPluginId(),
      'embedding_plugin_id' => $embedding_plugin->getPluginId(),
      'stats' => [
        'Get source documents' => 0,
        'Embed documents' => 0,
        'Get question embedding' => 0,
        'Get relevant passages' => 0,
        'Get answer' => 0,
      ],
    ];

    $time = microtime(TRUE);

    // Retrieve the source documents matching the document source URL.
    ['index' => $index, 'documents' => $documents] = $this->getSourceDocuments($url, $limit);
    $data['stats']['Get source documents'] = 0 - $time + ($time = microtime(TRUE));

    // If there are no documents to query, then no need to ask the AI.
    if (empty($documents)) {
      $data['answer'] = 'Sorry, no source documents were found from the source URL.';
      return $this->logAnswerData($data);
    }

    // @todo Maybe that should be done outside of the answer pipeline or in a
    // way that can help give feedback on the progress.
    $result = $this->embedDocuments($index, $documents);
    $data['stats']['Embed documents'] = 0 - $time + ($time = microtime(TRUE));

    // Abort if we were unable to process the source documents.
    // @todo maybe still proceed if some of the document could be processed?
    if (!$result) {
      $data['answer'] = 'Sorry, there was an error trying to retrieve the documents to the answer to your question.';
      return $this->logAnswerData($data);
    }

    // Generate the embedding for the question.
    $embedding = $embedding_plugin->generateEmbedding($question);
    $data['stats']['Get question embedding'] = 0 - $time + ($time = microtime(TRUE));

    // Abort if we were unable to generate the embedding for the question as
    // we cannot retrieve the relevant passages in that case.
    if (empty($embedding)) {
      $data['answer'] = 'Sorry, there was an error trying to process the qestion.';
      return $this->logAnswerData($data);
    }

    // Find document passages relevant to the question.
    $passages = $vector_store_plugin->getRelevantPassages($index, array_keys($documents), $question, $embedding);
    $data['stats']['Get relevant passages'] = 0 - $time + ($time = microtime(TRUE));

    // Abort if we could not find relevant passages to avoid generating an
    // answer without context.
    if (empty($passages)) {
      $data['answer'] = 'Sorry, no documents were found containing the answer to your question.';
      return $this->logAnswerData($data);
    }
    else {
      $data['passages'] = $passages;
    }

    // Generate the context to answer the question based on the relevant
    // passages.
    $context = $completion_plugin->generateContext($question, $passages);

    // @todo parse the answer and enrich it with the sources.
    $answer = $completion_plugin->answer($question, $context);
    $data['stats']['Get answer'] = 0 - $time + ($time = microtime(TRUE));

    // The answer is empty for example if there was an error during the request.
    if (empty($answer)) {
      $data['answer'] = 'Sorry, I was unable to answer to your question.';
      return $this->logAnswerData($data);
    }
    else {
      $data['answer'] = $answer;
    }

    // Arrived at this point we have a valid answer so we consider the request
    // successful.
    $data['status'] = 'success';

    // Try to rerank the source documents based on the similarity with both
    // the question and the answer.
    $answer_embedding = $embedding_plugin->generateEmbedding($answer);
    if (!empty($answer_embedding)) {
      // Rerank the relevant passages.
      // @todo review if multiplying is the best way to rerank.
      foreach ($data['passages'] as $index => $passage) {
        $answer_similarity = $this->cosineSimilarity($passage['embedding'], $answer_embedding) + 1.0;
        $data['passages'][$index]['score'] *= $answer_similarity;
      }
      usort($data['passages'], function ($a, $b) {
        return $b['score'] <=> $a['score'];
      });
    }

    return $this->logAnswerData($data);
  }

  /**
   * Log the answer data.
   *
   * @param array $data
   *   Answer data.
   *
   * @return array
   *   Answer data
   *
   * @see ::answer()
   */
  protected function logAnswerData(array $data): array {
    // Remove the embedding of the passages as they are not really useful
    // to have in the result or logs.
    foreach ($data['passages'] as $index => $passage) {
      unset($data['passages'][$index]['embedding']);
    }

    // Set the duration.
    $data['duration'] = $this->time->getCurrentTime() - $data['timestamp'];

    // Encode non scalar data like passages and stats.
    $fields = array_map(function ($item) {
      return is_scalar($item) ? $item : json_encode($item);
    }, $data);

    // Insert the record and retrieve the log ID. It can be used for example
    // to set the "satisfaction score" afterwards.
    $data['id'] = $this->database
      ->insert('ocha_ai_chat_logs')
      ->fields($fields)
      ->execute();

    return $data;
  }

  /**
   * Get a list of source documents for the given document source URL.
   *
   * @param string $url
   *   Document source URL.
   * @param int $limit
   *   Number of documents to retrieve.
   *
   * @return array
   *   Associative array with the index corresponding to the type of
   *   documents and the list of source documents for the source URL.
   */
  protected function getSourceDocuments(string $url, int $limit): array {
    $plugin = $this->getSourcePlugin();

    $documents = $plugin->getDocuments($url, $limit);

    // @todo allow multiple indices.
    $resource = key($documents);
    $documents = $documents[$resource] ?? [];

    if (empty($documents)) {
      $this->logger->notice(strtr('No documents found for the ReliefWeb river URL: @url', [
        '@url' => $url,
      ]));
    }

    // For the similarity search, we cannot compare vectors generated by an
    // embedding model with a vector from another embedding model even if they
    // have the same dimensions so we ensure that doesn't happen by prefixing
    // the index used to store the vectors with the embedding plugin id.
    //
    // @todo review if we really need the source ID as well. If the index
    // structure is the same between documents regardless of the source then
    // we could remove it.
    $index = implode('__', [
      $this->getEmbeddingPlugin()->getPluginId(),
      $plugin->getPluginId(),
      $resource,
    ]);

    return [
      'index' => $index,
      'documents' => $documents,
    ];
  }

  /**
   * Embed the documents retrieved from a ReliefWeb river URL.
   *
   * 1. Call RW API client to retrieve API data from RW river URL.
   * 2. Extract the text from the body + attachments.
   * 3. Split the text into passages.
   * 4. Generate embeddings for the text passages.
   * 5. Store the data in the vector database.
   *
   * @param string $index
   *   Index name.
   * @param array|null $documents
   *   Optional documents to embed. If not defined it uses the give river URL.
   *
   * @return bool
   *   TRUE if the embedding succeeded.
   *
   * @todo we may want to use a queue and do that asynchronously at some point.
   */
  public function embedDocuments(string $index, array $documents): bool {
    $vector_store_plugin = $this->getVectorStorePlugin();
    $dimensions = $this->getEmbeddingPlugin()->getDimensions();

    // Retrieve indexed documents.
    $existing = $vector_store_plugin->getDocuments($index, array_keys($documents), [
      'id',
      'date.changed',
    ]);

    // Process and index new or updated documents.
    foreach ($documents as $id => $document) {
      // Skip if the document already exists and has not changed.
      if (isset($existing[$id]) && $existing[$id]['date']['changed'] === $document['date']['changed']) {
        continue;
      }

      // Process the document contents.
      try {
        $document = $this->processDocument($document);
      }
      catch (\Exception $exception) {
        $this->logger->error(strtr('Unable to process document @url', [
          '@url' => $document['url'] ?? $id,
        ]));
        // @todo instead of aborting when there is an error indexing a document
        // log the error.
        return FALSE;
      }

      // Index the document.
      if (!$vector_store_plugin->indexDocument($index, $document, $dimensions)) {
        // @todo instead of aborting when there is an error indexing a document
        // log the error.
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Process (download, extract text, split) a document's contents.
   *
   * @param array $document
   *   Document.
   *
   * @return array
   *   Document with updated contents.
   */
  protected function processDocument(array $document): array {
    $contents = $document['contents'];

    foreach ($contents as $key => $content) {
      switch ($content['type']) {
        case 'markdown':
          $contents[$key]['pages'] = $this->processMarkdown($content['content']);
          break;

        case 'file':
          $contents[$key]['pages'] = $this->processFile($content['url'], $content['mimetype']);
          break;
      }
    }

    $document['contents'] = $contents;

    return $document;
  }

  /**
   * Process a markdown text to make it easier to split.
   *
   * @param string $text
   *   Markdown text.
   *
   * @return array
   *   List of pages with their page number and list of passages. Each passage
   *   has a text and corresponding embedding.
   */
  protected function processMarkdown(string $text): array {
    $replacements = [
      // Headings.
      '/^#{1,6}\s*([^#]+)$/um' => "$1\n\n",
      // Headings or horizontal lines or code blocks.
      '/^[=*`-]{2,}$/um' => "\n",
    ];

    $text = trim(preg_replace(array_keys($replacements), $replacements, $text));

    return [$this->processPage($text)];
  }

  /**
   * Process a file, returning a list of its pages with extracted passages.
   *
   * @param string $uri
   *   File URI.
   * @param string $mimetype
   *   MIME type of the file.
   *
   * @return array
   *   List of pages with their page number and list of passages. Each passage
   *   has a text and corresponding embedding.
   */
  protected function processFile(string $uri, string $mimetype): array {
    if (!$this->isSupportedFileType($mimetype)) {
      return [];
    }

    // Record time to execute the different steps.
    // @todo either store the stats elsewhere (log etc.) or remove.
    $stats = [
      'uri' => $uri,
      'mimetype' => $mimetype,
      'download' => 0,
      'extraction' => 0,
      'processing' => 0,
    ];

    $time = microtime(TRUE);

    // Create a temporary file to download to.
    $file = tmpfile();
    if ($file === FALSE) {
      $this->logger->error('Unable to create temporary file');
      return [];
    }

    try {
      // Download the file.
      $copy = stream_copy_to_stream(fopen($uri, 'r'), $file);
      $stats['download'] = 0 - $time + ($time = microtime(TRUE));
      if ($copy === FALSE) {
        $this->logger->error(strtr('Unable to download the file @uri.', [
          'uri' => $uri,
        ]));
      }
      else {
        // Extract the content of each page.
        $path = realpath(stream_get_meta_data($file)['uri']);
        $page_texts = $this->getTextExtractorPlugin($mimetype)->getPageTexts($path);
        $stats['extraction'] = 0 - $time + ($time = microtime(TRUE));

        // Process each page.
        if (!empty($page_texts)) {
          $pages = [];
          foreach ($page_texts as $page_number => $page_text) {
            $pages[] = $this->processPage($page_text, $page_number + 1);
          }
          $stats['processing'] = 0 - $time + ($time = microtime(TRUE));

          return $pages;
        }
      }
    }
    catch (\Exception $exception) {
      throw $exception;
    }
    finally {
      // Ensure we close the temporary file so it can be deleted.
      fclose($file);

      // @todo better logging.
      $this->logger->info(print_r($stats, TRUE));
    }

    return [];
  }

  /**
   * Process the text of a document page, extracting passages.
   *
   * @param string $content
   *   Page content.
   * @param int $page
   *   Page number (0 if irrelevant).
   *
   * @return array
   *   Associative array with the page number and passages. Each passage
   *   has a text and corresponding embedding.
   */
  protected function processPage(string $content, int $page = 0): array {
    $texts = $this->splitText(trim($content));
    $embeddings = $this->getEmbeddingPlugin()->generateEmbeddings($texts, TRUE);

    // @todo handle cases where the embedding fails. Maybe we should not store
    // the document in that case.
    $passages = [];
    foreach ($texts as $index => $text) {
      if (!empty($embeddings[$index])) {
        $passages[] = [
          'text' => $text,
          'embedding' => $embeddings[$index],
        ];
      }
    }

    return [
      'page' => $page,
      'passages' => $passages,
    ];
  }

  /**
   * Check if a file mimetype is supported.
   *
   * @param string $mimetype
   *   MIME type of the file.
   *
   * @return bool
   *   TRUE if the file type is supported.
   */
  protected function isSupportedFileType(string $mimetype): bool {
    $plugin_id = $this->getSetting([
      'plugins',
      'text_extractor',
      $mimetype,
      'plugin_id',
    ]);
    return isset($plugin_id);
  }

  /**
   * Get the completion plugin manager.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\CompletionPluginManagerInterface
   *   Completion plugin manager.
   */
  public function getCompletionPluginManager(): CompletionPluginManagerInterface {
    return $this->completionPluginManager;
  }

  /**
   * Get the embedding plugin manager.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\EmbeddingPluginManagerInterface
   *   Embedding plugin.
   */
  public function getEmbeddingPluginManager(): EmbeddingPluginManagerInterface {
    return $this->embeddingPluginManager;
  }

  /**
   * Get the source plugin manager.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\SourcePluginManagerInterface
   *   Source plugin.
   */
  public function getSourcePluginManager(): SourcePluginManagerInterface {
    return $this->sourcePluginManager;
  }

  /**
   * Get the text extractor plugin manager.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\TextExtractorPluginManagerInterface
   *   Text extractor plugin.
   */
  public function getTextExtractorPluginManager(): TextExtractorPluginManagerInterface {
    return $this->textExtractorPluginManager;
  }

  /**
   * Get the text splitter plugin manager.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\TextSplitterPluginManagerInterface
   *   Text splitter plugin.
   */
  public function getTextSplitterPluginManager(): TextSplitterPluginManagerInterface {
    return $this->textSplitterPluginManager;
  }

  /**
   * Get the vector store plugin manager.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\VectorStorePluginManagerInterface
   *   Vector store plugin manager.
   */
  protected function getVectorStorePluginManager(): VectorStorePluginManagerInterface {
    return $this->vectorStorePluginManager;
  }

  /**
   * Get the completion plugin.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\CompletionPluginInterface
   *   Completion plugin.
   */
  protected function getCompletionPlugin(): CompletionPluginInterface {
    $plugin_id = $this->getSetting(['plugins', 'completion', 'plugin_id']);
    return $this->getCompletionPluginManager()->getPlugin($plugin_id);
  }

  /**
   * Get the embedding plugin.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\EmbeddingPluginInterface
   *   Embedding plugin.
   */
  protected function getEmbeddingPlugin(): EmbeddingPluginInterface {
    $plugin_id = $this->getSetting(['plugins', 'embedding', 'plugin_id']);
    return $this->getEmbeddingPluginManager()->getPlugin($plugin_id);
  }

  /**
   * Get the source plugin.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\SourcePluginInterface
   *   Source plugin.
   */
  protected function getSourcePlugin(): SourcePluginInterface {
    $plugin_id = $this->getSetting(['plugins', 'source', 'plugin_id']);
    return $this->getSourcePluginManager()->getPlugin($plugin_id);
  }

  /**
   * Get the text extractor plugin for the given file mimetype.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\TextExtractorPluginInterface
   *   Text extractor plugin.
   */
  protected function getTextExtractorPlugin(string $mimetype): TextExtractorPluginInterface {
    $plugin_id = $this->getSetting([
      'plugins',
      'text_extractor',
      $mimetype,
      'plugin_id',
    ]);
    return $this->getTextExtractorPluginManager()->getPlugin($plugin_id);
  }

  /**
   * Get the text splitter plugin.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\TextSplitterPluginInterface
   *   Text splitter plugin.
   */
  protected function getTextSplitterPlugin(): TextSplitterPluginInterface {
    $plugin_id = $this->getSetting(['plugins', 'text_splitter', 'plugin_id']);
    return $this->getTextSplitterPluginManager()->getPlugin($plugin_id);
  }

  /**
   * Get the vector store plugin.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\VectorStorePluginInterface
   *   Vector store plugin.
   */
  protected function getVectorStorePlugin(): VectorStorePluginInterface {
    $plugin_id = $this->getSetting(['plugins', 'vector_store', 'plugin_id']);
    return $this->getVectorStorePluginManager()->getPlugin($plugin_id);
  }

  /**
   * Split a text into passages.
   *
   * @param string $text
   *   Text to split.
   *
   * @return array
   *   List of text passages.
   */
  protected function splitText(string $text): array {
    return $this->getTextSplitterPlugin()->splitText($text);
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

  /**
   * Get the default settings for the OCHA AI Chat.
   *
   * @return array
   *   The OCHA AI Chat settings.
   */
  public function getSettings(): array {
    if (!isset($this->settings)) {
      $config_defaults = $this->config->get('defaults') ?? [];

      $state_defaults = $this->state->get('ocha_ai_chat.default_settings', []);

      $this->settings = array_replace_recursive($config_defaults, $state_defaults);
    }
    return $this->settings;
  }

  /**
   * Get a setting for the OCHA AI Chat.
   *
   * @param array $keys
   *   Setting keys.
   * @param mixed $default
   *   Default.
   * @param bool $throw_if_null
   *   If TRUE and both the setting and default are NULL then an exception
   *   is thrown. Use this for example for mandatory settings.
   *
   * @return mixed
   *   The setting value for the keys or the provided default.
   *
   * @throws \Exception
   *   Throws an exception if no setting could be found (= NULL).
   */
  protected function getSetting(array $keys, mixed $default = NULL, bool $throw_if_null = TRUE): mixed {
    $settings = $this->getSettings();
    $setting = NestedArray::getValue($settings, $keys) ?? $default;
    if (is_null($setting) && $throw_if_null) {
      throw new \Exception(strtr('Missing setting @keys', [
        '@keys' => implode('.', $keys),
      ]));
    }
    return $setting;
  }

}
