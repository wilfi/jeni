<?php

namespace Drupal\exercise_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\Core\Routing\CurrentRouteMatch;

/**
 * Provides a 'RelatedContents' block.
 *
 * @Block(
 *  id = "related_contents",
 *  admin_label = @Translation("Related Contents"),
 * )
 */
class RelatedContents extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory object.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routematch;

  /**
   * The config factory object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $current_user;

  /**
   * The config factory object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
        $configuration,
        $plugin_id,
        $plugin_definition,
        $container->get('current_route_match'),
        $container->get('current_user'),
        $container->get('entity_type.manager')
    );
  }

  /**
   * Constructs the RelatedContents Block.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   The config factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The config factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CurrentRouteMatch $route_match, AccountInterface $current_user, EntityTypeManager $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routematch = $route_match;
    $this->current_user = $current_user;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $current_user = $this->current_user->id();
    $same_author_category = [];
    $diff_category_same_author = [];
    $diff_author_category = [];
    $diff_author_diff_category = [];
    $node = $this->routematch->getParameter('node');
    if ($node instanceof NodeInterface && $node->getType() == 'article') {
      $current_nid = $node->id();
      $current_field_category = $node->get('field_category')->getValue()[0]['target_id'];
      $entity_storage = $this->entityTypeManager->getStorage('node');
      $query = $entity_storage->getQuery();
      $query->condition('status', 1);
      $query->condition('type', 'article');
      $query->condition('nid', $current_nid, '<>')
        ->addTag('category');
      $entity_cat = $query->execute();

      $entities = $this->entityTypeManager->getStorage('node')->loadMultiple($entity_cat);
      foreach ($entities as $entity) {
        $author = $entity->getOwner();
        $author_id = $author->id();
        $field_category = $entity->get('field_category')->getValue()[0]['target_id'];

        // Diff category with same author.
        if ($current_user == $author_id && $current_field_category !== $field_category) {
          $url = Url::fromRoute('entity.node.canonical', ['node' => $entity->id()])->toString();
          $entity_id = ['label' => $entity->label(), 'url' => $url];
          array_push($diff_category_same_author, $entity_id);
        }

        // Same category with same author.
        if ($current_user == $author_id && $current_field_category == $field_category) {
          $url = Url::fromRoute('entity.node.canonical', ['node' => $entity->id()])->toString();
          $entity_id = ['label' => $entity->label(), 'url' => $url];
          array_push($same_author_category, $entity_id);
        }

        // Same category with diff author.
        if ($current_user !== $author_id && $current_field_category == $field_category) {
          $url = Url::fromRoute('entity.node.canonical', ['node' => $entity->id()])->toString();
          $entity_id = ['label' => $entity->label(), 'url' => $url];
          array_push($diff_author_category, $entity_id);
        }

        // Diff category with diff author.
        if ($current_user !== $author_id && $current_field_category !== $field_category) {
          $url = Url::fromRoute('entity.node.canonical', ['node' => $entity->id()])->toString();
          $entity_id = ['label' => $entity->label(), 'url' => $url];
          array_push($diff_author_diff_category, $entity_id);
        }

      }
    }

    // Render the output to related-contents.html.twig.
    $output = [
      '#theme' => 'related_contents',
      '#same_author' => $same_author_category,
      '#diff_category' => $diff_category_same_author,
      '#diff_author' => $diff_author_category,
      '#diff_author_diff_cat' => $diff_author_diff_category,
    ];

    return $output;
  }

}
