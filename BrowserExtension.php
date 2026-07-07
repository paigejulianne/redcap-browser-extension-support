<?php
namespace METRC\BrowserExtension;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;


class BrowserExtension extends AbstractExternalModule {

    /**
     * Show the extension config link to all users who have access to the project.
     * No longer requires an API token — extension tokens are generated on demand.
     */
    public function redcap_module_link_check_display($project_id, $link) {
        return $link;
    }

    /**
     * Generate or retrieve an extension-specific authentication token for a user.
     * Tokens are stored as project settings, keyed by a forward mapping (token -> user)
     * and a reverse mapping (user -> token) for efficient lookup in both directions.
     */
    public function getOrCreateExtensionToken($username) {
        // Check for an existing token via the reverse mapping
        $existing = $this->getProjectSetting("ext_user_" . $username);
        if ($existing) {
            // Verify the forward mapping still exists
            $data = $this->getProjectSetting("ext_token_" . $existing);
            if ($data) return $existing;
        }

        // Generate a new 32-character hex token (128 bits of entropy)
        $token = bin2hex(random_bytes(16));
        $this->setProjectSetting("ext_token_" . $token, json_encode([
            'username' => $username,
            'created' => time()
        ]));
        $this->setProjectSetting("ext_user_" . $username, $token);
        return $token;
    }

    /**
     * Validate an extension token and return the associated username, or false.
     */
    public function validateExtensionToken($token) {
        $data = $this->getProjectSetting("ext_token_" . $token);
        if (!$data) return false;
        $parsed = json_decode($data, true);
        return $parsed['username'] ?? false;
    }

    /**
     * Revoke a user's extension token.
     */
    public function revokeExtensionToken($username) {
        $existing = $this->getProjectSetting("ext_user_" . $username);
        if ($existing) {
            $this->removeProjectSetting("ext_token_" . $existing);
            $this->removeProjectSetting("ext_user_" . $username);
        }
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

    /**
     * Build the configuration key for the browser extension.
     * New format: {baseUrl}|{project_id}|{extension_token}
     * (replaces the old 6-field format that exposed the REDCap API token)
     */
    public function getConfigurationKey($username, $project_id) {
        global $redcap_base_url;
        $token = $this->getOrCreateExtensionToken($username);
        return $this->escape($redcap_base_url . '|' . $project_id . '|' . $token);
    }

    public function escape($string) {
        return ExternalModules::escape($string);
    }

    /**
     * Handle API requests from the browser extension.
     */
    public function redcap_module_api($action, $payload, $project_id, $user_id, $format, $returnFormat, $csvDelim) {
        $ext_token = $payload['ext_token'] ?? null;
        $pid = $payload['pid'] ?? null;

        if (in_array($action, ['projects', 'extraconfig', 'newrec'])) {
            if (!$pid) {
                return $this->framework->apiErrorResponse('Missing project ID', 400);
            }

            // Set project context so getProjectSetting() works
            $this->setProjectId($pid);

            $username = null;
            if ($ext_token) {
                $username = $this->validateExtensionToken($ext_token);
            }

            if (!$username) {
                return $this->framework->apiErrorResponse('Invalid or missing authentication token', 401);
            }
        }

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST');
        header('Access-Control-Allow-Headers: Origin, Content-Type');

        if ($action === 'projects') {
            $term = $payload['term'] ?? '';
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
            $target_project = intval($payload['target_project'] ?? 0);
            
            $sql = "SELECT MAX(record) AS max_record FROM redcap_data WHERE project_id = ? GROUP BY record ORDER BY record DESC LIMIT 1";
            $result = $this->query($sql, [$target_project]);
            $row = $result->fetch_assoc();
            $max_record = intval($row['max_record'] ?? 0);

            return $this->framework->apiJsonResponse([
                'record_id' => $max_record + 1,
                'target_project' => $target_project
            ]);
        }
    }
}