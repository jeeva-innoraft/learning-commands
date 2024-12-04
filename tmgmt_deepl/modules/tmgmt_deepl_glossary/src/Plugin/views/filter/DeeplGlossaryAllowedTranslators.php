<?php

namespace Drupal\tmgmt_deepl_glossary\Plugin\views\filter;

use Drupal\tmgmt_deepl_glossary\Entity\DeeplGlossary;
use Drupal\views\Plugin\views\filter\ManyToOne;

/**
 * Filter based on allowed translators for deepl_glossary.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("tmgmt_deepl_glossary_allowed_translators")
 */
class DeeplGlossaryAllowedTranslators extends ManyToOne {

  /**
   * Gets the values of the options.
   *
   * @return array
   *   Returns options.
   */
  public function getValueOptions(): array {
    $this->valueOptions = DeeplGlossary::getAllowedTranslators();
    return $this->valueOptions;
  }

}
