# Export et rendu des issues d'une base de données redmine

## Utilisation :

require "RedmineExport.php";

$redmine = new RedmineExport();

$issues = $redmine->getData(id_du_projet);
$redmine->render($issues, ['Nom lot 1', 'Nom lot 2';]);
