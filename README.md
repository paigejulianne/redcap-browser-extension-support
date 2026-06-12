# REDCap Browser Extension Support

This external module for REDCap enables your users to use the REDCap Browser Extension to quickly navigate your REDCap 
installation, being able to type in a project name, a record number, and going straight to that record's home page - no 
more waiting for long dropdowns to load. It also includes features for the administrator - being able to jump straight 
into the configuration for a project.

Despite using an API key, it does not bypass REDCap security and safeguards.  Basically, the browser extension just reads 
a list of projects a user has access to, whether or not they have admin access to that project, and then constructs a URL 
based on the project the user selects and the record number entered.  If the user still has a REDCap login session active, 
then they will jump straight to the page.  Otherwise, they will be presented with the REDCap login screen before being taken 
to the page.

This extension is needed so you, administrators can restrict who has access to this tool.  Essentially, we 'block' access 
to the project listing and details through the REDCap API.  The API requires you pass it an API key, so it can 'match up' 
requests with authorized users. Now, you *could* enable this module on any existing project, but we highly recommend you 
create an entirely new project so you can efficiently manage user permissions.

So, after creating a new project, you'll want to enable the module for that project.  That will give you a new page in the
 left navigation called 'Browser Extension Configuration'.  In the center will be a video, but at the bottom of the page
(that only you, administrators can see) is a link "Grant all users access to this project and generate API keys".

This will go through your entire system's user list and generate an API key for them in this project if they don't already 
have one (so you can do this multiple times as your userbase grows).  Now, the extension and project are configured and ready
 to be used with the browser extension.  Details for that can be seen in the video on the configuration page that you'll 
direct your users.

If you have further questions or need assistance, please reach out to me via email.

Paige Julianne Sullivan
<paige@paigejulianne.com>