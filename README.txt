The all-new, easy-to-use connect module 'connects' child nodes to parent nodes. The parent nodes could be petitions, online email or fax actions, or even event registrations. We refer to them generically as the 'parent' or 'campaign' nodes. The child (or 'participant') nodes represent the petition signers, action participants, etc.

This module lets you assign a variety of functions to a parent node (such as sending an email or a fax, adding participants to CiviCRM, allowing participants to customize the fax or email, displaying a progress meter, etc.). When the parent node is displayed, connect creates a form that allows users to enter the required information to create a new participant record. When the form is submitted, all the actions are launched, the participant is recorded as having participated in the action, and the participant information is saved.

INSTALLATION

Install as usual by:
  - downloading the module and adding it to your /sites/all/modules directory and 
  - then enable the Connect module.  The MP Autocomplete Widget should also work, but will require that you set up an API key from OpenConcept to use it.

The Connect CiviCRM module hasn't been heavily used and may not be tested with the latest version of CiviCRM.  The connect module does not require CiviCRM to run, however it is useful to be able to place participants into an organizations CRM.  In the future I expect that we will be ale to support multiple CRMs. 

To create a campaign:
- declare what node types can be managed by connect from Site configuration > Connect module settings (see the "Node types" discussion below)
- create a new campaign node of the proper type, and fill out the fields as desired
- from the "settings" tab, choose the basic campaign settings
- from the "functions" tab, select the functions you want to add to the campaign
- return to the 'settings' tab to configure the new functions (note that some settings will cause additional settings to appear, so make sure all the required elements are filled out before using your new campaign)
- set up a CAPTCHA challenge for the connect forms (see the "CAPTCHA" section below"
- displaying the campaign node will now also display the participant form; filling out that form will trigger the associated functions


CAPTCHA

The connect module requires that a captcha be assigned to its forms. To set this up, enable and configure the captcha module. Make sure you enable the "Add CAPTCHA adminstration links to forms" option. As user 1, or another user enabled both to avoid captchas amd administer connect, view a connect campaign node. You will have the opportunity to add a captcha to the "connect_form" form ID. Once this is done, captcha will automatically handle adding the desired challenge and response to your connect module forms.


NODE TYPES

Before using connect, you need to set up at least one parent and one child node type. Any node types that you set up as child/participant or parent/campaign nodes will be subject to processing by the connect module, so create node types specifically for use with this module.

The parent node types can contain any fields you want to display, but when you set up your connect functions, additional fields may become necessary. (For example, fax and email campaign functions save the success or failure of the fax/email in the child node, so there has to be a field available to hold that information.) The "required fields in parent/campaign node" and "required fields in child/participant node" sections of the settings form identify what fields are necessary in each node type for the functions you have selected.

To prevent any 'administrative' fields in the child node from being displayed on the connect participation form, set the teaser display to 'hidden' when you add the field.

Some examples:

A petition allows participants to add their names and other identifying information to the list of participants. Thus, the node types for a petition might look like this:

campaign_petition
- title
- body
- petition text

participant_petitioner
- title
- name
- city
- province/state
- postal code

That is the most basic functionality provided by the connect module. Additional functions allow it to do may more things, but new functionality requires more node fields to hold additioal information. For instance, if you want participation to trigger an email message to a defined target, you would enable the "Email (defined target)" function and create node types like so:

campaign_email
- title
- body
- email_target_address
- email_subject
- email_body

participant_email
- title
- name
- city
- province/state
- postal code
- email address

And if you want the participant to be able to edit the content of the email that is sent, you would also enable the "Content: rewrite" function and add anothe field to the participant_email nodetype:

participant_email
- title
- name
- city
- province/state
- postal code
- email address
- participant_email_body

When you add and configure the "Content: rewrite" function, the parent email_body will be available for the participant to revise, and the revised version of the text will be emailed to your target.


FUNCTIONS

Connect comes with the following functions.

CiviCRM: add participants
If you use CiviCRM, this functions allows you to automatically insert campaign participants into your CiviCRM records. By default, it creates a group named after the parent campaign and adds new participants to CiviCRM as members of that group. In order to use this function,

Content: append
This function is only relevant in cases where something is being done with the content by another function. It allows participants to add their own comments, etc. to the content provided by the parent node. In practice, this means that the content from the child field is appended to the content from the parent field before any further action (email or fax, for instance) is taken. Thus, .

Content: rewrite
Allows participants to revise the content provided by the parent node.

Display progress bar
Adds a CSS-based display showing how many of the target number have participated.

Email (defined target)
Sends email to a specified name and email address.

Email (target lookup)
Sends email to a target determined by the participant's information.

MyFax (defined target)
Sends fax (using the myfax.com service) to a specified name and fax no.

MyFax (target lookup)
Sends fax (using the myfax.com service) to a target determined by the participant's information.

One vote per person
Prevent the same person from participating more than once.


SENDING HTML EMAIL

Connect can send plain-text or HTML emails. Plain text is simple: make sure the email body field (which is usually in the parent node, but can be in the child node if you are using the "Content: replace" function) has its "Text processing" option set to "Plain text". This ensures that your email content will not contain any HTML tags when it is sent.

In order to send HTML mail, you must first install and configure the mimemail module. Connect uses this module to send HTML-formatted emails. Note that you do not need to make mimemail the default mail handler for your whole site if you just want to use it for connect. Next, make sure the "Text processing" option in the node type set to "Filtered text" and that the filter in the node itself is set to one of the HTML filters. This will preserve your HTML when it's mailed. To activate the HTML mail option using the "Send HTML email using the mimemail module?" option under the node's connect settings tab.



