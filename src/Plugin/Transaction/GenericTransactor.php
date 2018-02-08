<?php

namespace Drupal\transaction\Plugin\Transaction;

use Drupal\transaction\TransactorBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\transaction\TransactionInterface;

/**
 * Provides a generic transactor.
 *
 * @Transactor(
 *   id = "transaction_generic",
 *   title = @Translation("Generic"),
 *   description = @Translation("A simple multipurpose transactor."),
 *   transaction_fields = {
 *     {
 *       "name" = "log_message",
 *       "type" = "string",
 *       "title" = @Translation("Log message"),
 *       "description" = @Translation("A log message with details about the transaction."),
 *       "required" = FALSE,
 *     },
 *   },
 *   target_entity_fields = {
 *     {
 *       "name" = "last_transaction",
 *       "type" = "entity_reference",
 *       "title" = @Translation("Last transaction"),
 *       "description" = @Translation("A reference field in the target entity type to update with a reference to the last executed transaction of this type."),
 *       "required" = FALSE,
 *     },
 *   },
 * )
 */
class GenericTransactor extends TransactorBase {

  /**
   * {@inheritdoc}
   */
  public function executeTransaction(TransactionInterface $transaction, TransactionInterface $last_executed = NULL) {
    if (!parent::executeTransaction($transaction)) {
      return FALSE;
    }

    // Update the last execute transaction reference in the target entity.
    /** @var \Drupal\transaction\TransactionTypeInterface $transaction_type */
    $transaction_type = $transaction->get('type')->entity;

    $settings = $transaction_type->getPluginSettings();
    if (isset($settings['last_transaction'])
      && ($target_entity = $transaction->getTargetEntity())
      && $target_entity->hasField($settings['last_transaction'])) {
      $target_entity->get($settings['last_transaction'])->setValue($transaction);
    }

    return TRUE;
  }

}