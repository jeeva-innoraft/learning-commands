<?php

namespace Drupal\tmgmt_deepl_glossary\Entity\ViewsData;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the tmgmt_deepl_glossary entity type.
 */
class DeeplGlossaryViewsData extends EntityViewsData {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();

    // Set custom filter for source_lang.
    $data['tmgmt_deepl_glossary']['source_lang']['filter']['id'] = 'tmgmt_deepl_glossary_allowed_languages';
    // Set custom filter for target_lang.
    $data['tmgmt_deepl_glossary']['target_lang']['filter']['id'] = 'tmgmt_deepl_glossary_allowed_languages';
    // Set custom filter for tmgt_translator.
    $data['tmgmt_deepl_glossary']['tmgmt_translator']['filter']['id'] = 'tmgmt_deepl_glossary_allowed_translators';

    // Add custom filter for entries.
    $data['tmgmt_deepl_glossary']['glossary_entries'] = [
      'title' => $this->t('Filter by entries subject or definition'),
      'filter' => [
        'title' => $this->t('Filter by entries subject or definition'),
        'id' => 'tmgmt_deepl_glossary_entries',
      ],
    ];

    return $data;
  }

}
