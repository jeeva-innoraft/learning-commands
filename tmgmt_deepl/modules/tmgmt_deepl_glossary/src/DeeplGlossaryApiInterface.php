<?php

namespace Drupal\tmgmt_deepl_glossary;

use Drupal\tmgmt\TranslatorInterface;

/**
 * Provides an interface defining DeepL glossary API service.
 */
interface DeeplGlossaryApiInterface {

  /**
   * Set translator for all glossary API calls.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator entity.
   */
  public function setTranslator(TranslatorInterface $translator): void;

  /**
   * Get all glossaries for active translator.
   *
   * @return array
   *   Array of available glossaries.
   */
  public function getGlossaries(): array;

  /**
   * Get entries for a given glossary id.
   *
   * @param string $glossary_id
   *   The unique ID assigned to the glossary.
   *
   * @return array
   *   Array of glossary entries.
   */
  public function getGlossaryEntries(string $glossary_id): array;

  /**
   * Delete glossary for a given glossary id.
   *
   * @param string $glossary_id
   *   The unique ID assigned to the glossary.
   */
  public function deleteGlossary(string $glossary_id): void;

  /**
   * Create new glossary.
   *
   * @param string $name
   *   Name to be associated with the glossary.
   * @param string $source_lang
   *   The language in which the source texts in the glossary are specified.
   * @param string $target_lang
   *   The language in which the target texts in the glossary are specified.
   * @param string $entries
   *   The entries of the glossary.
   * @param string $entries_format
   *   The format in which the glossary entries are provided (default: 'tsv').
   *
   * @return array
   *   Array with results after creating a glossary.
   */
  public function createGlossary(string $name, string $source_lang, string $target_lang, string $entries, string $entries_format = 'tsv'): array;

  /**
   * Make API requests.
   *
   * @param string $url
   *   The url for the request.
   * @param string $method
   *   HTTP method of the API request (can be GET or POST).
   * @param array $query_params
   *   Query params to be passed into the request.
   * @param array $headers
   *   Additional headers for request.
   *
   * @return array|null
   *   Array with results of the request.
   */
  public function doRequest(string $url, string $method, array $query_params, array $headers): ?array;

  /**
   * Build glossary sync batch for available deepl translators.
   */
  public function buildGlossariesSyncBatch(): void;

}
