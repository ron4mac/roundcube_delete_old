
function rcmail_delete_old () {
	buttons={};
	buttons[rcmail.get_label('delete_old.check')] = function(e) {
			rcmail.http_post('plugin.delallold', {'ck':1}, function() { $('#dstus').addClass('busy').html(rcmail.get_label('delete_old.checking')); });
		};
	buttons[rcmail.get_label('delete_old.delete')] = function(e) {
			rcmail.http_post('plugin.delallold', {}, function() { $('#dstus').addClass('busy').html(rcmail.get_label('delete_old.deleting')); });
		};
	buttons[rcmail.get_label('cancel')] = function(e) { $(this).remove(); };
	rcmail.show_popup_dialog(rcmail.get_label('delete_old.dlgtxt'), rcmail.get_label('delete_old.deloldmsgs'), buttons);
}


// callback for app-onload event
if (window.rcmail) {
	rcmail.addEventListener('init', function(evt) {
		// register command (directly enable in message view mode)
		rcmail.register_command('plugin.delete_old', rcmail_delete_old, true);
	});
	rcmail.addEventListener('plugin.docallback', function (data) {
		$('#dstus').removeClass('busy').html(data.msg)
		rcmail.refresh();
	});
}
