# roundcube_delete_old
A Roundcube plugin to delete messages older than configurable timespans.

###Installation
Add folder to the roundcube plugins folder as *delete_old*.
Add name (delete_old) to the plugins list in the roundcube config file.

###Confiuration
In *Settings->Delete Old Messages*, you can specify a global timespan before messages will be deleted in any folder that has no setting of its own. And you can specify whether and when an automatic deletion will take place.

Each folder (in *Settings->Folders*) can have a timespan setting for deletion.

###Use
If automatic deletion is configured, messages older that the specified timespan(s) will be deleted on login or logout. You can check for or delete expired messages at any time from an icon on the Mail Toolbar.
