<?php
require 'Database.php';

class RedmineExport{

  public $db;

  public function __construct($dbname="redmine", $user="root", $pass="", $host="localhost"){
    $this->db = new Database($dbname, $user, $pass, $host);
  }

  public function getData($redmine_project_id, $redmine_project_name){

    $sql =
      "SELECT I.id,
       I.subject AS name,
       I.description AS issue_descr,
       I.parent_id,
       T.name AS tracker,
       CONCAT( 'Lien redmine : http://http://redmine.actency.fr/projects/ultra-tuning/issues/', I.Id, '\n\n', I.description, '\n\n',
                ( SELECT IFNULL ( GROUP_CONCAT( CONCAT( 'Le ', j.created_on, ' ',
                                                          ( SELECT CONCAT (uj.firstname, ' ', uj.lastname)
                                                          FROM users uj
                                                          WHERE u.id = j.user_id LIMIT 1 ), ' à écrit :\n', j.notes, '\n' ) ), ' ' )
                 FROM journals j
                 WHERE j.journalized_id = I.id
                   AND j.journalized_type = 'Issue'
                   AND TRIM(j.notes) != '' ) ) AS description,
       CONCAT(( SELECT CONCAT ('#', id, ' : ', subject)
                 FROM issues
                 WHERE parent_id = I.id LIMIT 1 ) ) AS parent,
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
    // echo "<h1>" .  . "</h1>";
      foreach($data as $issue){
        echo "<em>" . $issue->parent . "</em><h2>" . $issue->name . "</h2>";
        echo "<p>" . $issue->description . "</p>" . "<hr><br>";
      }

  }

}
