<?php

namespace Drupal\tmgmt_deepl_glossary;

use Drupal\tmgmt\TranslatorInterface;

/**
 * A service for managing DeepL glossary API batch.
 */
class DeeplGlossaryApiBatch implements DeeplGlossaryApiBatchInterface {

  /**
   * {@inheritDoc}
   */
  public static function syncGlossary(TranslatorInterface $translator, array $glossary, array $entries, array &$context): void {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $deepl_glossary_storage = $entity_type_manager->getStorage('deepl_glossary');

    // Prepare glossary entries.
    $glossary_entries = [];
    /** @var array $entry */
    foreach ($entries as $entry) {
      if (isset($entry[0]) && isset($entry[1])) {
        $glossary_entries[] = [
          'subject' => $entry[0],
          'definition' => $entry[1],
        ];
      }
    }

    // Load deepl_glossary entities (if available).
    $existing_glossaries = $deepl_glossary_storage->loadByProperties(['glossary_id' => $glossary['glossary_id']]);
    if (count($existing_glossaries) > 0) {
      // Update glossary entries for existing glossary.
      $existing_glossary = reset($existing_glossaries);
      if ($existing_glossary instanceof DeeplGlossaryInterface) {
        $existing_glossary->set('entries', $glossary_entries);
        $existing_glossary->save();
      }
    }
    else {
      // Add new deepl_glossary entity with entries.
      $deepl_glossary_storage->create(
        [
          'label' => $glossary['name'],
          'glossary_id' => $glossary['glossary_id'],
          'source_lang' => strtoupper($glossary['source_lang']),
          'target_lang' => strtoupper($glossary['target_lang']),
          'tmgmt_translator' => $translator->id(),
          'ready' => $glossary['ready'],
          'entries' => $glossary_entries,
          'entries_format' => 'tsv',
          'entry_count' => $glossary['entry_count'],
        ]
      )->save();
    }

    // Add context message.
    $context['message'] = \Drupal::translation()
      ->formatPlural($glossary['entry_count'], 'Syncing glossary @glossary_name with @entry_count entry.', 'Syncing glossary @glossary_name with @entry_count entries.', [
        '@glossary_name' => $glossary['name'],
        '@entry_count' => $glossary['entry_count'],
      ]);

    // Add context results.
    $context['results']['glossaries'][] = [
      'name' => $glossary['name'],
      'entry_count' => $glossary['entry_count'],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public static function cleanupDeeplGlossaryEntities(array $deepl_glossaries, string $translator, array &$context): void {
    // Get glossary_ids.
    $deepl_glossary_ids = array_column($deepl_glossaries, 'glossary_id');

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $deepl_glossary_storage = $entity_type_manager->getStorage('deepl_glossary');
    $glossary_entities = $deepl_glossary_storage->loadByProperties(['tmgmt_translator' => $translator]);

    /** @var \Drupal\tmgmt_deepl_glossary\DeeplGlossaryInterface $glossary_entity */
    foreach ($glossary_entities as $glossary_entity) {
      // Delete non matching glossary entities.
      $glossary_id = $glossary_entity->get('glossary_id')->value;
      if ((isset($glossary_id) && !in_array($glossary_id, $deepl_glossary_ids, TRUE))) {
        $glossary_entity->delete();
      }
      // Delete glossaries without glossary_id.
      // This could be caused by an error in the creation process.
      elseif (!isset($glossary_id)) {
        $glossary_entity->delete();
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public static function syncGlossariesFinishedCallback(bool $success, array $results, array $operations): void {
    if ($success) {
      // Glossaries were found and synced.
      if (isset($results['glossaries']) && count($results['glossaries']) > 0) {
        \Drupal::messenger()->addStatus(t('DeepL glossaries were synced successfully.'));
      }
      else {
        $message = t('Could not find any glossary for syncing.');
        \Drupal::messenger()->addWarning($message);
      }
    }
    else {
      \Drupal::messenger()->addError(t('An error occured while syncing glossaries.'));
    }
  }

}
