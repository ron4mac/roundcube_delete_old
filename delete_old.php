<?php

class delete_old extends rcube_plugin
{
	public $task = 'login|logout|mail|settings';

	private $rc;
	private $cbdata = [];

	function init ()
	{
		$this->rc = rcmail::get_instance();
		$this->add_texts('localization', ['deloldcfrm','deloldmsgs','dlgtxt','check','dnzip','movloc','delete','checking','moving','zipping','deleting']);
		$tsk = $this->rc->task;
		if ($tsk == 'settings') {
			$this->includeCSS();
			$this->add_hook('preferences_sections_list', [$this, 'insert_section']);
			$this->add_hook('preferences_list', [$this, 'settings_blocks']);
			$this->add_hook('preferences_save', [$this, 'save_settings']);
			$this->add_hook('folder_form', [$this, 'folder_form']);
			$this->add_hook('preferences_update', [$this, 'update_settings']);
		} elseif ($this->rc->task == 'login') {
			$this->add_hook('login_after', [$this, 'login']);
		} elseif ($this->rc->task == 'logout') {
			$this->add_hook('session_destroy', [$this, 'end_session']);
		} elseif ($tsk == 'mail' && ($this->rc->action == '' || $this->rc->action == 'show')) {
			$this->includeCSS();
			$this->include_script('delete_old.js');
			$this->add_button(
				[
					'type'		=> 'link',
					'label'		=> 'buttontext',
					'command'	=> 'plugin.delete_old',
					'class'		=> 'button clean',
					'classact'	=> 'button clean',
					'width'		=> 32,
					'height'	=> 32,
					'title'		=> 'buttontitle',
					'domain'	=> $this->ID,
				],
				'toolbar');
		} elseif ($tsk == 'mail') {
			// handler for ajax request
			$this->register_action('plugin.delallold', [$this, 'clean_messages']);
			$this->register_action('plugin.downloadzip', [$this, 'downloadZip']);
		}
	}

	function insert_section ($args)
	{	//$this->logger('psections ', $args);
		$args['list']['deloldg'] = ['id'=>'deloldg','section'=>$this->gettext('deloldmsgs')];
		return $args;
	}

	function settings_blocks ($args)
	{	//$this->logger('prefslist ', $args);		//return $args;
		if ($args['section'] == 'deloldg') {
			$delete_old = $this->rc->config->get('g_delete_old');
			$whenauto = $this->rc->config->get('whenauto');
			$dold_id = 'g_deleteold';
			$auto_id = '_whenauto';
			$shtml = '<select id="'.$dold_id.'" name="'.$dold_id.'">'.$this->whenOpts($delete_old, true).'</select>';
			$rhtml = new html_select(['name' => $auto_id, 'id' => $auto_id]);
			$rhtml->add($this->gettext('autonever'), 'N');
			$rhtml->add($this->gettext('autologin'), 'I');
			$rhtml->add($this->gettext('autologout'), 'O');

			$args['blocks']['blurb']['name'] = $this->gettext('about');
			$args['blocks']['blurb']['content'] = $this->gettext('blurbcontent');

			$args['blocks']['main']['name'] = $this->gettext('mainoptions');
			$args['blocks']['main']['options']['delete_old'] = ['title' => html::label($dold_id, rcube::Q($this->gettext('deleteold'))), 'content' => $shtml];
			$args['blocks']['main']['options']['delete_oldr'] = ['title' => html::label($auto_id, rcube::Q($this->gettext('whenauto'))), 'content' => $rhtml->show($whenauto)];
		}

		return $args;
	}

	function save_settings ($args)
	{	//$this->logger('save ', [$_POST,$args]);
		if ($args['section']=='deloldg') {
			$days = $_POST['g_deleteold'];
			$args['prefs']['g_delete_old'] = $days;
			$when = $_POST['_whenauto'];
			$args['prefs']['whenauto'] = $when;
		}
		return $args;
	}

	function folder_form ($args)
	{	//$this->logger('fldfrm ', $args);
		$mbox = $args['options']['name'];
		$dolds = $this->rc->config->get('delete_old');
		$fdlts = $this->rc->config->get('ful_delete');
		$doval = $dolds[$mbox];
		$dold_id = '_deleteold';
		$html = '<select id="'.$dold_id.'" name="'.$dold_id.'">'.$this->whenOpts($doval).'</select>';
		$oldfld = ['label' => rcube::Q($this->gettext('deleteold')), 'value' => $html];
		$fuld_id = '_fuldelete';
		$ckbx = new html_checkbox(['name' => $fuld_id, 'id' => $fuld_id, 'value' => 1]);
		$fulfld = ['label' => rcube::Q($this->gettext('fuldelete')), 'value' => $ckbx->show($fdlts[$mbox])];
		$flds = [substr($dold_id, 1) => $oldfld, substr($fuld_id, 1) => $fulfld];
		$fset = ['name' => $this->gettext('deloldmsgs'), 'content' => $flds];
		$args['form']['props']['fieldsets']['deleteold'] = $fset;
		return $args;
	}

	function update_settings ($args)
	{	//$this->logger('update ', [$_POST,$args]);
		if ($_POST['_action']=='save-folder') {
			$dolds = [];
			$fdlts = [];
			if (isset($args['old']['delete_old'])) {
				$dolds = $args['old']['delete_old'];
			}
			if (isset($args['old']['ful_delete'])) {
				$fdlts = $args['old']['ful_delete'];
			}
			$mbox = $this->gpv('_mbox');		//$_POST['_mbox'];
			$fdlts[$mbox] = $this->gpv('_fuldelete');
			$days = $this->gpv('_deleteold');		//$_POST['_deleteold'];
			if ($days) {
				$dolds[$mbox] = $days;
			} else {
				unset($dolds[$mbox]);
		//		unset($fdlts[$mbox]);
			}
			$args['prefs']['delete_old'] = $dolds;
			$args['prefs']['ful_delete'] = $fdlts;
		}
		return $args;
	}

	function clean_messages ()
	{	//$this->logger('clean ', $_POST);
		$msg = ['chk'=>'numchecked','mov'=>'nummoved','zip'=>'numzipped','del'=>'numdeleted'];
		$act = $this->gpv('act');
		$mcnt = $this->cleanse($act);
		$this->cbdata['msg'] = $mcnt.$this->gettext($msg[$act]);
		$this->rc->output->command('plugin.docallback', $this->cbdata);
	}

	function login ($args)
	{	//$this->logger('login ', $args);
		if ($args['_task'] == 'mail') {
			$whenauto = $this->rc->config->get('whenauto');
			if ($whenauto == 'I') $this->cleanse('del');
		}
		return $args;
	}

	function end_session ()
	{
		$whenauto = $this->rc->config->get('whenauto');
		if ($whenauto == 'O') $this->cleanse('del');
	}

	private function cleanse ($act='chk')
	{
		$mcnt = 0;
		$allf = [];
		// get all folders with a setting
		$dold = $this->rc->config->get('delete_old', []);
	//	$this->logger('foldprefs ', $dold);
		// get the global setting
		$gdo = $this->rc->config->get('g_delete_old', 0);
		// get the storage object
		$ffd = $this->rc->config->get('flag_for_deletion');
		$storage = $this->rc->get_storage();
		// if there is a global value, start with that
		if ($gdo > 0) {
			// get all the folder names
			$a_folders = $storage->list_folders('', '*', null, null, true);
		//	$this->logger('lifolders_b ', $a_folders);
			// remove ones that have their own setting
			$a_folders = array_diff($a_folders, array_keys($dold));
		//	$this->logger('lifolders_a ', $a_folders);
			$bd = $this->beforeDate($gdo);
			foreach ($a_folders as $fld) {
				$sch = $storage->search_once($fld,'UNDELETED BEFORE '.$bd);
				$scnt = $sch->count();
				if ($scnt) {
					$mcnt += $sch->count();
					$uids = $sch->get();
					$allf[$fld] = $uids;
				//	$this->logger('searchgetg '.$fld.$bd.' ', $uids);
					if ($act=='del') {
						if ($ffd) {
							$storage->set_flag($uids, 'DELETED', $fld, true);
						} else {
							$storage->delete_message($uids, $fld);
						}
					}
				}
			}
		}
		if (is_array($dold)) foreach ($dold as $F=>$D) {
			if ($D < 0) continue;
			$bd = $this->beforeDate($D);
			$sch = $storage->search_once($F,'UNDELETED BEFORE '.$bd);
			$scnt = $sch->count();
			if ($scnt) {
				$mcnt += $sch->count();
				$uids = $sch->get();
				$allf[$F] = $uids;
			//	$this->logger('searchget-'.$F.$bd.' ', $uids);
				if ($act=='del') {
					if ($ffd) {
						$storage->set_flag($uids, 'DELETED', $F, true);
					} else {
						$storage->delete_message($uids, $F);
					}
				}
			}
		}
		if ($mcnt && $act == 'zip') {
			$this->_zip_messages($allf);
		}
		if ($act == 'mov') {
			$this->_move_messages($allf);
		}
		return $mcnt;
	}

	// invoked by client JS to download the zip file
	public function downloadZip ()
	{
		$tmpfn = $this->gpv('tmpfn');
		$zipfn = $this->gpv('zipfn');
		$this->_deliver_zipfile($tmpfn, $zipfn);
		unlink($tmpfn);
	}

	/**
	 * Helper method to pack all the given messages into a zip archive
	 *
	 * @param array List of message UIDs to download
	 */
	private function _zip_messages ($messageset)
	{	//$this->logger('zipset ', $messageset);
		$imap		= $this->rc->get_storage();
		$temp_dir	= $this->rc->config->get('temp_dir');
		$tmpfname	= tempnam($temp_dir, 'zipdownload');
		$tempfiles	= [];	//[$tmpfname];
		$folders	= count($messageset) > 1;

		// @TODO: file size limit

		// don't kill the connection if zipping takes more than 30 sec.
		@set_time_limit(0);

		// open zip file
		$zip = new ZipArchive();
		$zip->open($tmpfname, ZIPARCHIVE::OVERWRITE);

		foreach ($messageset as $mbox => $uids) {
			$imap->set_folder($mbox);
			$path = $folders ? str_replace($imap->get_hierarchy_delimiter(), '/', $mbox) . '/' : '';

			if ($uids === '*') {
				$index = $imap->index($mbox, null, null, true);
				$uids  = $index->get();
			}

			foreach ($uids as $uid) {
				$headers = $imap->get_message_headers($uid);
				$subject = rcube_mime::decode_header($headers->subject, $headers->charset);
				$subject = $this->_filename_from_subject(mb_substr($subject, 0, 16));
				$subject = $this->_convert_filename($subject);
				$disp_name = $path . $uid . ($subject ? " $subject" : '') . '.eml';
				$tmpfn = tempnam($temp_dir, 'zipmessage');
				$tmpfp = fopen($tmpfn, 'w');
//				$locfn = 'local/u'.$this->rc->user->ID.'/'.$disp_name;
//				@mkdir(dirname($locfn), 0755, true);
//				$locfp = fopen($locfn, 'w');
				$imap->get_raw_body($uid, $tmpfp);
//				$imap->get_raw_body($uid, $locfp);
//				fclose($locfp);
				$tempfiles[] = $tmpfn;
				fclose($tmpfp);
				$zip->addFile($tmpfn, $disp_name);
			}
		}

		$zip->close();

		$filename = $folders ? 'messages' : $imap->get_folder();

		$this->cbdata['tmpfn'] = $tmpfname;		//basename($tmpfname);
		$this->cbdata['zipfn'] = $filename . '.zip';

		// delete temporary files from disk
		foreach ($tempfiles as $tmpfn) {
			unlink($tmpfn);
		}

	}

	/**
	 * Helper method to move all the given messages to local storage
	 *
	 * @param array List of message UIDs to move
	 */
	private function _move_messages ($messageset)
	{	//$this->logger('zipset ', $messageset);
		$imap		= $this->rc->get_storage();
		$folders	= count($messageset) > 1;

		// @TODO: file size limit

		// don't kill the connection if moving takes more than 30 sec.
		@set_time_limit(0);

		foreach ($messageset as $mbox => $uids) {
			$imap->set_folder($mbox);
			$path = $folders ? str_replace($imap->get_hierarchy_delimiter(), '/', $mbox) . '/' : '';

			if ($uids === '*') {
				$index = $imap->index($mbox, null, null, true);
				$uids  = $index->get();
			}

			foreach ($uids as $uid) {
				$headers = $imap->get_message_headers($uid);
				$subject = rcube_mime::decode_header($headers->subject, $headers->charset);
				$subject = $this->_filename_from_subject(mb_substr($subject, 0, 16));
				$subject = $this->_convert_filename($subject);
		//		$disp_name = $path . $uid . ($subject ? " $subject" : '') . '.emlz';
				list($m,$s) = explode(' ', microtime());
				$disp_name = $path . $s.$m . '.emlz';
				$locfn = 'local/u'.$this->rc->user->ID.'/'.$disp_name;
				@mkdir(dirname($locfn), 0755, true);
			//	$locfp = fopen($locfn, 'w');
			//	$imap->get_raw_body($uid, $locfp);
			//	fclose($locfp);
				file_put_contents($locfn, gzencode($imap->get_raw_body($uid)));
			}
		}
	}

	/**
	 * Helper method to send the zip archive to the browser
	 */
	private function _deliver_zipfile ($tmpfname, $filename)
	{	//$this->logger('zipset-fn ', $tmpfname.' :: '.$filename);
		$browser = new rcube_browser;
	//	$rcmail	 = rcmail::get_instance();

		$this->rc->output->nocacheing_headers();

		if ($browser->ie)
			$filename = rawurlencode($filename);
		else
			$filename = addcslashes($filename, '"');

		// send download headers
		header("Content-Type: application/octet-stream");
		if ($browser->ie) {
			header("Content-Type: application/force-download");
		}

		// don't kill the connection if download takes more than 30 sec.
		@set_time_limit(0);
		header("Content-Disposition: attachment; filename=\"". $filename ."\"");
		header("Content-length: " . filesize($tmpfname));
		@readfile($tmpfname);
	}

	/**
	 * Helper function to convert filenames to the configured charset
	 */
	private function _convert_filename ($str)
	{
		$str = strtr($str, [':' => '', '/' => '-']);

		return rcube_charset::convert($str, RCUBE_CHARSET, 'UTF-8');
	}

	/**
	 * Helper function to convert message subject into filename
	 */
	private function _filename_from_subject ($str)
	{
		$str = preg_replace('/[\t\n\r\0\x0B]+\s*/', ' ', $str);

		return trim($str, " ./_");
	}

	// get a POSTed value
	private function gpv ($fld)
	{
		return rcube_utils::get_input_value($fld, rcube_utils::INPUT_POST);
	}

	private function includeCSS ()
	{
		$skin_path = $this->local_skin_path();
		if (is_file($this->home . "/$skin_path/delete_old.css")) {
			$this->include_stylesheet("$skin_path/delete_old.css");
		} else {
			$this->include_stylesheet('css/delete_old.css');
		}
	}

	private function whenOpts ($val, $glb=false)
	{
		$wray = ['default'=>0,'never'=>-1,'1_week'=>7,'2_week'=>14,'1_month'=>30, '2_month'=>60,'6_month'=>182,'1_year'=>364,'2_year'=>728,'3_year'=>1093,'4_year'=>1457];
		if ($glb) {
			array_shift($wray);
			array_splice($wray, 1, 2);
		}
		$opts = '';
		foreach ($wray as $K=>$V) {
			$s = $V == $val ? ' selected="selected"' : '';
			$opts .= '<option value="'.$V.'"'.$s.'>'.rcube::Q($this->gettext($K)).'</option>';
		}
		return $opts;
	}

	private function beforeDate ($days)
	{
		$d = new DateTime();
		$d->sub(new DateInterval('P'.$days.'D'));
		return $d->format('j-M-Y');
	}

	private function logger ($src, $args)
	{
		file_put_contents('_do_LOG.txt', $src . print_r($args, true), FILE_APPEND);
	}

}
