<?php

namespace Drupal\joinup_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Unicode;
use Drupal\migrate\Row;

/**
 * Provides a 'document' node migration source plugin.
 *
 * @MigrateSource(
 *   id = "document"
 * )
 */
class Document extends NodeBase {

  use CountryTrait;
  use FileUrlFieldTrait;
  use KeywordsTrait;
  use LicenceTrait;
  use StateTrait;

  /**
   * {@inheritdoc}
   */
  protected $uriProperties = ['original_url'];

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'collection' => $this->t('Collection'),
      'document_type' => $this->t('Document type'),
      'publication_date' => $this->t('Publication date'),
      'original_url' => $this->t('Original URL'),
      'field_file' => $this->t('File'),
      'keywords' => $this->t('Keywords'),
      'country' => $this->t('Spatial coverage'),
      'licence' => $this->t('Licence'),
      'state' => $this->t('State'),
    ] + parent::fields();
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('d8_document', 'n')->fields('n', [
      'document_type',
      'publication_date',
      'original_url',
      'policy_context',
      'desc_target_users_groups',
      'desc_implementation',
      'tech_solution',
      'technology_choice',
      'main_results',
      'roi_desc',
      'track_record_sharing',
      'lessons_learnt',
      'scope',
      'return_investment',
      'case_sector',
      'target_users_or_group',
      'factsheet_topic',
      'presentation_nature_of_doc',
      'state',
      'acronym',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $nid = $row->getSourceProperty('nid');
    $vid = $row->getSourceProperty('vid');
    $document_type = $row->getSourceProperty('document_type');

    if ($acronym = $row->getSourceProperty('acronym')) {
      // Because of the Case title suffix, the length might exceed 255 chars.
      $title = $row->getSourceProperty('title');
      $title_length = Unicode::strlen($title);
      if ($title_length + Unicode::strlen($acronym) <= 252) {
        $title .= " ($acronym)";
      }
      else {
        // Space, opening and closing parenthesis and ellipsis.
        $acronym_length = 255 - $title_length - 4;
        $title .= ' (' . Unicode::substr($acronym, 0, $acronym_length) . "…)";
      }
      $row->setSourceProperty('title', $title);
    }

    // Resolve 'field_file'.
    $table = "d8_file_document_{$document_type}";
    if ($this->getDatabase()->schema()->tableExists($table)) {
      $file_source_id_values = $this->select($table)
        ->fields($table, ['fid'])
        ->condition("$table.nid", $nid)
        ->execute()
        ->fetchAll();
      $file_migration = "file:document_{$document_type}";
      $urls = $row->getSourceProperty('original_url') ? [$row->getSourceProperty('original_url')] : [];
      $this->setFileUrlTargetId($row, 'field_file', $file_source_id_values, $file_migration, $urls, JoinupSqlBase::FILE_URL_MODE_MULTIPLE);
    }

    // Keywords.
    $this->setKeywords($row, 'keywords', $nid, $vid);
    // Append keywords additions.
    $keywords = $row->getSourceProperty('keywords') ?: [];
    foreach (static::$keywordsAdditions as $property) {
      $addition = $row->getSourceProperty($property);
      if (!empty($addition)) {
        $addition = array_map('trim', explode('|', $addition));
        // Add only new terms.
        $keywords = array_merge($keywords, array_diff($addition, $keywords));
      }
    }
    if ($keywords) {
      $row->setSourceProperty('keywords', $keywords);
    }

    // Spatial coverage.
    $row->setSourceProperty('country', $this->getCountries([$vid]));

    // Append body additions.
    $body = trim($row->getSourceProperty('body')) . "\n";
    foreach (static::$bodyAdditions as $section => $data) {
      $value = [];
      $pattern = $data['pattern'];
      foreach ($data['fields'] as $field_name) {
        $field = trim($row->getSourceProperty($field_name));
        if (!empty($field)) {
          $value[] = $field;
        }
      }
      if ($value) {
        $body .= str_replace(['@title', '@value'], [
          $section,
          implode("\n", $value),
        ], $pattern);
      }
    }
    $row->setSourceProperty('body', trim($body));

    // State.
    $this->setState($row);

    // Licence.
    $query = $this->select('term_node', 'tn');
    $query->join('term_data', 'td', 'tn.tid = td.tid');
    $licence = $query
      ->fields('td', ['name'])
      ->condition('tn.nid', $nid)
      ->condition('tn.vid', $vid)
      // The License of document vocabulary vid is 56.
      ->condition('td.vid', 56)
      ->execute()
      ->fetchField();
    $row->setSourceProperty('licence', $licence ?: NULL);
    $this->setLicence($row, 'document');

    return parent::prepareRow($row);
  }

  /**
   * Body additions structure.
   *
   * @var array
   */
  protected static $bodyAdditions = [
    'Policy Context' => [
      'pattern' => "<h2>@title</h2>\n@value\n",
      'fields' => ['policy_context'],
    ],
    'Description of target users and groups' => [
      'pattern' => "<h2>@title</h2>\n@value\n",
      'fields' => ['desc_target_users_groups'],
    ],
    'Description of the way to implement the initiative' => [
      'pattern' => "<h2>@title</h2>\n@value\n",
      'fields' => ['desc_implementation'],
    ],
    'Technology solution' => [
      'pattern' => "<h2>@title</h2>\n@value\n",
      'fields' => ['tech_solution', 'technology_choice'],
    ],
    'Main results, benefits and impacts' => [
      'pattern' => "<h2>@title</h2>\n@value\n",
      'fields' => ['main_results'],
    ],
    'Return on investment' => [
      'pattern' => "<h2>@title</h2>\n@value\n",
      'fields' => ['roi_desc', 'return_investment'],
    ],
    'Track record of sharing' => [
      'pattern' => "<h2>@title</h2>\n@value\n",
      'fields' => ['track_record_sharing'],
    ],
    'Lessons learnt' => [
      'pattern' => "<h2>@title</h2>\n@value\n",
      'fields' => ['lessons_learnt', 'scope'],
    ],
  ];

  /**
   * Keywords additional fields.
   *
   * @var array
   */
  protected static $keywordsAdditions = [
    'case_sector',
    'target_users_or_group',
    'factsheet_topic',
    'presentation_nature_of_doc',
  ];

}
