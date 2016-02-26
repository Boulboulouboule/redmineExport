<?php
require "RedmineExport.php";

$redmine = new RedmineExport();

$issues = $redmine->getData(176);
$redmine->render($issues);
