<?php

namespace Drupal\tmgmt_deepl_glossary;

use Drupal\tmgmt\TranslatorInterface;

/**
 * Provides an interface defining DeepL glossary API batch service.
 */
interface DeeplGlossaryApiBatchInterface {

  /**
   * Sync glossary entries batch finish callback.
   *
   * @param bool $success
   *   Whether the batch run was successful.
   * @param array $results
   *   The collected results coming from batch context.
   * @param array $operations
   *   The processed operations.
   */
  public static function syncGlossariesFinishedCallback(bool $success, array $results, array $operations): void;

  /**
   * Sync a single glossary.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator.
   * @param array $glossary
   *   The unique ID assigned to the glossary.
   * @param array $entries
   *   The entries of the glossary.
   * @param array $context
   *   Context for operation.
   */
  public static function syncGlossary(TranslatorInterface $translator, array $glossary, array $entries, array &$context): void;

  /**
   * Clean up obsolete deepl_glossary entities.
   *
   * @param array $deepl_glossaries
   *   Array of DeepL glossaries provided by the API.
   * @param string $translator
   *   The name of the translator.
   * @param array $context
   *   Context for operation.
   */
  public static function cleanupDeeplGlossaryEntities(array $deepl_glossaries, string $translator, array &$context): void;

}
