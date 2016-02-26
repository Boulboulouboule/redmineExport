<?php
require 'Database.php';

class RedmineExport{

  public $db;

  public function __construct($dbname="redmine", $user="root", $pass="", $host="localhost"){
    $this->db = new Database($dbname, $user, $pass, $host);
  }

  public function getData($redmine_project_id){

    $sql =
      "SELECT I.id,
       I.subject AS name,
       I.description AS issue_descr,
       I.root_id,
       I.parent_id,
       T.name AS tracker,
       CONCAT( '<a href=\'http://redmine.actency.fr/issues/', I.Id, '\'>Lien redmine</a><br><br>', I.description, '<br><br>',
                ( SELECT IFNULL ( GROUP_CONCAT( CONCAT( 'Le ', j.created_on, ' ',
                                                          ( SELECT CONCAT (uj.firstname, ' ', uj.lastname)
                                                          FROM users uj
                                                          WHERE u.id = j.user_id LIMIT 1 ), ' à écrit :<br>', j.notes, '<br>' ) ), ' ' )
                 FROM journals j
                 WHERE j.journalized_id = I.id
                   AND j.journalized_type = 'Issue'
                   AND TRIM(j.notes) != '' ) ) AS description,
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
      ORDER BY 1";

    $data = $this->db->query($sql);
    return $data;
  }

  public function render($data){
     echo "<h1>Redmine export</h1>";
      foreach($data as $issue){
        echo "<ul>";
        echo "<li><em>Lot : " . $issue->root . "</em></li>";
        echo "<ul>";
        echo "<li><em>Parent : " . $issue->parent . "</em></li>";
        echo "<ul>";
        echo "<li><h2>" . $issue->name . "</h2></li>";
        echo "</ul>";
        echo "</ul>";
        echo "</ul>";
        echo "<p>" . $issue->description . "</p>" . "<hr><br>";
      }

  }

}
