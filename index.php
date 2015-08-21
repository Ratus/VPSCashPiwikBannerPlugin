<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);
require 'db.php';
$tool = $_GET['tool'];

//Get the tool id by name
$contentNames = mysqli_query($conn, "SELECT idaction FROM piwik_log_action WHERE name = '$tool' LIMIT 1");
if($name = mysqli_fetch_assoc($contentNames)){
    $toolid = $name['idaction'];
    $toolquery = "SELECT
                        -- v.*,
                        a.name as label,
                        v.custom_var_v1,
                        v.custom_var_v2,
                        v.custom_var_v3,
                        sum(ifnull(v.idaction_content_interaction, 0, 1)) as interaction,
                        trim(substring_index(t.name, '|', 1)) as referrer,
                        trim(substring_index(t.name, '|', -1)) as target
                      FROM
                        piwik_log_link_visit_action as v
                      JOIN
                        piwik_log_action as a
                      ON
                        a.idaction = v.idaction_content_piece
                      JOIN
                        piwik_log_action as t
                      ON
                        t.idaction = v.idaction_content_target
                      WHERE
                        v.idaction_content_name = $toolid
                      GROUP BY
                        custom_var_v1, custom_var_v2, custom_var_v3, label, referrer, target";
    $linkVisitAction = mysqli_query($conn, $toolquery);
    var_dump(mysqli_error($conn));
    if($linkVisitAction->num_rows > 0)
    {
        $dataarray = array();
        while($row = mysqli_fetch_assoc($linkVisitAction)){
            $dataarray['toolname'] = $tool;
            $dataarray['label'] = $row['label'];
            $dataarray['referrer'] = $row['referrer'];
            $dataarray['target'] = $row['target'];
            $dataarray['interaction'] = $row['interaction'];
            //$dataarray['interaction'] = isset($row['interaction'])? 1:0;
            echo '<pre>';
            print_r($row);
        }
    }
}
?>