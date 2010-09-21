<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Reelocate module by Crescendo (info@crescendo.net.nz)
 */

class Reelocate_mcp {
	
	function Reelocate_mcp()
	{
		$this->EE =& get_instance();
		$this->EE->load->library('table');
		$this->EE->load->helper('form');
		$this->EE->load->helper('path');
		
		define('REELOCATE_CP', 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=reelocate');
	}
	
	function index()
	{
		$this->EE->cp->set_variable('cp_page_title', lang('reelocate_module_name'));
		
		$data = array(
			'submit_url' => REELOCATE_CP.AMP.'method=preview',
			'current_site_url' => rtrim($this->EE->config->item('site_url'), '/\\'),
			'current_site_path' => rtrim(dirname(dirname($this->EE->config->item('avatar_path'))), '/\\'),
			'site_path' => rtrim(set_realpath('..'), '/\\'),
			'cp_url' => $this->EE->config->item('site_url'),
		);
		
		$data['current_site_url'] = preg_replace('/http[s]?:\/\//i', '', $data['current_site_url']);
		$data['site_url'] = $data['current_site_url'];
		
		return $this->EE->load->view('reelocate_index', $data, TRUE);
	}
	
	function preview()
	{
		$this->EE->cp->set_breadcrumb(BASE.AMP.REELOCATE_CP, lang('reelocate_module_name'));
		$this->EE->cp->set_variable('cp_page_title', lang('reelocate_preview_changes'));
		$this->EE->lang->loadfile('admin');
		$this->EE->lang->loadfile('design');
		$this->EE->lang->loadfile('tools');
		
		$data = array(
			'config' => array(),
			'submit_url' => REELOCATE_CP.AMP.'method=update',
			'current_site_url' => rtrim($this->EE->input->post('current_site_url', TRUE), '/\\'),
			'current_site_path' => rtrim($this->EE->input->post('current_site_path', TRUE), '/\\'),
			'site_url' => rtrim($this->EE->input->post('site_url', TRUE), '/\\'),
			'site_path' => rtrim($this->EE->input->post('site_path', TRUE), '/\\')
		);
		
		if (empty($data['current_site_url']) OR empty($data['current_site_path']) OR
			empty($data['site_url']) OR empty($data['site_path']))
		{
			$this->EE->session->set_flashdata('message_failure', lang('reelocate_no_postdata'));
			$this->EE->functions->redirect(BASE.AMP.REELOCATE_CP);
		}
		
		// search for offending settings
		$search = array($data['current_site_url'], $data['current_site_path']);
		$replace = array($data['site_url'], $data['site_path']);
		
		// remove identical replacements
		foreach ($search as $id => $search_str)
		{
			if ($search_str == $replace[$id])
			{
				unset($search[$id]);
				unset($replace[$id]);
			}
		}
		
		$data['site_prefs'] = $this->_find_site_prefs($search, $replace);
		$data['upload_prefs'] = $this->_find_upload_prefs($search, $replace);
		
		return $this->EE->load->view('reelocate_preview', $data, TRUE);
	}
	
	function _find_site_prefs($search, $replace)
	{
		if (!is_array($search) OR !is_array($replace)) return;
		if (count($search) != count($replace)) return;
		
		// search for standard site preferences
		$results = array();
		foreach ($this->EE->config->config as $key => $value)
		{
			if (is_string($value))
			{
				// loop through find/replace strings
				foreach ($search as $id => $search_str)
				{
					if (stripos($value, $search_str) !== FALSE)
					{
						$results[$key]['title'] = lang($key);
						$results[$key]['current'] = $value;
						$results[$key]['current_html'] = preg_replace('/('.preg_quote($search_str, '/').')/i', '<strong>$1</strong>', $value);
						$results[$key]['updated'] = str_ireplace($search_str, $replace[$id], $value);
					}
				}
			}
		}
		
		ksort($results);
		return $results;
	}
	
	function _find_upload_prefs($search, $replace)
	{
		if (!is_array($search) OR !is_array($replace)) return;
		if (count($search) != count($replace)) return;
		
		// search for upload preferences
		$results = array();
		$upload_prefs = $this->EE->db->get('upload_prefs')->result_array();
		// loop through upload directories
		foreach ($upload_prefs as $row)
		{
			// loop through find/replace strings
			foreach ($search as $id => $search_str)
			{
				// loop through upload preference names
				foreach (array('server_path', 'url') as $upload_pref_name)
				{
					if (stripos($row[$upload_pref_name], $search_str) !== FALSE)
					{
						$key = 'upload_dir_'.$row['id'].'_'.$upload_pref_name;
						$results[$key]['title'] = $row['name'].' '.lang($upload_pref_name);
						$results[$key]['current'] = $row[$upload_pref_name];
						$results[$key]['current_html'] = preg_replace('/('.preg_quote($search_str, '/').')/i', '<strong>$1</strong>', $row[$upload_pref_name]);
						$results[$key]['updated'] = str_ireplace($search_str, $replace[$id], $row[$upload_pref_name]);
					}
				}
			}
		}
		
		ksort($results);
		return $results;
	}
	
	function update()
	{
		$this->EE->cp->set_breadcrumb(BASE.AMP.REELOCATE_CP, lang('reelocate_module_name'));
		$this->EE->cp->set_variable('cp_page_title', lang('reelocate_success'));
		
		if ($this->EE->input->post('submit') === FALSE)
		{
			$this->EE->session->set_flashdata('message_failure', lang('reelocate_no_postdata'));
			$this->EE->functions->redirect(BASE.AMP.REELOCATE_CP);
		}
		
		$site_prefs = array();
		$upload_prefs = array();
		
		// loop through the POST data
		foreach ($_POST as $key => $value)
		{
			if ($value === 'y' AND $pos = strpos($key, '_update'))
			{
				$setting_id = substr($key, 0, $pos);
				
				// find out what type of setting we are dealing with
				if ($this->EE->config->item($setting_id) !== FALSE)
				{
					// standard site preference
					$site_prefs[$setting_id] = $this->EE->input->post($setting_id, TRUE);
				}
				elseif (strpos($setting_id, 'upload_dir_') !== FALSE)
				{
					// upload dir preference
					preg_match('/upload_dir_([\d]+)_([\w]+)/i', $setting_id, $matches);
					$upload_prefs[$matches[1]][$matches[2]] = $this->EE->input->post($setting_id, TRUE);
				}
			}
		}
		
		// update the site prefs
		$this->EE->config->update_site_prefs($site_prefs);
		$updated_count = count($site_prefs);
		
		// update the upload prefs
		foreach ($upload_prefs as $id => $data)
		{
			$this->EE->db->update('upload_prefs', $data, array('id' => $id));
			$updated_count += count($data);
		}
		
		return sprintf(lang('reelocate_updated'), $updated_count);
	}
}

/* End of file mcp.reelocate.php */