<?php

namespace Drupal\tmgmt_deepl_glossary\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for deepl_glossary entity.
 *
 * @ingroup tmgmt_deepl_glossary
 */
class DeeplGlossaryListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the contact list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader(): array {
    $header = [];
    $header['name'] = $this->t('Name');
    $header['glossary_id'] = $this->t('Glossary Id');
    $header['entry_count'] = $this->t('Entries');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row = [];
    /** @var \Drupal\tmgmt_deepl_glossary\Entity\DeeplGlossary $entity */
    $row['name'] = $entity->label() . ' (' . $entity->getSourceLanguage() . ' > ' . $entity->getTargetLanguage() . ')';
    $row['glossary_id'] = $entity->getGlossaryId();
    $row['entry_count'] = $entity->getEntryCount();
    return $row + parent::buildRow($entity);
  }

}
