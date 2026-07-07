<?php
namespace METRC\BrowserExtension;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;


class BrowserExtension extends AbstractExternalModule {

    public function redcap_module_link_check_display($project_id, $link) {
        if ($this->getAPIToken(USERID, $project_id)) return $link;
    }

    public function getAPIToken($user, $project_id) {
        $sql = "SELECT api_token FROM redcap_user_rights WHERE username = ? AND project_id = ? AND api_token IS NOT NULL";
        $q = $this->query($sql, [$user, $project_id]);
        $row = $q->fetch_assoc();
        return ($row['api_token']) ?? false;
    }

    public function validateAPIToken($project_id, $api_token) {
        $sql = "SELECT username FROM redcap_user_rights WHERE project_id = ? AND api_token = ?";
        $q = $this->query($sql, [$project_id, $api_token]);
        $row = $q->fetch_assoc();
        return ($row['username']) ?? false;
    }

    public function getAllProjects($username, $term) {
        $username = $this->escape($username);
        $term = $this->escape($term);
        $userAdmin = $this->isUserAdmin($username);
        $userQuery = "SELECT redcap_user_rights.project_id, redcap_projects.app_title FROM redcap_user_rights, redcap_projects 
                            WHERE redcap_user_rights.project_id = redcap_projects.project_id AND redcap_projects.app_title LIKE ?";
        $params = ["%$term%"];
        if (!$userAdmin) {
            $userQuery .= " AND  redcap_user_rights.username = ?";
            $params[] = $username;
        }
        $userQuery .= " GROUP BY redcap_projects.project_id";
        $userQuery .= " ORDER BY redcap_projects.app_title ASC";
        $q = $this->query($userQuery, $params);
        $projects = array();
        while ($row = $q->fetch_assoc()) {
            $projects[] = $row['project_id'];
        }
        return $projects;
    }

    public function getProjectData($pid) {
        $sql = "SELECT project_id as `value`, app_title as `label` FROM redcap_projects WHERE project_id = ?";
        $q = $this->query($sql, [$pid]);
        $row = $q->fetch_assoc();
        $row['label'] = $this->escape($row['label']);
        return $row;
    }

    public function getProjectPerms($username, $pid) {
        $sql = "SELECT user_rights, design FROM redcap_user_rights WHERE username = ? AND project_id = ?";
        $q = $this->query($sql, [$username, $pid]);
        $row = $q->fetch_assoc();
        return $row;
    }

    public function isUserAdmin($username) {
        $sql = "SELECT * FROM redcap_user_information WHERE username = ? AND super_user = '1'";
        $q = $this->query($sql, [$username]);
        $row = $q->fetch_assoc();
        return ($row) ? true : false;
    }

    public function getConfigurationKey($user, $project_id) {
        global $redcap_base_url;
        $api_token = $this->getAPIToken($user, $project_id);
        $configuration_key = $this->escape($redcap_base_url . '|' . '|' . '|' . $project_id . '|' . $api_token . '|');
        return $configuration_key;
    }

    public function escape($string) {
        return ExternalModules::escape($string);
    }

    public function redcap_module_api($action, $payload, $project_id, $user_id, $format, $returnFormat, $csvDelim) {
        $api_token = $payload['api_token'] ?? $_GET['api_token'] ?? null;
        $pid = defined('PROJECT_ID') ? PROJECT_ID : ($payload['pid'] ?? $_GET['pid'] ?? null);

        if (in_array($action, ['projects', 'extraconfig', 'newrec'])) {
            $username = $this->validateApiToken($pid, $api_token);
            if (!$username) {
                return $this->framework->apiErrorResponse('Invalid API Token', 401);
            }
        }

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token');

        if ($action === 'projects') {
            $term = $payload['term'] ?? $_GET['term'] ?? '';
            $projects = $this->getAllProjects($username, $term);
            $projectData = array();
            foreach ($projects as $proj_id) {
                $projectData[] = $this->escape($this->getProjectData($proj_id));
            }
            return $this->framework->apiJsonResponse($projectData);
        }

        if ($action === 'extraconfig') {
            global $redcap_version;
            $returnData = [];
            $returnData['system_admin'] = $this->isUserAdmin($username);
            $returnData['redcap_version'] = $redcap_version;

            $allProjects = $this->getAllProjects($username, '%');
            foreach($allProjects as $proj_id) {
                $returnData['project_data'][$proj_id] = $this->escape($this->getProjectPerms($username, $proj_id));
            }
            return $this->framework->apiJsonResponse($returnData);
        }

        if ($action === 'newrec') {
            $target_project = $this->escape($payload['target_project'] ?? $_REQUEST['target_project'] ?? '');
            
            $sql = "SELECT MAX(record) AS max_record FROM redcap_data WHERE project_id = ? GROUP BY record ORDER BY record DESC LIMIT 1";
            $result = $this->query($sql, [$target_project]);
            $row = $result->fetch_assoc();
            $max_record = $this->escape($row['max_record']);

            header("Location: " . APP_PATH_WEBROOT . "DataEntry/record_home.php?auto=1&pid=$target_project&id=" . ($max_record + 1));
            exit();
        }
    }

}