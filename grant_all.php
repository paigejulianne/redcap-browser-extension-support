<?php
global $module;
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

if (!SUPER_USER) die('You must be a super user to run this script');

?>
    <h5>REDCap Browser Extension Support</h5>
<p>
<?php

// Fetch all usernames on the system
$sql = "SELECT username FROM redcap_user_information";
$q = $module->query($sql, []);
while ($row = $q->fetch_assoc()) {
    $sql2 = "SELECT username FROM redcap_user_rights WHERE username = ? AND project_id = ?";
    $q2 = $module->query($sql2, [$row['username'], PROJECT_ID]);
    $row2 = $q2->fetch_assoc();
    if (!$row2) {
        // Grant access to this project (no API token needed for the extension)
        $sql3 = 'INSERT INTO redcap_user_rights (username, project_id) VALUES (?, ?)';
        $module->query($sql3, [$row['username'], PROJECT_ID]);
        echo "Granted project access to " . $module->escape($row['username']) . "<br>";
    } else {
        echo $module->escape($row['username']) . " already has access<br>";
    }
}
?>
</p>
<p>All operations complete. Users can now visit the <strong>Browser Extension Configuration</strong> page in this project to set up their extension.</p>

<?php require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';