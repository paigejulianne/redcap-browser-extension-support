<?php
global $module;
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$token = $module->getOrCreateExtensionToken(USERID);
$configKey = $module->getConfigurationKey(USERID, PROJECT_ID);
global $redcap_base_url;
?>

<!-- Hidden element for one-click browser extension auto-configuration -->
<div id="redcap-ext-config"
     data-base-url="<?php echo $module->escape($redcap_base_url); ?>"
     data-pid="<?php echo intval(PROJECT_ID); ?>"
     data-token="<?php echo $module->escape($token); ?>"
     style="display:none;"></div>

<h5>REDCap Browser Extension Support</h5>
    <p><strong>What is the REDCap Browser Extension?</strong> <br/><br/>
        The browser extension helps you quickly and easily navigate this REDCap server.  You can easily jump directly
        into a record that already exists or add a new record with a few clicks.  Project and system administrators
        also get other options to jump right to where they need to be to manage a project or the system.<br/><br/>

        <iframe width="560" height="315" src="https://www.youtube.com/embed/rrnTLtVGlyM?si=vFX42WLINoRPYARc" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></a></p>

    <p>First, you need to install the browser extension from the appropriate store.  You can find the extension here:</p>

<?php $ua = $_SERVER['HTTP_USER_AGENT'];
if (stripos($ua, 'chrome') !== false || stripos($ua, 'chromium') !== false || stripos($ua, 'edge') !== false || stripos($ua, 'opera') !== false || stripos($ua, 'brave') !== false): ?>
    <p><strong>Install the extension for Chrome, Chromium, Microsoft Edge, Opera, and Brave</strong><br/><br/>
        <a href="https://chrome.google.com/webstore/detail/redcap-browser-extension/gplbopmpolkcfokdhjeclihfhnlhleji" target="_blank">
            <img src="<?php echo $module->getUrl("chrome_store_button.png") ?>" alt="Chrome Web Store"></a><br/><br/>
    </p>

<?php elseif (stripos($ua, 'firefox') !== false): ?>
    <p><strong>Install the extension for Firefox</strong><br/><br/>
        <a href="https://addons.mozilla.org/en-US/firefox/addon/redcap-browser-extension/" target="_blank">
            <img src="<?php echo $module->getUrl("firefox_addon.webp") ?>" alt="Firefox Addons"></a><br/><br/>
    </p>
<?php endif; ?>

<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px; padding: 15px; margin: 20px 0;">
    <h6 style="margin-top: 0; color: #155724;">&#9889; One-Click Setup (Recommended)</h6>
    <p style="margin-bottom: 0; color: #155724;">
        With the extension installed, just <strong>click the REDCap Browser Extension icon</strong> in your browser toolbar
        while you're on this page. It will automatically detect your server and configure itself &mdash; no copying or pasting needed.
    </p>
</div>

<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; margin: 20px 0;">
    <h6 style="margin-top: 0;">Manual Setup</h6>
    <p>If the one-click setup doesn't work, you can manually configure the extension:</p>
    <p>
        <button onclick="navigator.clipboard.writeText('<?php echo $module->escape($configKey); ?>');">&#128203; Copy Configuration Key</button>
        &nbsp; <strong>Do not share this key with anyone.</strong>
    </p>
    <p style="margin-bottom: 0;">
        Open the extension options (right-click the extension icon &rarr; Options) and paste the key into the configuration field.
    </p>
</div>

<?php if (SUPER_USER): ?>
<p><h3>Admin Tools</h3>
<a href="<?php echo $module->getUrl('grant_all.php'); ?>">Grant all users access to this project</a> (may take a few moments to load while the operation completes)
    </p>
<?php endif; ?>

<?php require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';