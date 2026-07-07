# The Definitive REDCap External Module Developer's Guide

This guide is written specifically for PHP and JavaScript developers who have **zero prior knowledge of REDCap**. If you are accustomed to modern PHP frameworks like Laravel or Symfony, or JS frameworks like React or Vue, REDCap will feel structurally different. This guide acts as a complete onboarding manual to bridge that gap, while also serving as a massive, exhaustively detailed reference manual for REDCap External Modules (EMs).

---

## Part 1: Welcome to REDCap (The Primer)

Before you can build an extension for an application, you must understand the application itself.

### What is REDCap?
REDCap (Research Electronic Data Capture) is a web application originally created by Vanderbilt University. It is used globally to build and manage online surveys and databases. While it can be used for any data collection, it is specifically geared to support online or offline data capture for research studies and operations.

### The Core Data Model (EAV vs Relational)
As a PHP developer, you are likely used to relational databases (e.g., a `users` table, a `products` table). REDCap does **not** create a new database table for every project or form you create. 

Instead, REDCap heavily relies on an **Entity-Attribute-Value (EAV)** model. Almost all project data is stored in a massive central table called `redcap_data`.
- **Entity**: The record ID (e.g., Patient #123).
- **Attribute**: The field name (e.g., `first_name`, `age`).
- **Value**: The actual data (e.g., "John", "45").

*Why does this matter?* Because you cannot write traditional `SELECT * FROM my_custom_table` queries when extracting project data. You must use REDCap's built-in framework methods to extract and format data into standard arrays.

### Glossary of REDCap Concepts
To understand the documentation, you must understand the domain language.

1. **The System vs. The Project**: REDCap is a multi-tenant system. The "System" refers to the entire REDCap installation. A "Project" is a single database/survey instance created by a user. **System Settings** apply globally; **Project Settings** apply only to a specific project.
2. **Instruments**: Often called "Forms" or "Surveys". An instrument is simply a collection of fields (questions) grouped together on a page.
3. **Records**: A record represents the primary entity being tracked in a project (usually a person, like a patient or study participant). 
4. **Events and Arms (Longitudinal Projects)**: 
   - In a *Classic Project*, an instrument is filled out once per record. 
   - In a *Longitudinal Project*, instruments can be filled out multiple times across different "Events" (e.g., "Baseline Visit", "Month 1", "Month 6"). 
   - "Arms" are groups of events (e.g., "Treatment Arm" vs "Placebo Arm").
5. **Data Access Groups (DAGs)**: A way to partition records. If users are assigned to "Site A", they can only see records created by "Site A", even though all records exist in the same project.
6. **Control Center**: The admin dashboard for the entire REDCap system. Only "Super Users" can access this.

---

## Part 2: External Modules: The Architecture

### What is an External Module (EM)?
An External Module is a plugin that extends REDCap's core functionality. REDCap does not have a traditional package manager like Composer. Instead, modules are downloaded (or cloned) directly into a specific folder on the server. 

Modules can:
- Modify the UI on existing pages.
- Add completely new custom pages.
- Execute background cron jobs.
- Expose API endpoints.
- Hook into REDCap's lifecycle events (e.g., "right after a record is saved").

### The Lifecycle
For an External Module to run, it must go through a two-step lifecycle:
1. **System Enablement**: A Super User must navigate to the Control Center and enable the module globally for the entire REDCap installation.
2. **Project Enablement**: A Project Admin must navigate to their specific project and enable the module *for that project*. (Some modules only have system-level effects and don't need project enablement).

### File Structure & Namespacing
Modules live in the `<redcap-root>/modules/` directory.

The directory name **must** follow a strict format: `[developer_prefix]_[module_name]_v[version]`.
- **Developer Prefix**: A unique identifier for your organization (e.g., `vanderbilt`).
- **Module Name**: Snake case (e.g., `awesome_plugin`).
- **Version**: E.g., `1.0.0`.

**Example Directory**: `modules/vanderbilt_awesome_plugin_v1.0.0/`

Inside this folder, you need at minimum two files:
1. `config.json`: The manifest file.
2. `AwesomePlugin.php`: The main PHP class.

The namespace in your PHP file should typically match the prefix and name, though REDCap automatically inspects the class that extends `AbstractExternalModule`.

```php
<?php
namespace Vanderbilt\AwesomePlugin;

use ExternalModules\AbstractExternalModule;

class AwesomePlugin extends AbstractExternalModule {
    // Your code here
}
```

---

## Part 3: Bootstrapping a Module (`config.json`)

The `config.json` file is the heart of your module. It tells REDCap who built it, what permissions it needs, what settings it exposes to the UI, and what custom pages it creates.

### The Metadata Schema
```json
{
  "name": "My Awesome Plugin",
  "description": "This plugin does incredible things to REDCap.",
  "namespace": "Vanderbilt\\AwesomePlugin",
  "documentation": "README.md",
  "authors": [
    {
      "name": "Jane Doe",
      "email": "jane@example.com",
      "institution": "Vanderbilt University"
    }
  ],
  "framework-version": 16,
  "compatibility": {
    "php-version-min": "7.4.0",
    "redcap-version-min": "12.0.0"
  }
}
```
*Note on Framework Version*: Always use the latest framework version (currently up to 16). Higher versions provide more methods and automatically detect hooks without needing to declare them.

### Settings & Persistence
REDCap allows you to build a UI for configuring your module without writing any HTML. You define settings in `config.json`, and REDCap auto-generates a configuration modal.

**System Settings** are defined under `"system-settings"`.
**Project Settings** are defined under `"project-settings"`.

```json
"project-settings": [
    {
        "key": "enable-feature-x",
        "name": "Enable Feature X?",
        "required": true,
        "type": "checkbox"
    },
    {
        "key": "api-token",
        "name": "External API Token",
        "required": false,
        "type": "text",
        "super-users-only": true
    }
]
```

#### Setting Data Types
The `type` key is incredibly powerful. REDCap natively supports:
- `text`, `textarea`, `rich-text`, `password`, `json`
- `checkbox`, `radio`, `dropdown` (requires a `choices` array)
- `file` (auto-handles file uploads)
- `project-id`, `user-list`, `user-role-list`
- `form-list`, `field-list`, `event-list`, `dag-list` (auto-populates dropdowns with project context!)
- `sub_settings` (for nested arrays of objects)

#### Branching Logic
You can conditionally show/hide settings using `branchingLogic`.
```json
"branchingLogic": {
    "field": "enable-feature-x",
    "op": "=",
    "value": true
}
```

#### Saving and Retrieving Settings
In your PHP code, you do not query the database for these settings. You use the framework:
```php
// Get a project setting
$token = $this->getProjectSetting('api-token');

// Save a project setting dynamically
$this->setProjectSetting('enable-feature-x', true);
```

### Links (Custom Pages)
If you want to add a page to REDCap, define it in `links`. REDCap will create a menu item in the sidebar that routes to your PHP file.

```json
"links": {
    "project": [
        {
            "name": "My Dashboard",
            "icon": "fas fa-chart-bar",
            "url": "dashboard.php",
            "show-header-and-footer": true
        }
    ]
}
```

---

## Part 4: The Hook System (Event Listeners)

If you use Laravel, you are used to dispatching and listening to events. In REDCap, these are called **Hooks**.

REDCap uses "Magic Methods" for hooks. You do not need to register a listener. Instead, you simply declare a method in your main class with the **exact name** of the hook. If the method exists, REDCap will call it at the appropriate time in the lifecycle.

### The Request Lifecycle and "_top" Hooks
REDCap hooks generally fire in two places:
1. `_top`: Fires at the very top of the HTML body, *before* the main content is rendered. Use this to inject CSS, intercept a page load, or perform back-end logic.
2. Standard (e.g., `redcap_data_entry_form`): Fires at the very *bottom* of the page, after the DOM is rendered. Use this to inject JavaScript that manipulates the UI.

### Standard REDCap Hooks
Here is the complete, exhaustive list of standard REDCap hooks you can implement in your class:

- **redcap_control_center()**
  - *Purpose*: Actions on any Control Center page.
  - *Location*: Bottom of the page.

- **redcap_every_page_before_render($project_id=null)**
  - *Purpose*: Executes on every PHP script in REDCap before the script formally processes. Ideal for global intercepts.

- **redcap_every_page_top($project_id=null)**
  - *Purpose*: Executes at the top of every single REDCap page (right after the BODY tag). Good for global CSS injection.

- **redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance)**
  - *Purpose*: Executes at the BOTTOM of every data entry form (not surveys). Used to inject JS to alter form behavior.

- **redcap_data_entry_form_top($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance)**
  - *Purpose*: Executes at the TOP of the data entry form.

- **redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)**
  - *Purpose*: Executes during post-processing immediately after a record has been saved. Extremely useful for sending alerts, syncing data to external systems, or calculating scores in the background.

- **redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)**
  - *Purpose*: Executes immediately after a survey is finalized.

- **redcap_survey_acknowledgement_page($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)**
  - *Purpose*: Executes on the "Thank You" page of a survey.

- **redcap_survey_page($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)**
  - *Purpose*: Bottom of a survey page (useful for survey-specific JS).

- **redcap_survey_page_top($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)**
  - *Purpose*: Top of a survey page.

- **redcap_add_edit_records_page($project_id, $instrument, $event_id)**
  - *Purpose*: Executes on the "Add/Edit Records" dashboard.

- **redcap_user_rights($project_id)**
  - *Purpose*: Executes on the User Rights configuration page.

- **redcap_project_home_page($project_id)**
  - *Purpose*: Executes on the Project Home page.

- **redcap_custom_verify_username($user)**
  - *Purpose*: Intercepts user assignment to verify external auth systems (LDAP, etc.).

- **redcap_pdf($project_id, $metadata, $data, $instrument=null, $record=null, $event_id=null, $instance=1)**
  - *Purpose*: Intercepts PDF generation to allow you to alter the `$metadata` (layout) or `$data` (values) before export.

- **redcap_email($to, $from, $subject, $message, $cc, $bcc, $fromName, $attachments)**
  - *Purpose*: Intercepts all REDCap outbound emails.
  - *Special Return Value*: You MUST return a boolean. `false` prevents REDCap from sending the email (useful if you are handling delivery yourself).

### External Module Specific Hooks
The EM framework itself provides additional hooks specifically for module lifecycle management:

- **redcap_module_ajax($action, $payload, $project_id, $record, $instrument, $event_id, $repeat_instance, $survey_hash, $response_id, $survey_queue_hash, $page, $page_full, $user_id, $group_id)**: Triggered by the JS `module.ajax()` call (See Section 5).
- **redcap_module_api_before($project_id, $post)**: Triggered before REDCap's API is executed. Return a string to disallow the request.
- **redcap_module_configuration_settings($project_id, $settings)**: Dynamically modify config settings array before they are displayed in the modal UI.
- **redcap_module_system_enable($version)**: Triggered when module is enabled system-wide. Run installation logic here.
- **redcap_module_system_disable($version)**: Triggered when disabled system-wide.
- **redcap_module_project_enable($version, $project_id)**: Triggered when enabled on a project.
- **redcap_module_project_disable($version, $project_id)**: Triggered when disabled on a project.
- **redcap_module_save_configuration($project_id)**: Fired after a user clicks "Save" on your module's config modal.
- **redcap_module_link_check_display($project_id, $link)**: Dynamically show/hide your custom page links based on user context.

---

## Part 5: Routing, APIs, and AJAX

Because REDCap is a legacy application that predates modern MVC routers, it relies heavily on query strings (`?pid=123&page=dashboard`).

### Creating Custom Pages (Routing)
To create a custom page, you define it in the `links` section of `config.json` (pointing to `dashboard.php`). 
When you need to generate a URL to that page dynamically in PHP, use `$this->getUrl()`:

```php
$url = $this->getUrl('dashboard.php');
// Output: https://redcap.org/redcap_vX.X.X/ExternalModules/?prefix=my_module&page=dashboard&pid=123
```

By default, these pages check if the user is authenticated and has rights to the project. To create a public page, pass `true` as the second argument: `$this->getUrl('public_page.php', true)`.

### Internal AJAX (JavaScript to PHP)
To make asynchronous calls from a REDCap page back to your module, do NOT create a separate PHP file and use `$.ajax()`. Use the built-in EM AJAX system.

**1. Register the action in `config.json`:**
```json
"auth-ajax-actions": [
    "save_my_custom_data"
]
```

**2. Call it from JavaScript:**
```javascript
module.ajax('save_my_custom_data', { myData: 123 }).then(function(response) {
    console.log("Server replied:", response);
});
```

**3. Handle it in your PHP Class:**
```php
function redcap_module_ajax($action, $payload, $project_id, $record, $instrument, $event_id, $repeat_instance, $survey_hash, $response_id, $survey_queue_hash, $page, $page_full, $user_id, $group_id) {
    if ($action === 'save_my_custom_data') {
        $data = $payload['myData'];
        // Do something...
        return ["status" => "success", "processed" => $data];
    }
}
```

### External APIs
If you are building an endpoint for a 3rd party system (e.g., an external mobile app or a script pulling data), use the External Module API system. These endpoints are accessed through REDCap's standard API mechanism (`/api/`), leveraging built-in token management.

#### How 3rd Party Systems Access the API
To access the API, a 3rd party application must submit an HTTP POST request to your REDCap API endpoint (e.g., `https://your-redcap.org/api/`). 

They must pass the following `POST` parameters:
- `content`: **Required.** Must be `"externalModule"`.
- `prefix`: **Required.** Your module's prefix.
- `action`: **Required.** The name of the API action.
- `token`: **Optional.** A valid REDCap API token. Required if the action is set to `"auth"` only.
- `format`: **Optional.** One of `json`, `xml`, or `odm`.
- `returnFormat`: **Optional.** One of `json`, `xml`, or `csv`.
- `(any custom keys)`: You can pass custom payloads (e.g., `customData=123`), which will be injected into the `$payload` parameter in PHP. This includes file uploads!

#### 1. Register in `config.json`
Define your actions and whether they require authentication (`auth`) or are public (`no-auth`).

```json
"api-actions": {
    "fetch-scores": {
        "description": "Gets calculated scores for a record.",
        "access": ["auth", "no-auth"]
    },
    "upload-data": {
        "description": "Uploads sensitive data. Requires API token.",
        "access": ["auth"] 
    }
}
```

#### 2. Handle the Request in PHP
Implement the `redcap_module_api` hook. 

**Parameters explained:**
- `$action`: The action name requested (e.g., `"fetch-scores"`).
- `$payload`: An array containing all custom POST parameters, including uploaded files (e.g., `$payload['my_custom_field']`).
- `$project_id`: The project ID associated with the API token (if provided). `null` if no token is used.
- `$user_id`: The user ID associated with the token.
- `$format`, `$returnFormat`, `$csvDelim`: The formats requested by the client.

```php
function redcap_module_api($action, $payload, $project_id, $user_id, $format, $returnFormat, $csvDelim) {
    if ($action === "fetch-scores") {
        $recordId = $payload['record_id'];
        
        // Use the framework's helper to return JSON with a 200 OK status
        return $this->framework->apiJsonResponse(["scores" => [95, 82, 100]]);
    }
    
    // For unauthorized actions, you can return a custom error
    if ($action === "upload-data") {
        if (!$project_id) {
            return $this->framework->apiErrorResponse("Missing or invalid API token.", 401);
        }
        return $this->framework->apiJsonResponse(["status" => "success"]);
    }
}
```

---

## Part 6: Interacting with the Core (Framework Methods)

As a developer, you will spend 90% of your time interacting with the `ExternalModules\AbstractExternalModule` framework methods. 
Inside your module class, these are accessed via `$this->methodName()`.

### 1. Database Access & Querying
**DO NOT USE PHP's standard PDO, mysqli, or `REDCap::db()`**. You must use the framework's parameterized query system to prevent SQL injection.

```php
// GOOD: Parameterized query
$sql = "SELECT record, value FROM redcap_data WHERE project_id = ? AND field_name = ?";
$result = $this->query($sql, [$project_id, 'first_name']);

while ($row = $result->fetch_assoc()) {
    $firstName = $row['value'];
}
```

**Query Builder**: The framework provides a query builder for complex conditions.
```php
$query = $this->createQuery();
$query->add('SELECT * FROM redcap_data WHERE project_id = ?', [$project_id]);
$query->add('AND field_name IN (?, ?)', ['first_name', 'last_name']);
$result = $query->execute();
```

### 2. Logging & Auditing
REDCap has strict compliance requirements (HIPAA, 21 CFR Part 11). If your module alters data, you should log it. EMs have a dedicated log table.

```php
// Write a log
$log_id = $this->log("Processed data for record", [
    "record_id" => 123,
    "status" => "success"
]);

// Query logs later using simplified SQL (omitting the SELECT FROM part)
$result = $this->queryLogs("status = ? AND record_id = ?", ["success", 123]);
```

### 3. Fetching Project Context
Often, you need to know "where" the code is running.
- **$this->getProjectId()**: Returns the current project ID (from the `pid` query parameter).
- **$this->getEventId()**: Returns the current event ID.
- **$this->getRecordId()**: Returns the current record ID (if in a hook context).
- **$this->getProjectStatus()**: Returns "DEV", "PROD", "AC", or "DONE".
- **$this->getRecordIdField()**: Returns the name of the primary key field for the project.

### 4. Framework Objects (Project, Form, Field, User)
Instead of writing raw SQL, REDCap provides object-oriented wrappers.

```php
// 1. Get the Project Object
$project = $this->getProject($project_id);

// 2. Interact with the project
$project_title = $project->getTitle();
$all_users = $project->getUsers();

// 3. Get a Form (Instrument) Object
$form = $project->getForm('baseline_visit');
$form_label = $form->getLabel();

// 4. Get a Field Object
$field = $project->getField('age');
$field_type = $field->getType(); // e.g., 'text'
```

User Management Methods:
- `$project->addUser($username, $rights_array)`
- `$project->removeUser($username)`
- `$project->getRights($username)`
- `$project->setRights($username, $rights_array)`

---

## Part 7: Front-End Development & JS

### The JavaScript Module Object
If you need to execute JavaScript on a REDCap page, you should leverage the JS Module Object. This bridges PHP and JS securely.

In your PHP hook (e.g., `redcap_data_entry_form`), initialize the object:
```php
function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) {
    // 1. Initialize the object
    $this->initializeJavascriptModuleObject();
    
    // 2. Output your script
    ?>
    <script>
        // Access the JS object specifically scoped to your module
        const module = <?=$this->getJavascriptModuleObjectName()?>;
        
        // Log to the server via AJAX invisibly!
        module.log('The user opened the form', { record: '<?=$record?>' });
        
        // Get an API URL
        const myApiUrl = module.getUrl('api.php');
    </script>
    <?php
}
```

### Twig Templating
REDCap natively supports Twig for separating your PHP logic from your HTML views. You do not need to install Twig via composer.

**1. Create `views/dashboard.html.twig`:**
```html
<div class="card">
    <h1>Welcome, {{ user_name }}</h1>
    <p>You are in project: {{ project_title }}</p>
</div>
```

**2. Render it in your PHP script:**
```php
echo $this->getTwig()->render('views/dashboard.html.twig', [
    'user_name' => USERID, // REDCap global constant
    'project_title' => $this->getProject()->getTitle()
]);
```

### Multi-Language Management (MLM)
If the project uses REDCap's Multi-Language Management, you can access translations dynamically.
- **PHP**: `$this->tt('string_key')`
- **JS**: `module.tt('string_key')`

---

## Part 8: Advanced Development & Security

### Background Processes (Crons)
Crons run asynchronously in the background. Define them in `config.json`.

```json
"crons": [
    {
        "cron_name": "nightly_sync",
        "cron_description": "Syncs data to the data warehouse.",
        "method": "runNightlySync",
        "cron_frequency": "86400",
        "cron_max_run_time": "3600"
    }
]
```

```php
function runNightlySync($cronAttributes){
    // Because crons run globally, they do not have a specific project_id context.
    // You must iterate through projects that have the module enabled:
    foreach($this->getProjectsWithModuleEnabled() as $localProjectId){
        // Set the context manually!
        $this->setProjectId($localProjectId);
        
        // Perform heavy lifting...
    }
    return "Sync completed successfully.";
}
```
*Architecture Warning*: PHP processes have timeouts. Do not build monolith crons that loop through 10,000 records sequentially. Use a queue/worker pattern by having a cron process 100 records, update a persistent pointer in `system-settings`, and let the next cron invocation pick up from there.

### Security Paridigms

#### 1. SQL Injection
As stated earlier, **ALWAYS** use `$this->query($sql, [$params])`. Never concatenate user input into strings.

#### 2. Cross-Site Scripting (XSS)
When outputting data from the database to the browser, you must escape it. REDCap provides a recursive escape function.
```php
$dirty_string = "<script>alert('hack');</script>";
echo $this->escape($dirty_string); // Converts to &lt;script&gt;...
```

#### 3. Path Traversal
If your module allows users to specify file paths, ensure they cannot traverse up directories (e.g., `../../../etc/passwd`).
```php
// GOOD
$safe_path = $this->getSafePath($user_provided_path, __DIR__ . '/uploads/');
```

### Building Action Tags
Action Tags are custom string modifiers starting with `@` (like `@HIDDEN` or `@READONLY`) that REDCap users can type into a field's "Action Tags / Field Annotation" box in the Online Designer. 
As an EM developer, you can create your own custom Action Tags (e.g., `@MY-AUTO-CALCULATE`).

#### 1. Register the Action Tag in `config.json`
To make your action tag appear in the popup dialog so users can select it during project design, define it in the `action-tags` array in `config.json`.
```json
"action-tags": [
    {
        "tag": "@MY-AUTO-CALCULATE",
        "description": "Automatically calculates a complex medical score when the field is viewed."
    }
]
```

#### 2. Implement the Logic in PHP/JS
REDCap does not automatically execute logic just because an action tag is present. You must use hooks (like `redcap_data_entry_form` or `redcap_survey_page`) to scan the data dictionary, detect which fields have your tag, and inject JavaScript to perform the action.

```php
function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) {
    $fieldsWithTag = [];
    
    // Scan the data dictionary for the action tag
    $dictionary = \REDCap::getDataDictionary($project_id, 'array', false, null, $instrument);
    
    foreach ($dictionary as $fieldName => $fieldMeta) {
        $annotation = $fieldMeta['field_annotation'];
        if (strpos($annotation, '@MY-AUTO-CALCULATE') !== false) {
            $fieldsWithTag[] = $fieldName;
        }
    }
    
    if (empty($fieldsWithTag)) return; // Don't inject JS if the tag isn't used
    
    // Inject JS to act on those specific fields
    $this->initializeJavascriptModuleObject();
    ?>
    <script>
        $(function() {
            const fieldsToCalculate = <?=json_encode($fieldsWithTag)?>;
            fieldsToCalculate.forEach(function(fieldName) {
                // E.g., make the field red and readonly
                $('tr[sq_id="' + fieldName + '"]').css('background-color', 'red');
            });
        });
    </script>
    <?php
}
```

### Final Thoughts for PHP Developers
REDCap is incredibly powerful, but it has a massive legacy footprint. It relies heavily on global constants (like `USERID`, `PROJECT_ID`, `PAGE`) and procedural includes. 

The External Module Framework is the modern layer on top of this legacy core. By strictly adhering to the `$this->methodName()` framework methods, using Twig for views, and utilizing `config.json` for all settings, you can build clean, modern, and secure applications inside the REDCap ecosystem.

---

## Part 9: Appendix - Complete Framework Methods Reference

The following methods are available on the main `$this->framework` or `$this` object (depending on version) for any External Module.

### General Framework Methods
- **addAutoNumberedRecord([$pid])**: Creates the next auto numbered record and returns the record id.
- **apiResponse($body)**, **apiErrorResponse($error_message, $status)**, **apiJsonResponse($data)**, **apiFileResponse($path)**: Helper methods for returning API responses.
- **convertIntsToStrings($row)**: Returns a copy of the specified array with any integer values cast to strings.
- **countLogs($whereClause, $parameters)**: Returns the count of log statements matching the specified where clause.
- **createDAG($name)**: Creates a DAG with the specified name, and returns its ID.
- **createProject($title, $purpose, [, $projectNote])**: Creates a new redcap project and returns the project id.
- **createQuery()**: Creates a `Query` object to aid in building complex queries using parameters.
- **createTempDir()** / **createTempFile()**: Creates temporary directories or files that are automatically deleted when the PHP process finishes.
- **delayModuleExecution()**: Delays the current hook to be called again after all other enabled modules have executed.
- **deleteDAG($groupId)**: Deletes a DAG and all Users/Records assigned to it.
- **deleteUserQuery($key [, $pid])**, **saveUserQuery()**, **getUserQuery()**, **runUserQuery()**: Methods for executing saved User SQL queries.
- **disableModule($pid, $prefix)** / **enableModule($pid, $prefix)**: Enable or disable a module programmatically.
- **escape($value)**: Ensures that the given `$value` is safe for display within HTML (recursive XSS prevention).
- **exitAfterHook()**: Schedules PHP's exit() function to be called after ALL modules finish executing for the current hook.
- **getChoiceLabel($fieldName, $value)** / **getChoiceLabels($fieldName)**: Gets the UI labels for multiple-choice dropdowns or radios.
- **getConfig()**: Returns an array representation of `config.json`.
- **getCSRFToken()**: Returns the CSRF token that REDCap will expect on the next POST request.
- **getDAG($recordId)**: Return the Group ID number for the given record ID.
- **getDataClassical($projectId, $fields, $records)**: Optimized version of REDCap::getData for classical projects.
- **getDataTable([$pid])**: Returns the data table name for the specified project ID.
- **getEdocPath($edoc_id)**: Returns the full (safe) path to an edoc.
- **getEnabledModules([$pid])**: Returns an array with the modules enabled on the system or project.
- **getEventId()**: Returns the current event ID.
- **getFieldLabel($fieldName)**: Returns the UI label for a field.
- **getFieldNames($formName)**: Returns an array of field names for a form.
- **getFormsForEventId($eventId)**: Returns an array of form names for an event.
- **getJavascriptModuleObjectName()**: Returns the name of the javascript object for this module.
- **getProject([$projectId])**: Returns a `Project` object for the given project ID.
- **getProjectId()**: Returns the current project ID.
- **getProjectsWithModuleEnabled()**: Returns an array of project ids for which the module is enabled.
- **getProjectSetting($key [, $pid])** / **setProjectSetting($key, $value)**: Gets or sets a project setting.
- **getProjectSettings([$pid])** / **setProjectSettings($settings)**: Gets or sets ALL project settings.
- **getProjectStatus([$pid])**: Returns "DEV", "PROD", "AC", or "DONE".
- **getPublicSurveyHash($pid)** / **getPublicSurveyUrl($pid)**: Retrieves the public survey link info.
- **getRecordId()** / **getRecordIdField()**: Retrieves the current record ID or the primary key field name.
- **getRepeatingForms([$eventId])**: Returns repeating form names.
- **getSafePath($path[, $root])**: Prevents path traversal attacks by validating file paths against a root directory.
- **getSelectedCheckboxes($record, $variableName)**: Extracts selected checkbox values from a json-array.
- **getSubSettings($key [, $pid])**: Returns repeating sub-settings.
- **getSurveyLinkNewInstance($formName, $record)**: Returns a survey link for a new instance of a repeating form.
- **getSystemSetting($key)** / **setSystemSetting($key, $value)** / **removeSystemSetting($key)**: Manage system-level settings.
- **getTwig()**: Returns an instance of the Twig environment for rendering templates.
- **getUrl($path [, $noAuth=false [, $useApiEndpoint=false]])**: Generates a valid URL pointing to a module file.
- **getUser([$username])**: Returns a `User` object for the given username.
- **getUserSetting($key)** / **setUserSetting($key, $value)**: Manages settings scoped to a specific user.
- **isAuthenticated()**: Returns true in authenticated contexts.
- **isModuleEnabled($prefix)**: Checks if a module is enabled.
- **isPage($path)** / **isREDCapPage($path)** / **isModulePage($path)**: Checks what page the request is currently on.
- **isSuperUser()**: Checks if the current user is a super user.
- **loadBootstrap()** / **loadREDCapJS()** / **loadREDCapCSS()**: Outputs HTML to load standard REDCap assets into a custom page.
- **log($message, [$parameters])**: Stores a log entry in the module's log table.
- **query($sql, $parameters)**: Executes a parameterized SQL query and returns a MySQLi result object.
- **queryLogs($sql, $parameters)**: Queries the EM log table using SQL-like syntax.
- **records->lock($recordIds)**: Locks all forms/instances for the given record ids.
- **redirectAfterHook($url, $forceJS)**: Schedules a redirect after all modules finish executing.
- **removeLogs($whereClause, $parameters)**: Deletes logs.
- **renameDAG($groupId, $name)**: Renames a DAG.
- **requireInteger($mixed)**: Casts and strictly validates an integer.
- **sanitizeAPIToken($token)** / **sanitizeFieldName($fieldName)**: Strips invalid characters for security.
- **setProjectId($projectId)**: Overrides the current project ID in context.
- **throttle($sql, $parameters, $seconds, $maxOccurrences)**: Prevents an action from running too many times in a given window.
- **tt($key, ...)** / **tt_addToJavascriptModuleObject(...)**: Uses the Multi-Language Management system to output translated strings.
- **validateS3URL($url)**: Verifies an Amazon S3 URL.

### Project Object Methods (`$this->getProject()`)
- **addRole($roleName, $rights)** / **removeRole()**: Manage roles.
- **addUser($username, $rights)** / **removeUser($username)**: Manage users.
- **addOrUpdateInstances($instances, $keyFieldNames)**: Allows adding/updating repeating form instances easily.
- **getField($fieldName)**: Returns a `Field` object.
- **getForm($formName)** / **getFormForField($fieldName)**: Returns a `Form` object.
- **getRights($username)** / **setRights($username, $rights)** / **setRoleForUser()**: Manages user permissions.
- **getTitle()**: Gets the project title.
- **getUsers()**: Gets an array of `User` objects.

### Form Object Methods (`$project->getForm()`)
- **getFieldNames()**: Returns the field names on this form.

### Field Object Methods (`$project->getField()`)
- **getType()**: Returns the field type (e.g., 'text', 'radio').

### User Object Methods (`$this->getUser()`)
- **getUsername()**, **getEmail()**
- **getRights([$projectIds])**: Gets rights across specified projects.
- **hasDesignRights()**: Checks for project design privileges.
- **isSuperUser()**: Checks if the user is a super user.

### JavaScript Module Object Methods (`module.`)
Accessed via Javascript in the browser after initializing it in PHP.
- **afterRender(action)**: Executes after page finishes rendering (useful for MLM language switches).
- **ajax(action, data)**: Performs an async POST request to the `redcap_module_ajax` hook. Returns a Promise.
- **getCurrentLanguage()**: Gets the active language from MLM.
- **getUrl(path, noAuth)**: Equivalent to PHP's `getUrl`.
- **getUrlParameter(name)** / **getUrlParameters()**: Safely parse URL GET query strings.
- **isImportPage()** / **isImportReviewPage()** / **isImportSuccessPage()**: Detects Data Import Tool context.
- **log(message, parameters)**: Async logs to the backend from JS (requires `enable-ajax-logging: true`).
- **tt(key)** / **tt_add(key, item)**: Translates a string using MLM.
