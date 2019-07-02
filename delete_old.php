<?php

class delete_old extends rcube_plugin
{
	public $task = 'login|logout|mail|settings';

	private $rc;

	function init ()
	{
		$this->rc = rcmail::get_instance();
		$this->add_texts('localization', array('deloldcfrm','deloldmsgs','dlgtxt','check','delete','checking','deleting'));
		$tsk = $this->rc->task;
		if ($tsk == 'settings') {
			$this->includeCSS();
			$this->add_hook('preferences_sections_list', array($this, 'insert_section'));
			$this->add_hook('preferences_list', array($this, 'settings_blocks'));
			$this->add_hook('preferences_save', array($this, 'save_settings'));
			$this->add_hook('folder_form', array($this, 'folder_form'));
			$this->add_hook('preferences_update', array($this, 'update_settings'));
		} elseif ($this->rc->task == 'login') {
			$this->add_hook('login_after', array($this, 'login'));
		} elseif ($this->rc->task == 'logout') {
			$this->add_hook('session_destroy', array($this, 'end_session'));
		} elseif ($tsk == 'mail' && ($this->rc->action == '' || $this->rc->action == 'show')) {
			$this->includeCSS();
			$this->include_script('delete_old.js');
			$this->add_button(
				array(
					'type'		=> 'link',
					'label'		=> 'buttontext',
					'command'	=> 'plugin.delete_old',
					'class'		=> 'button clean',
					'classact'	=> 'button clean',
					'width'		=> 32,
					'height'	=> 32,
					'title'		=> 'buttontitle',
					'domain'	=> $this->ID,
				),
				'toolbar');
		} elseif ($tsk == 'mail') {
			// handler for ajax request
			$this->register_action('plugin.delallold', array($this, 'clean_messages'));
		}
	}

	function insert_section ($args)
	{	//$this->logger('psections ', $args);
		$args['list']['deloldg'] = array('id'=>'deloldg','section'=>$this->gettext('deloldmsgs'));
		return $args;
	}

	function settings_blocks ($args)
	{	//$this->logger('prefslist ', $args);		//return $args;
		if ($args['section'] == 'deloldg') {
			$delete_old = $this->rc->config->get('g_delete_old');
			$whenauto = $this->rc->config->get('whenauto');
			$field_id = 'g_deleteold';
			$shtml = '<select id="'.$field_id.'" name="_deleteold">'.$this->whenOpts($delete_old, true).'</select>';
			$rhtml = new html_select(array('name'=>'_whenauto'));
			$rhtml->add($this->gettext('autonever'), 'N');
			$rhtml->add($this->gettext('autologin'), 'I');
			$rhtml->add($this->gettext('autologout'), 'O');

			$args['blocks']['blurb']['name'] = $this->gettext('about');
			$args['blocks']['blurb']['content'] = $this->gettext('blurbcontent');

			$args['blocks']['main']['name'] = $this->gettext('mainoptions');
			$args['blocks']['main']['options']['delete_old'] = array(
				'title' => html::label($field_id, rcube::Q($this->gettext('deleteold'))),
				'content' => $shtml
			);
			$args['blocks']['main']['options']['delete_oldr'] = array(
				'title' => html::label($field_id, rcube::Q($this->gettext('whenauto'))),
				'content' => $rhtml->show($whenauto)
			);
		}

		return $args;
	}

	function save_settings ($args)
	{	//$this->logger('save ', array($_POST,$args));
		if ($args['section']=='deloldg') {
			$days = $_POST['_deleteold'];
			$args['prefs']['g_delete_old'] = $days;
			$when = $_POST['_whenauto'];
			$args['prefs']['whenauto'] = $when;
		}
		return $args;
	}

	function folder_form ($args)
	{
		$cfg = $this->rc->config->get('delete_old');
		$mbox_imap = $args['options']['name'];
		$myvalue = $cfg[$mbox_imap];
		$field_id = '_deleteold';
		$html = '<select id="'.$field_id.'" name="_deleteold">'.$this->whenOpts($myvalue).'</select>';
		$args['form']['props']['fieldsets']['settings']['content']['deleteold'] = array(
			'label' => rcube::Q($this->gettext('deleteold')),
			'value' => $html
		);
		return $args;
	}

	function update_settings ($args)
	{	//$this->logger('update ', array($_POST,$args));
		if ($_POST['_action']=='save-folder') {
			$dolds = [];
			if (isset($args['old']['delete_old'])) {
				$dolds = $args['old']['delete_old'];
			}
			$mbox = $_POST['_mbox'];
			$days = $_POST['_deleteold'];
			if ($days) {
				$dolds[$mbox] = $days;
			} else {
				unset($dolds[$mbox]);
			}
			$args['prefs']['delete_old'] = $dolds;
		}
		return $args;
	}

	function clean_messages ()
	{	//$this->logger('clean ', $_POST);
		$ck = isset($_POST['ck']) ? true : false;
		$mcnt = $this->cleanse($ck);
		$this->rc->output->command('plugin.docallback', array('msg' => $mcnt.$this->gettext($ck?'numchecked':'numdeleted')));
	}

	function login ($args)
	{	//$this->logger('login ', $args);
		if ($args['_task'] == 'mail') {
			$whenauto = $this->rc->config->get('whenauto');
			if ($whenauto == 'I') $this->cleanse();
		}
		return $args;
	}

	function end_session ()
	{
		$whenauto = $this->rc->config->get('whenauto');
		if ($whenauto == 'O') $this->cleanse();
	}


	private function cleanse ($ck=true)
	{
		$mcnt = 0;
		// get all folders with a setting
		$dold = $this->rc->config->get('delete_old', array());
	//	$this->logger('foldprefs ', $dold);
		// get the global setting
		$gdo = $this->rc->config->get('g_delete_old');
		// get the storage object
		$ffd = $this->rc->config->get('flag_for_deletion');
		$storage = $this->rc->get_storage();
		// if there is a global value, start with that
		if ($gdo > 0) {
			// get all the folder names
			$a_folders = $storage->list_folders('', '*', null, null, true);
	//		$this->logger('lifolders_b ', $a_folders);
			// remove ones that have their own setting
			$a_folders = array_diff($a_folders, array_keys($dold));
	//		$this->logger('lifolders_a ', $a_folders);
			$bd = $this->beforeDate($gdo);
			foreach ($a_folders as $fld) {
				$sch = $storage->search_once($fld,'UNDELETED BEFORE '.$bd);
				$scnt = $sch->count();
				if ($scnt) {
					$mcnt += $sch->count();
					$uids = $sch->get();
	//				$this->logger('searchgetg '.$fld.$bd.' ', $uids);
					if (!$ck) {
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
	//			$this->logger('searchget-'.$F.$bd.' ', $uids);
				if (!$ck) {
					if ($ffd) {
						$storage->set_flag($uids, 'DELETED', $F, true);
					} else {
						$storage->delete_message($uids, $F);
					}
				}
			}
		}
		return $mcnt;
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
		$wray = array('default'=>0,'never'=>-1,'1_week'=>7,'2_week'=>14,'1_month'=>30,
			'2_month'=>60,'6_month'=>182,'1_year'=>364,'2_year'=>728,'3_year'=>1093,'4_year'=>1457,'5_year'=>1821,'10_year'=>3642,'15_year'=>5464);
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
		file_put_contents('LOG.txt', $src . print_r($args, true), FILE_APPEND);
	}

}
