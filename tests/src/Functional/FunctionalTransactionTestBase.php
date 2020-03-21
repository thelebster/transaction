<?php

namespace Drupal\Tests\transaction\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Base class for functional tests for the fixed block content.
 */
abstract class FunctionalTransactionTestBase extends BrowserTestBase {

  /**
   * The default theme for browser tests.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'field_ui',
    // @todo Remove once https://www.drupal.org/project/transaction/issues/3118352 is solved
    'options',
    'block',
    'filter',
    'text',
    'entity_test',
    'dynamic_entity_reference',
    'transaction',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create a basic bundle on the entity test type.
    entity_test_create_bundle('basic', 'Basic');
  }

}
