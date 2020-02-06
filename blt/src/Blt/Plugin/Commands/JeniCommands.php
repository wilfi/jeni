<?php

namespace Example\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

/**
 * This class defines example hooks.
 */
class JeniCommands extends BltTasks {

  /**
   * This will be called before the `drupal:config:import` command is executed.
   *
   * @hook command-event drupal:config:import
   */
  public function preConfigImport(ConsoleCommandEvent $event) {
    $this->say("preCommandMessage hook: About to delete the default entity shortcut_set of type default.");
    $entities = [
      [
        'type' => 'shortcut_set',
        'bundle' => 'default',
      ],

    ];
    foreach ($entities as $entity) {
      $bundle = $entity['bundle'];
      $type = $entity['type'];
      $task = $this->taskDrush()->stopOnFail()->drush("ev '\Drupal::entityManager()->getStorage(\"$type\")->load(\"$bundle\")->delete()'");
      $result = $task->run();
      if (!$result->wasSuccessful()) {
        throw new BltException("Failed delete entity shortcut_set of type default.");
      }
    }

  }

  /**
   * @hook replace-command artifact:ac-hooks:db-scrub
   *
   * Override user data sanitization.
   */
  public function dbScrubOverride($site, $target_env, $db_name, $source_env) {
    $this->say("User data not sanitized for environment  $target_env");
    $this->taskDrush()
      ->drush("cr")
      ->run();
  }

}
