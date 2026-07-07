# REDCap Browser Extension Support External Module

This external module for REDCap acts as the backend support for the **REDCap Browser Extension**. It enables your users to quickly navigate your REDCap installation, allowing them to type in a project name, a record number, and go straight to that record's home page—bypassing long dropdowns and extra clicks. It also includes shortcuts for administrators and project designers to jump straight into configuration pages.

## v2.0 Redesign: Enhanced Security & One-Click Setup

Version 2.0 brings major security and usability improvements:
- **Dedicated Extension Tokens**: The module no longer relies on raw REDCap API tokens. Instead, it generates a dedicated extension token for each user (stored securely in project settings). This token is strictly scoped to project listing and navigation; it **cannot** be used to export or import project data.
- **One-Click Auto-Setup**: Users no longer have to copy and paste a long configuration key. They simply visit the configuration page and click the extension icon in their browser toolbar to automatically configure the extension.
- **On-Demand Token Generation**: Tokens are created dynamically when a user visits the configuration page, eliminating the need for admins to pre-generate tokens for everyone.

## How It Works

The browser extension reads a list of projects a user has access to, checks their admin/design permissions, and constructs URLs based on the user's selections. 

All routing goes through REDCap's secure `api-actions` framework. If the user has an active REDCap login session, they jump straight to the page. If not, they are presented with the standard REDCap login screen before being redirected. **It does not bypass REDCap security.**

## Installation & Setup

We highly recommend creating a **dedicated, system-wide "Utility" project** for this module rather than enabling it on an existing research project. This allows you to efficiently manage user permissions for the extension.

1. Create a new REDCap project (e.g., "Browser Extension Support").
2. Enable this External Module on the project.
3. Once enabled, you will see a new link in the left navigation called **Browser Extension Configuration**.
4. **Granting Access**: At the bottom of the configuration page (visible only to system administrators), click the link to *"Grant all users access to this project"*.
   - This script will add all current REDCap users to this project. 
   - You can run this script periodically as your userbase grows.

## User Instructions

Direct your users to the **Browser Extension Configuration** page in your dedicated project. The page provides a video tutorial and links to download the extension.

To connect the extension:
1. The user installs the browser extension.
2. The user navigates to the Browser Extension Configuration page.
3. The user clicks the extension icon in their browser toolbar. A green banner will appear confirming the server was detected, and clicking "Configure Extension" completes the setup instantly.

*(A manual fallback is provided on the page for users who need to copy and paste their configuration key.)*

## Backward Compatibility

The v2.0 external module maintains backward compatibility with legacy configuration keys. Users who configured their extension prior to the v2.0 update will not experience disruptions and can continue using their existing API-token-based keys until they decide to reconfigure.

## Support

If you have further questions or need assistance, please reach out to me via email:
Paige Julianne Sullivan (<paige@paigejulianne.com>)