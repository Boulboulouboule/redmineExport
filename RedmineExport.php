<?php
require 'Database.php';

class RedmineExport {

  public $db;

  /*
   * Connection à la db lors de l'instanciation
   */
  public function __construct($dbname = "redmine", $user = "root", $pass = "", $host = "localhost") {
    $this->db = new Database($dbname, $user, $pass, $host);
  }

  /*
   * Recherche des issues correspondantes à l'id du projet
   */
  public function getData($redmine_project_id) {

    $sql = "SELECT I.id,
       I.subject AS name,
       I.description AS description,
       I.root_id,
       I.parent_id,
       T.name AS tracker,
       CONCAT(( SELECT IFNULL (
         GROUP_CONCAT(
           CONCAT(
             'Le ', j.created_on, ' ',
              ( SELECT CONCAT (uj.firstname, ' ', uj.lastname)
              FROM users uj
              WHERE u.id = j.user_id LIMIT 1 ), ' à écrit :<br>', j.notes, '<br><br>' ) ), ' ' )
                 FROM journals j
                 WHERE j.journalized_id = I.id
                   AND j.journalized_type = 'Issue'
                   AND TRIM(j.notes) != '' ) ) AS comments,
       CONCAT(( SELECT CONCAT ('#', id, ' : ', subject)
                 FROM issues
                 WHERE id = I.parent_id LIMIT 1 ) ) AS parent,
       CONCAT(( SELECT CONCAT ('#', id, ' : ', subject)
                 FROM issues
                 WHERE id = I.root_id LIMIT 1 ) ) AS root,
       u.login AS assignee,
       u2.login AS reporter,
       I.start_date
      FROM issues I
      LEFT JOIN users u ON I.assigned_to_id = u.id
      LEFT JOIN users u2 ON I.author_id = u2.id,
                      issue_statuses S,
                      trackers T
      WHERE I.project_id = " . $redmine_project_id . "
      AND S.id = I.status_id
      AND T.id = I.tracker_id
      GROUP BY root_id, parent_id, id
      ORDER BY root_id, parent_id, id";

    $data = $this->db->query($sql);
    return $data;
  }

  /**
   * Classement des issues dans un array.
   * @param $data = $issues de getData()
   * @return array imbriqué :
   * $issues[root_id]['childrens'][parent_id]['childrens]['issue_id']
   */
  public function parse($data) {
    $issues = [];

    /*
     * Index des root issues
     */
    foreach($data as $key => $issue) {
      if($key != 0) {
        if($issue->root_id != $data[$key - 1]->root_id && $issue->root_id == $issue->id) {
          $issues[$issue->root_id]['issue'] = $issue;
        }
      }else {
        $issues[$issue->root_id]['issue'] = $issue;

      }
    }

    /*
     * Index des issues et parent
     */
    foreach($data as $key => $issue) {
      if(!empty($issue->parent_id)) {
        if($issue->id == $issue->parent_id) {
          $issues[$issue->root_id]['childrens'][$issue->id]['issue'] = $issue;

        }elseif($issue->parent_id == $issue->id){
          $issues[$issue->root_id]['childrens'][$issue->parent_id]['childrens'][$issue->id] = $issue;
        }else {
          $issues[$issue->root_id]['childrens'][$issue->parent_id]['childrens'][$issue->id] = $issue;

        }
      }
    }

    /*
     * Ajout des infos aux parents
     */
    foreach($data as $old) {
      foreach($issues as $new){
        if(empty($new[$old->root_id]['childrens'][$old->id]) && $old->root_id != $old->id){
          $issues[$old->root_id]['childrens'][$old->id]['issue'] = $old;
        }
      }
    }
    return $issues;
  }

  public function render($data, $lot = 'all') {
    $issues = $this->parse($data);
    if(!is_array($lot)){
      strtolower($lot);
      $condition = explode(',', (trim($lot)));
    } else {
      array_walk($lot, function(&$value){
          $value = strtolower($value);
      });
      $condition = $lot;
    }
//    var_dump($condition);
    foreach($issues as $root) {
      if(in_array(strtolower($root['issue']->name), $condition) || strtolower($lot == 'all')){
        echo "<ul>";
        echo "<li><h2><a href='http://redmine.actency.fr/issues/" . $root['issue']->id . "'>Lot " . $root['issue']->root . "</a></h2></li>";
        echo "<pre>" . $root['issue']->description . "</pre>";
        echo "<pre>" . $root['issue']->comments . "</pre>" . "<br>";
        if(!empty($root['childrens'])){
          foreach($root['childrens'] as $parent) {
            if(!empty($parent['issue'])) {
              echo "<ul>";
              echo "<li><h3>Parent : #" . $parent['issue']->id. " : " .$parent['issue']->name . "</h3></li>";
              echo "<a href='http://redmine.actency.fr/issues/" . $parent['issue']->id . "'>http://redmine.actency.fr/issues/" . $parent['issue']->id . "</a>";
              echo "<pre>" . $parent['issue']->description . "</pre>";
              echo "<pre>" . $parent['issue']->comments . "</pre>" . "<br>";
            }
            if(!empty($parent['childrens'])){
              foreach($parent['childrens'] as $child) {
                echo "<ul>";
                echo "<li><h4>" . $child->name . "</h4></li>";
                echo "<a href='http://redmine.actency.fr/issues/" . $child->id . "'>http://redmine.actency.fr/issues/" . $child->id . "</a>";
                echo "<pre>" . $child->description . "</pre>";
                echo "<pre>" . $child->comments . "</pre>" . "<hr><br>";
                echo "</ul>";

              }

            }
            if(!empty($parent['issue'])) {
              echo "</ul>";
            }
          }
        }
        echo "</ul>";

      }

    }

  }

}
