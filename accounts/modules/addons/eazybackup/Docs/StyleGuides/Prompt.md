
Context: We are working on a WHMCS development server. I am in the process of updating the client area templates with a new semantec styling system so that we can remove as much of the inline Tailwind styling as possible. Context: Refer to Docs/StyleGuides/SEMANTIC-THEME-REFERENCE.md as the authoritative styling reference. Use the semantic classes and tokens documented there for every design decision.

Workspace:
- /var/www/eazybackup.ca/accounts

Task: We need to start by migrating the following templates from the old inline tailwind styles over to the new eb semantec system: [e3backup_hyperv.tpl](accounts/modules/addons/cloudstorage/templates/e3backup_hyperv.tpl) 

keep the templates current client-side behavior exactly as-is, including the Alpine dropdowns, column picker, pagination, and modal / drawer flow, with this pass limited to restyling and shell/layout migration only.


Context: We are working on a WHMCS development server. I am in the process of updating the client area templates with a new semantec styling system so that we can remove as much of the inline Tailwind styling as possible. Context: Refer to Docs/StyleGuides/SEMANTIC-THEME-REFERENCE.md as the authoritative styling reference. Use the semantic classes and tokens documented there for every design decision.

Workspace:
- /var/www/eazybackup.ca/accounts

keep the templates current client-side behavior exactly as-is, including the Alpine dropdowns, column picker, pagination, and modal flow, with this pass limited to restyling and shell/layout migration only. 

migrate the template to modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl and pass ebE3SidebarPage='users' so it renders through the new e3backup_sidebar.tpl with the same collapsible app-shell behavior as the other e3 pages like @e3backup_tenants.tpl.
