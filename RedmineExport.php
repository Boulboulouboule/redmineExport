<?php
require 'Database.php';

class RedmineExport{

  public $db;

  /*
   * Connection à la db lors de l'instanciation
   */
  public function __construct($dbname="redmine", $user="root", $pass="", $host="localhost"){
    $this->db = new Database($dbname, $user, $pass, $host);
  }

  /*
   * Recherche des issues correspondantes à l'id du projet
   */
  public function getData($redmine_project_id){

    $sql =
      "SELECT I.id,
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
  public function parse($data){
    $issues = [];

    /*
     * Index des root issues
     */
    foreach($data as $key=>$issue){
      if ($issue->root_id != $data[$key-1]->root_id && $issue->root_id == $issue->id ) {
        $issues[$issue->root_id]['issue'] = $issue;
      }
    }

    /*
     * Index des issues et parent
     */
    foreach($data as $key=>$issue){
      if (!empty($issue->parent_id)) {
        if($issue->id == $issue->parent_id){
          $issues[$issue->root_id]['childrens'][$issue->id]['issue'] = $issue;

        } elseif($issue->root_id == $issue->id || $issue->root_id == $issue->parent_id) {

        } else {
          $issues[$issue->root_id]['childrens'][$issue->parent_id]['childrens'][$issue->id] = $issue;

        }
      }
    }

    return $issues;
  }

  public function render($data){
      $this->parse($data);
        // echo "<ul>";
        // echo "<li><em>Lot : " . $issue->root . "</em></li>";
        // echo "<ul>";
        // echo "<li><em>Parent : " . $issue->parent . "</em></li>";
        // echo "<ul>";
        // echo "<li><h2>" . $issue->name . "</h2></li>";
        // echo "<a href='http://redmine.actency.fr/issues/". $issue->id . "'>http://redmine.actency.fr/issues/". $issue->id . "</a>";
        // echo "</ul>";
        // echo "</ul>";
        // echo "</ul>";
        // echo "<p>" . $issue->description . "</p>";
        // echo "<p>" . $issue->comments . "</p>" . "<hr><br>";

  }

}
