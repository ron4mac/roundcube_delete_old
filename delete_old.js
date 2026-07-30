function rcmail_delete_old () {
	buttons={};
	buttons[rcmail.get_label('delete_old.check')] = function(e) {
			rcmail.http_post('plugin.delallold', {act:'chk'}, function() { $('#dstus').addClass('busy').html(rcmail.get_label('delete_old.checking')); });
		};
	buttons[rcmail.get_label('delete_old.dnzip')] = function(e) {
			rcmail.http_post('plugin.delallold', {act:'zip'}, function() { $('#dstus').addClass('busy').html(rcmail.get_label('delete_old.zipping')); });
//	postZipAction();
		};
	buttons[rcmail.get_label('delete_old.movloc')] = function(e) {
			rcmail.http_post('plugin.delallold', {act:'mov'}, function() { $('#dstus').addClass('busy').html(rcmail.get_label('delete_old.moving')); });
		};
	buttons[rcmail.get_label('delete_old.delete')] = function(e) {
			rcmail.http_post('plugin.delallold', {act:'del'}, function() { $('#dstus').addClass('busy').html(rcmail.get_label('delete_old.deleting')); });
		};
	buttons[rcmail.get_label('cancel')] = function(e) { $(this).remove(); rcmail.checkmail(); };
	rcmail.show_popup_dialog(rcmail.get_label('delete_old.dlgtxt'), rcmail.get_label('delete_old.deloldmsgs'), buttons, { close: ()=>rcmail.checkmail() });		//rcmail.command('switch-task', 'mail')
}

function postDownloadAction (dat) {
	var form = $('#d0_download-form'),
		inputs = [],
		post = {};

	post._token = rcmail.env.request_token;
//	post.act = "zip";
	post.tmpfn = dat.tmpfn;
	post.zipfn = dat.zipfn;
	$.each(post, function(k, v) {
		if (typeof v == 'object' && v.length > 1) {
		  for (var j=0; j < v.length; j++)
			  inputs.push($('<input>').attr({type: 'hidden', name: k+'[]', value: v[j]}));
		}
		else {
			inputs.push($('<input>').attr({type: 'hidden', name: k, value: v}));
		}
	});

	if (!form.length)
		form = $('<form>').attr({
				style: 'display: none',
				method: 'POST',
				action: '?_task=mail&_action=plugin.downloadzip'
			})
			.appendTo('body');
	form.html('').append(inputs).submit();
}

// callback for app-onload event
if (window.rcmail) {
	rcmail.addEventListener('init', function(evt) {
		// register command (directly enable in message view mode)
		rcmail.register_command('plugin.delete_old', rcmail_delete_old, true);
	});
	rcmail.addEventListener('plugin.docallback', function (data) {
		$('#dstus').removeClass('busy').html(data.msg);
		if (data.zipfn) postDownloadAction(data);		// request file download of zip file
	//	rcmail.refresh();
	});
}
