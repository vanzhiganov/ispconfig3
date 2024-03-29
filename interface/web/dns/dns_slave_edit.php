<?php

/*
Copyright (c) 2007, Till Brehm, projektfarm Gmbh
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.
    * Neither the name of ISPConfig nor the names of its contributors
      may be used to endorse or promote products derived from this software without
      specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

/******************************************
* Begin Form configuration
******************************************/

$tform_def_file = "form/dns_slave.tform.php";

/******************************************
* End Form configuration
******************************************/

require_once('../../lib/config.inc.php');
require_once('../../lib/app.inc.php');

//* Check permissions for module
$app->auth->check_module_permissions('dns');

// Loading classes
$app->uses('tpl,tform,tform_actions,validate_dns');
$app->load('tform_actions');

class page_action extends tform_actions {
	
	function onShowNew() {
		global $app, $conf;
		
		// we will check only users, not admins
		if($_SESSION["s"]["user"]["typ"] == 'user') {
			if(!$app->tform->checkClientLimit('limit_dns_slave_zone')) {
				$app->error($app->tform->wordbook["limit_dns_slave_zone_txt"]);
			}
			if(!$app->tform->checkResellerLimit('limit_dns_slave_zone')) {
				$app->error('Reseller: '.$app->tform->wordbook["limit_dns_slave_zone_txt"]);
			}
		}
		
		parent::onShowNew();
	}
	
	function onShowEnd() {
		global $app, $conf;
		
		// If user is admin, we will allow him to select to whom this record belongs
		if($_SESSION["s"]["user"]["typ"] == 'admin') {
			// Getting Domains of the user
			$sql = "SELECT sys_group.groupid, sys_group.name, CONCAT(client.company_name,' :: ',client.contact_name) as contactname FROM sys_group, client WHERE sys_group.client_id = client.client_id AND sys_group.client_id > 0 ORDER BY sys_group.name";
			$clients = $app->db->queryAllRecords($sql);
			$client_select = '';
			if($_SESSION["s"]["user"]["typ"] == 'admin') $client_select .= "<option value='0'></option>";
			//$tmp_data_record = $app->tform->getDataRecord($this->id);
			if(is_array($clients)) {
				foreach( $clients as $client) {
					$selected = @(is_array($this->dataRecord) && ($client["groupid"] == $this->dataRecord['client_group_id'] || $client["groupid"] == $this->dataRecord['sys_groupid']))?'SELECTED':'';
					$client_select .= "<option value='$client[groupid]' $selected>$client[name] :: $client[contactname]</option>\r\n";
				}
			}
		$app->tpl->setVar("client_group_id",$client_select);
		} else if($app->auth->has_clients($_SESSION['s']['user']['userid'])) {
		
			// Get the limits of the client
			$client_group_id = $_SESSION["s"]["user"]["default_group"];
			$client = $app->db->queryOneRecord("SELECT client.client_id, sys_group.name, client.contact_name, CONCAT(client.company_name,' :: ',client.contact_name) as contactname FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = $client_group_id");
			
			// Fill the client select field
			$sql = "SELECT sys_group.groupid, sys_group.name, CONCAT(client.company_name,' :: ',client.contact_name) as contactname FROM sys_group, client WHERE sys_group.client_id = client.client_id AND client.parent_client_id = ".$client['client_id']." ORDER BY sys_group.name";
			$clients = $app->db->queryAllRecords($sql);
			$tmp = $app->db->queryOneRecord("SELECT groupid FROM sys_group WHERE client_id = ".$client['client_id']);
			$client_select = '<option value="'.$tmp['groupid'].'">'.$client['name'].' :: '.$client['contactname'].'</option>';
			//$tmp_data_record = $app->tform->getDataRecord($this->id);
			if(is_array($clients)) {
				foreach( $clients as $client) {
					$selected = @(is_array($this->dataRecord) && ($client["groupid"] == $this->dataRecord['client_group_id'] || $client["groupid"] == $this->dataRecord['sys_groupid']))?'SELECTED':'';
					$client_select .= "<option value='$client[groupid]' $selected>$client[name] :: $client[contactname]</option>\r\n";
				}
			}
			$app->tpl->setVar("client_group_id",$client_select);
		
		}
		
		if($this->id > 0) {
			//* we are editing a existing record
			$app->tpl->setVar("edit_disabled", 1);
			$app->tpl->setVar("server_id_value", $this->dataRecord["server_id"]);
		} else {
			$app->tpl->setVar("edit_disabled", 0);
		}
		
		parent::onShowEnd();
	}
	
	function onSubmit() {
		global $app, $conf;
		
		if($_SESSION["s"]["user"]["typ"] != 'admin') {
			// Get the limits of the client
			$client_group_id = $_SESSION["s"]["user"]["default_group"];
			$client = $app->db->queryOneRecord("SELECT limit_dns_slave_zone, default_dnsserver FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = $client_group_id");
		
			// When the record is updated
			if($this->id > 0) {
				// restore the server ID if the user is not admin and record is edited
				$tmp = $app->db->queryOneRecord("SELECT server_id FROM dns_slave WHERE id = ".$app->functions->intval($this->id));
				$this->dataRecord["server_id"] = $tmp["server_id"];
				unset($tmp);
			// When the record is inserted
			} else {
				// set the server ID to the default dnsserver of the client
				$this->dataRecord["server_id"] = $client["default_dnsserver"];
				
				// Check if the user may add anoter secondary domain.
				if(!$app->tform->checkClientLimit('limit_dns_slave_zone')) {
					$app->error($app->tform->wordbook["limit_dns_slave_zone_txt"]);
				}
				if(!$app->tform->checkResellerLimit('limit_dns_slave_zone')) {
					$app->error('Reseller: '.$app->tform->wordbook["limit_dns_slave_zone_txt"]);
				}
			}
		}
		
		//* Check if the zone name has a dot at the end
		if(strlen($this->dataRecord["origin"]) > 0 && substr($this->dataRecord["origin"],-1,1) != '.') $this->dataRecord["origin"] .= '.';
	
		//* Check if a primary zone with the same name already exists 	
		$tmp = $app->db->queryOneRecord("SELECT count(id) as number FROM dns_soa WHERE origin = \"".$this->dataRecord["origin"]."\" AND server_id= \"".$this->dataRecord["server_id"]."\"");
		if($tmp["number"] > 0) {
  			$app->error($app->tform->wordbook["origin_error_unique"]);
		}

		parent::onSubmit();
	}
	
	function onInsert() {
		global $app, $conf;
		
		// Check if record is existing already
		$duplicate_slave = $app->db->queryOneRecord("SELECT * FROM dns_slave WHERE origin = '".$this->dataRecord["origin"]."' AND server_id = ".$app->functions->intval($this->dataRecord["server_id"])." AND ".$app->tform->getAuthSQL('r'));
		
		if(is_array($duplicate_slave) && !empty($duplicate_slave)) $app->error($app->tform->wordbook["origin_error_unique"]);
		
		parent::onInsert();
	}
	
	function onAfterInsert() {
		global $app, $conf;
		
		// make sure that the record belongs to the client group and not the admin group when a dmin inserts it
		if($_SESSION["s"]["user"]["typ"] == 'admin' && isset($this->dataRecord["client_group_id"])) {
			$client_group_id = $app->functions->intval($this->dataRecord["client_group_id"]);
			$app->db->query("UPDATE dns_slave SET sys_groupid = $client_group_id WHERE id = ".$this->id);
		}
		if($app->auth->has_clients($_SESSION['s']['user']['userid']) && isset($this->dataRecord["client_group_id"])) {
			$client_group_id = $app->functions->intval($this->dataRecord["client_group_id"]);
			$app->db->query("UPDATE dns_slave SET sys_groupid = $client_group_id WHERE id = ".$this->id);
		}

	}
	
	function onAfterUpdate() {
		global $app, $conf;
		
		$tmp = $app->db->diffrec($this->oldDataRecord,$app->tform->getDataRecord($this->id));

		// make sure that the record belongs to the client group and not the admin group when a dmin inserts it
		if($_SESSION["s"]["user"]["typ"] == 'admin' && isset($this->dataRecord["client_group_id"])) {
			$client_group_id = $app->functions->intval($this->dataRecord["client_group_id"]);
			$app->db->query("UPDATE dns_slave SET sys_groupid = $client_group_id WHERE id = ".$this->id);
		}
		if($app->auth->has_clients($_SESSION['s']['user']['userid']) && isset($this->dataRecord["client_group_id"])) {
			$client_group_id = $app->functions->intval($this->dataRecord["client_group_id"]);
			$app->db->query("UPDATE dns_slave SET sys_groupid = $client_group_id WHERE id = ".$this->id);
		}
		
		//** When the client group has changed, change also the owner of the record if the owner is not the admin user
		if($this->oldDataRecord["client_group_id"] != $this->dataRecord["client_group_id"] && $this->dataRecord["sys_userid"] != 1) {
			$client_group_id = $app->functions->intval($this->dataRecord["client_group_id"]);
			$tmp = $app->db->queryOneREcord("SELECT userid FROM sys_user WHERE default_group = ".$client_group_id);
			if($tmp["userid"] > 0) {
				$app->db->query("UPDATE dns_slave SET sys_userid = ".$tmp["userid"]." WHERE id = ".$this->id);
			}
		}
		
	}
	
}

$page = new page_action;
$page->onLoad();

?>
