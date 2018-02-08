<?php

namespace Drupal\transaction;

use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Defines the interface for transactor plugins.
 */
interface TransactorPluginInterface extends PluginFormInterface, ConfigurablePluginInterface, ContainerFactoryPluginInterface {

  /**
   * Validates a transaction for its execution.
   *
   * @param \Drupal\transaction\TransactionInterface $transaction
   *   The transaction to execute.
   *
   * @return bool
   *   TRUE if transaction is in proper state to be executed, FALSE otherwise.
   */
  public function validateTransaction(TransactionInterface $transaction);

  /**
   * Executes a transacion.
   *
   * Note that this method does not validates the transaction status prior to
   * its execution.
   *
   * @param \Drupal\transaction\TransactionInterface $transaction
   *   The transaction to execute.
   * @param \Drupal\transaction\TransactionInterface $last_executed
   *   The last executed transaction with the same type and target. Empty if
   *   this is the first one.
   *
   * @return bool
   *   TRUE if transaction was executed successful, FALSE otherwise.
   */
  public function executeTransaction(TransactionInterface $transaction, TransactionInterface $last_executed = NULL);

  /**
   * Compose a human readable description for the given transaction.
   *
   * @param \Drupal\transaction\TransactionInterface $transaction
   *   The transaction to describe.
   * @param string $langcode
   *   (optional) For which language the transaction description should be
   *   composed, defaults to the current content language.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   A string or translatable markup with the generated description.
   */
  public function getTransactionDescription(TransactionInterface $transaction, $langcode = NULL);

  /**
   * Compose human readable details for the given transaction.
   *
   * @param \Drupal\transaction\TransactionInterface $transaction
   *   The transaction to detail.
   * @param string $langcode
   *   (optional) For which language the transaction details should be
   *   composed, defaults to the current content language.
   *
   * @return array
   *   An array of strings and/or translatable markup objects representing each
   *   one a line detailing the transaction. Empty array if no details
   *   generated.
   */
  public function getTransactionDetails(TransactionInterface $transaction, $langcode = NULL);

  /**
   * Compose a messsage with execution indications for the given transaction.
   *
   * This message is commonly shown to the users upon transaction execution.
   *
   * @param \Drupal\transaction\TransactionInterface $transaction
   *   The pending transaction to compose indications about.
   * @param string $langcode
   *   (optional) For which language the execution indications should be
   *   composed, defaults to the current content language.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   A string or translatable markup with the generated message.
   */
  public function getExecutionIndications(TransactionInterface $transaction, $langcode = NULL);

}