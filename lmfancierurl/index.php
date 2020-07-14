<?php
/*
    LMFancierURL Nucleus plugin
    Copyright (C) 2011-2013 Leo (www.slightlysome.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. 
	(http://www.gnu.org/licenses/gpl-2.0.html)
*/

	$strRel = '../../../'; 
	require($strRel . 'config.php');
	include_libs('PLUGINADMIN.php');

	$oPluginAdmin	= new PluginAdmin('LMFancierURL');
	$pluginURL 		= $oPluginAdmin->plugin->getAdminURL();
	$plugName		= $oPluginAdmin->plugin->getName();
	
	if(!$oPluginAdmin->plugin->_checkURLPartsSourceVersion() || !$oPluginAdmin->plugin->_checkURLPartsDataVersion())
	{
		$oPluginAdmin->start();
		echo '<h2>'.htmlspecialchars($plugName, ENT_QUOTES, _CHARSET).' Administration</h2>';
		echo '<p>The issues with the LMURLParts plugin must be resolved before the LMFancierURL plugin admin page can be used.</p>';
		$oPluginAdmin->end();
		exit;
	}

	_pluginDataUpgrade($oPluginAdmin);

	if(!($member->isLoggedIn()))
	{
		$oPluginAdmin->start();
		echo '<p>You must be logged in to use the LMFancierURL plugin admin area.</p>';
		$oPluginAdmin->end();
		exit;
	}

	if(!($member->isAdmin()))
	{
		$oPluginAdmin->start();
		echo '<p>You must be admin to use the LMFancierURL plugin admin area.</p>';
		$oPluginAdmin->end();
		exit;
	}

	$action = requestVar('action');

	$oPluginAdmin->start("<style type='text/css'>
	<!--
		p.message {	font-weight: bold; }
		p.error { font-size: 100%; font-weight: bold; color: #880000; }
		iframe { width: 100%; height: 400px; border: 1px solid gray; }
		div.dialogbox { border: 1px solid #ddd; background-color: #F6F6F6; margin: 18px 0 1.5em 0; }
		div.dialogbox h4 { background-color: #bbc; color: #000; margin: 0; padding: 5px; }
		div.dialogbox h4.light { background-color: #ddd; }
		div.dialogbox div { margin: 0; padding: 10px; }
		div.dialogbox button { margin: 10px 0 0 6px; float: right; }
		div.dialogbox p { margin: 0; }
		div.dialogbox p.buttons { text-align: right; overflow: auto; }
		div.dialogbox label { float: left; width: 150px; }

	-->
	</style>");

	if($action == 'showhelp')
	{
        echo '<p><a href="'.$pluginURL.'?skipupgradehandling=1">(Back to '.htmlspecialchars($plugName, ENT_QUOTES, _CHARSET).' administration)</a></p>';
		echo '<h2>Helppage for plugin: '.htmlspecialchars($plugName, ENT_QUOTES, _CHARSET).'</h2>';
	
		$helpFile = $DIR_PLUGINS.$oPluginAdmin->plugin->getShortName().'/help.html';
		
       if (@file_exists($helpFile)) 
	   {
            @readfile($helpFile);
        } 
		else 
		{
            echo '<p class="error">Missing helpfile.</p>';
        }
		
		$oPluginAdmin->end();
		exit;
	}
	
	echo '<h2>'.htmlspecialchars($plugName, ENT_QUOTES, _CHARSET).' Administration</h2>';

	$actions = array('rebuildurlparts', 'rebuildurlparts_process', 'updateskinparturlparts', 'updateskinparturlparts_process',
				'editcustombaseurl', 'editcustombaseurl_process', 'enablecustombaseurl');

	if (in_array($action, $actions)) 
	{ 
		if (!$manager->checkTicket())
		{
			echo '<p class="error">Error: Bad ticket</p>';
		} 
		else 
		{
			call_user_func('_lmfancierurl_' . $action);
		}
	}
	else
	{
		lShowCustomBaseURL();
	}
	
	$oPluginAdmin->end();
	exit;

	function lShowCustomBaseURL($message = '')
	{
		global $oPluginAdmin, $manager, $pluginURL, $CONF;

		$plugid = $oPluginAdmin->plugin->getID();
		
		echo '<h3>URL Parts</h3>';
		echo '<div class="dialogbox">';
		echo '<h4 class="light">Rebuild URL parts</h4><div>';
		echo '<form method="post" action="'.$pluginURL.'">';
		$manager->addTicketHidden();
		echo '<input type="hidden" name="action" value="rebuildurlparts" />';
		echo '<p>This function will rebuild the LMFancierURL URL parts data stored in the LMURLParts plugin. ';
		echo 'It can be used if the URL parts data get out of sync with the actual objects on the blog site. ';
		echo 'The URL part name on unlocked objects may change when this function is used.</p>';
		echo '<p class="buttons"><input type="submit" value="Rebuild URL parts" />';
		echo '</p></form></div></div>';

		echo '<div class="dialogbox">';
		echo '<h4 class="light">Update special skin part URL parts</h4><div>';
		echo '<form method="post" action="'.$pluginURL.'">';
		$manager->addTicketHidden();
		echo '<input type="hidden" name="action" value="updateskinparturlparts" />';
		echo '<p>Because of a shortcoming in the Nuclues core can the LMFancierURL plugin not automatically catch special ';
		echo 'skin parts that are added to or removed from a skin after the plugin is installed. ';
		echo 'This function will update the special skin part URL parts stored in the LMURLParts plugin. ';
		echo 'It must be used after you have added a special skin part to a skin or removed a special skin parts from a skin.</p>';
		echo '<p class="buttons"><input type="submit" value="Update special skin part URL parts" />';
		echo '</p></form></div></div>';

		echo '<h3>Custom Base URLs</h3>';
		echo $message;
		echo '<table><thead><tr>';
		echo '<th>Blog</th><th>Custom Base URL</th><th>Enabled</th><th>Default Blog</th><th>Identifies Blog</th><th colspan="2">Actions</th>';
		echo '</tr></thead>';

		$aCustomBaseURLInfo = $oPluginAdmin->plugin->_getCustomBaseURLWithBlogNameAll();

		foreach($aCustomBaseURLInfo as $aCustomBaseURL)
		{
			$editURL = $manager->addTicketToUrl($pluginURL . '?action=editcustombaseurl&blogid='.$aCustomBaseURL['blogid']);
			$editLink = '<a href="'.$editURL.'" title="Edit custom base URL for &quot;'.htmlspecialchars($aCustomBaseURL['blogname'], ENT_QUOTES, _CHARSET).'&quot;">Edit</a>';
		
			if($aCustomBaseURL['enablebaseurl'])
			{
				$enablebaseurl = 'Yes';
				
				$enableDisableURL = $manager->addTicketToUrl($pluginURL . '?action=enablecustombaseurl&blogid='.$aCustomBaseURL['blogid'].'&enablebaseurl=0');
				$enableDisableLink = '<a href="'.$enableDisableURL.'" title="Disable custom base URL for &quot;'.htmlspecialchars($aCustomBaseURL['blogname'], ENT_QUOTES, _CHARSET).'&quot;">Disable</a>';
			}
			else
			{
				$enablebaseurl = 'No';

				$enableDisableURL = $manager->addTicketToUrl($pluginURL . '?action=enablecustombaseurl&blogid='.$aCustomBaseURL['blogid'].'&enablebaseurl=1');
				$enableDisableLink = '<a href="'.$enableDisableURL.'" title="Enable custom base URL for &quot;'.htmlspecialchars($aCustomBaseURL['blogname'], ENT_QUOTES, _CHARSET).'&quot;">Enable</a>';
			}
			
			if($aCustomBaseURL['defaultblog'])
			{
				$defaultblog = 'Yes';
			}
			else
			{
				$defaultblog = 'No';
			}

			if($aCustomBaseURL['identifiesblog'])
			{
				$identifiesblog = 'Yes';
			}
			else
			{
				$identifiesblog = 'No';
			}

			echo '<tr onmouseover="focusRow(this);" onmouseout="blurRow(this);">';

			echo '<td>'.htmlspecialchars($aCustomBaseURL['blogname'], ENT_QUOTES, _CHARSET).'</td><td>'.htmlspecialchars($aCustomBaseURL['baseurl'], ENT_QUOTES, _CHARSET).'</td><td>'.$enablebaseurl.'</td><td>'.$defaultblog.'</td><td>'.$identifiesblog.'</td><td>'.$editLink.'</td><td>'.$enableDisableLink.'</td>';
			echo '</tr>';		
		}
		echo '</table>';

		echo '<h3>General</h3>';
		echo '<div class="dialogbox">';
		echo '<h4 class="light">Plugin help page</h4>';
		echo '<div>';
		echo '<p>The help page for this plugin is available <a href="'.$pluginURL.'?action=showhelp">here</a>.</p>';
		echo '</div></div>';
		echo '<div class="dialogbox">';
		echo '<h4 class="light">Plugin options page</h4>';
		echo '<div>';
		echo '<p>The options page for this plugin is available <a href="'.$CONF['AdminURL'].'index.php?action=pluginoptions&plugid='.$plugid.'">here</a>.</p>';
		echo '</div></div>';
	}

	function _lmfancierurl_rebuildurlparts()
	{
		global $oPluginAdmin, $manager, $pluginURL;

		$historygo = intRequestVar('historygo');
		$historygo--;
		
		echo '<div class="dialogbox">';
		echo '<form method="post" action="'.$pluginURL.'">';
		$manager->addTicketHidden();
		echo '<input type="hidden" name="action" value="rebuildurlparts_process" />';
		echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
		echo '<h4 class="light">Rebuild URL parts</h4><div>';
		echo '<p>This function may change the URL part name of unlocked objects. This means that links for some objects in the blog site may be different after this function is executed.</p>';
		echo '<br /><p>Are you sure you want to rebuild URL parts?</p>';
		echo '<p class="buttons">';
		echo '<input type="hidden" name="sure" value="yes" /">';
		echo '<input type="submit" value="Rebuild URL parts" />';
		echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
		echo '</p>';
		echo '</div></form></div>';
	}
	
	function _lmfancierurl_rebuildurlparts_process()
	{
		global $oPluginAdmin;

		if (requestVar('sure') == 'yes')
		{
			if($oPluginAdmin->plugin->_initializeAllURLParts())
			{
				echo '<p class="message">URL parts have now been rebuilt.</p>';
			}
			else
			{
				echo '<p class="error">URL parts rebuild has failed.</p>';
			}
		}
	}
		
	function _lmfancierurl_updateskinparturlparts()
	{
		global $oPluginAdmin, $manager, $pluginURL;

		$historygo = intRequestVar('historygo');
		$historygo--;
		
		echo '<div class="dialogbox">';
		echo '<form method="post" action="'.$pluginURL.'">';
		$manager->addTicketHidden();
		echo '<input type="hidden" name="action" value="updateskinparturlparts_process" />';
		echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
		echo '<h4 class="light">Update special skin part URL parts</h4><div>';
		echo '<p>Are you sure you want to update special skin part URL parts?</p>';
		echo '<p class="buttons">';
		echo '<input type="hidden" name="sure" value="yes" /">';
		echo '<input type="submit" value="Update special skin part URL parts" />';
		echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
		echo '</p>';
		echo '</div></form></div>';
	}

	function _lmfancierurl_updateskinparturlparts_process()
	{
		global $oPluginAdmin;

		if (requestVar('sure') == 'yes')
		{
			if($oPluginAdmin->plugin->_initializeSpecialPartForAllBlogs())
			{
				echo '<p class="message">Special skin part URL parts has now been updated.</p>';
			}
			else
			{
				echo '<p class="error">Special skin part URL parts update has failed.</p>';
			}
		}
	}

	function _lmfancierurl_editcustombaseurl($blogid = 0, $baseurl = '', $enablebaseurl = 0, $defaultblog = 0, $identifiesblog = 0, $blogname = '')
	{
		global $oPluginAdmin, $manager, $pluginURL;

		if(!$blogid)
		{
			$blogid = intRequestVar('blogid');

			if($blogid)
			{
				$aCustomBaseURL = $oPluginAdmin->plugin->_getCustomBaseURLWithBlogNameFromBlogID($blogid);
				
				if($aCustomBaseURL)
				{
					$aCustomBaseURL = $aCustomBaseURL['0'];

					$baseurl = $aCustomBaseURL['baseurl'];
					$enablebaseurl = $aCustomBaseURL['enablebaseurl'];
					$defaultblog = $aCustomBaseURL['defaultblog'];
					$identifiesblog = $aCustomBaseURL['identifiesblog'];
					$blogname = $aCustomBaseURL['blogname'];
				}
				else
				{
					$blogid = 0;
				}
			}
		}
		
		if($blogid)
		{
			$historygo = intRequestVar('historygo');
			$historygo--;
		
			echo '<div class="dialogbox">';
			echo '<form method="post" action="'.$pluginURL.'">';
			$manager->addTicketHidden();
			echo '<input type="hidden" name="action" value="editcustombaseurl_process" />';
			echo '<input type="hidden" name="blogid" value="'.$blogid.'" />';
			echo '<input type="hidden" name="blogname" value="'.htmlspecialchars($blogname, ENT_QUOTES, _CHARSET).'" />';
			echo '<input type="hidden" name="enablebaseurl" value="'.$enablebaseurl.'" />';
			echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
			echo '<h4>Edit custom base URL for &quot;'.htmlspecialchars($blogname, ENT_QUOTES, _CHARSET).'&quot;</h4><div>';
			echo '<p><label for="baseurl">Custom base URL:</label> ';
			echo '<input type="text" name="baseurl" size="40" value="'.htmlspecialchars($baseurl, ENT_QUOTES, _CHARSET).'" />';
			
			echo '<p><label for="defaultblog">Default blog:</label> ';
			echo '<select name="defaultblog"><option value="1"';

			if($defaultblog)
			{
				echo ' selected="selected"';
			}
			echo '>Yes</option><option value="0"';

			if(!$defaultblog)
			{
				echo ' selected="selected"';
			}		
			echo '>No</option></select></p>';

			echo '<p><label for="identifiesblog">Identifes blog:</label> ';
			echo '<select name="identifiesblog"><option value="1"';

			if($identifiesblog)
			{
				echo ' selected="selected"';
			}
			echo '>Yes</option><option value="0"';

			if(!$identifiesblog)
			{
				echo ' selected="selected"';
			}		
			echo '>No</option></select></p>';
			
			echo '<p class="buttons">';
			echo '<input type="hidden" name="sure" value="yes" /">';
			echo '<input type="submit" value="Edit" />';
			echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
			echo '</p>';
			echo '</div></form></div>';
		}
		else
		{
			lShowCustomBaseURL();
		}
	}

	function _lmfancierurl_editcustombaseurl_process()
	{
		global $oPluginAdmin, $manager, $pluginURL;

		if (requestVar('sure') == 'yes')
		{
			$blogid = intRequestVar('blogid');
			$blogname = requestVar('blogname');
			$enablebaseurl = intRequestVar('enablebaseurl');
			$baseurl = trim(requestVar('baseurl'));
			$defaultblog = intRequestVar('defaultblog');
			$identifiesblog = intRequestVar('identifiesblog');
		
			if($blogid)
			{
				if($baseurl)
				{
					if(substr($baseurl, 0, 7) <> 'http://')
					{
						echo '<p class="error">A custom base URL must start with &quot;http://&quot;.</p>';
						_lmfancierurl_editcustombaseurl($blogid, $baseurl, $enablebaseurl, $defaultblog, $identifiesblog, $blogname);
						return;
					}

					if(substr($baseurl, -1, 1) == '/')
					{
						echo '<p class="error">A custom base URL can not end with a &quot;/&quot;.</p>';
						_lmfancierurl_editcustombaseurl($blogid, $baseurl, $enablebaseurl, $defaultblog, $identifiesblog, $blogname);
						return;
					}
				}

				if($oPluginAdmin->plugin->_updateCustomBaseURL($blogid, $baseurl, $enablebaseurl, $defaultblog, $identifiesblog))
				{
					$message = '<p class="message">Updated custom base URL for &quot;'.htmlspecialchars($blogname, ENT_QUOTES, _CHARSET).'&quot;</p>';
					lShowCustomBaseURL($message);
				}
				else
				{
					echo '<p class="error">Update failed.</p>';
					_lmfancierurl_editcustombaseurl($blogid, $baseurl, $enablebaseurl, $defaultblog, $identifiesblog, $blogname);
					return;
				}
			}
			else
			{
				lShowCustomBaseURL();
			}
		}
		else
		{
			// User cancelled
			lShowCustomBaseURL();
		}
	}

	function _lmfancierurl_enablecustombaseurl()
	{
		global $oPluginAdmin, $manager, $pluginURL;
		
		$blogid = intRequestVar('blogid');
		
		if($blogid)
		{
			$aCustomBaseURL = $oPluginAdmin->plugin->_getCustomBaseURLWithBlogNameFromBlogID($blogid);
				
			if($aCustomBaseURL)
			{
				$aCustomBaseURL = $aCustomBaseURL['0'];

				$baseurl = $aCustomBaseURL['baseurl'];
				$defaultblog = $aCustomBaseURL['defaultblog'];
				$identifiesblog = $aCustomBaseURL['identifiesblog'];
				$blogname = $aCustomBaseURL['blogname'];
			}
			else
			{
				$blogid = 0;
			}
		}

		if($blogid)
		{
			$enablebaseurl = intRequestVar('enablebaseurl');

			if($oPluginAdmin->plugin->_updateCustomBaseURL($blogid, $baseurl, $enablebaseurl, $defaultblog, $identifiesblog))
			{
				if($enablebaseurl)
				{
					$enable = 'Enabled';
				}
				else
				{
					$enable = 'Disabled';
				}
				
				$message = '<p class="message">'.$enable.' custom base URL for &quot;'.htmlspecialchars($blogname, ENT_QUOTES, _CHARSET).'&quot;</p>';
				lShowCustomBaseURL($message);
			}
			else
			{
				echo '<p class="error">Update failed.</p>';
				return;
			}
		}
		else
		{
			lShowCustomBaseURL();
		}
	}

	function _pluginDataUpgrade(&$oPluginAdmin)
	{
		global $member, $manager;
		
		if (!($member->isLoggedIn()))
		{
			// Do nothing if not logged in
			return;
		}

		$extrahead = "<style type='text/css'>
	<!--
		p.message { font-weight: bold; }
		p.error { font-size: 100%; font-weight: bold; color: #880000; }
		div.dialogbox { border: 1px solid #ddd; background-color: #F6F6F6; margin: 18px 0 1.5em 0; }
		div.dialogbox h4 { background-color: #bbc; color: #000; margin: 0; padding: 5px; }
		div.dialogbox h4.light { background-color: #ddd; }
		div.dialogbox div { margin: 0; padding: 10px; }
		div.dialogbox button { margin: 10px 0 0 6px; float: right; }
		div.dialogbox p { margin: 0; }
		div.dialogbox p.buttons { text-align: right; overflow: auto; }
	-->
	</style>";

		$pluginURL = $oPluginAdmin->plugin->getAdminURL();

		$sourcedataversion = $oPluginAdmin->plugin->getDataVersion();
		$commitdataversion = $oPluginAdmin->plugin->getCommitDataVersion();
		$currentdataversion = $oPluginAdmin->plugin->getCurrentDataVersion();
		
		$action = requestVar('action');

		$actions = array('upgradeplugindata', 'upgradeplugindata_process', 'rollbackplugindata', 'rollbackplugindata_process', 'commitplugindata', 'commitplugindata_process');

		if (in_array($action, $actions)) 
		{ 
			if (!$manager->checkTicket())
			{
				$oPluginAdmin->start($extrahead);
				echo '<h2>'.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' plugin data upgrade</h2>';
				echo '<p class="error">Error: Bad ticket</p>';
				$oPluginAdmin->end();
				exit;
			} 

			if (!($member->isAdmin()))
			{
				$oPluginAdmin->start($extrahead);
				echo '<h2>'.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' plugin data upgrade</h2>';
				echo '<p class="error">Only a super admin can execute plugin data upgrade actions.</p>';
				$oPluginAdmin->end();
				exit;
			}

			$gotoadminlink = false;
			
			$oPluginAdmin->start($extrahead);
			echo '<h2>'.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' plugin data upgrade</h2>';
			
			if($action == 'upgradeplugindata')
			{
				$canrollback = $oPluginAdmin->plugin->upgradeDataTest($currentdataversion, $sourcedataversion);

				$historygo = intRequestVar('historygo');
				$historygo--;
		
				echo '<div class="dialogbox">';
				echo '<form method="post" action="'.$pluginURL.'">';
				$manager->addTicketHidden();
				echo '<input type="hidden" name="action" value="upgradeplugindata_process" />';
				echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
				echo '<h4 class="light">Upgrade plugin data</h4><div>';
				echo '<p>Taking a database backup is recommended before performing the upgrade. ';
	
				if($canrollback)
				{
					echo 'After the upgrade is done you can choose to commit the plugin data to the new version or rollback the plugin data to the previous version. ';
				}
				else
				{
					echo 'This upgrade of the plugin data is not reversible. ';
				}
				
				echo '</p><br /><p>Are you sure you want to upgrade the plugin data now?</p>';
				echo '<p class="buttons">';
				echo '<input type="hidden" name="sure" value="yes" /">';
				echo '<input type="submit" value="Perform Upgrade" />';
				echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
				echo '</p>';
				echo '</div></form></div>';
			}
			else if($action == 'upgradeplugindata_process')
			{
				$canrollback = $oPluginAdmin->plugin->upgradeDataTest($currentdataversion, $sourcedataversion);

				if (requestVar('sure') == 'yes' && $sourcedataversion > $currentdataversion)
				{
					if($oPluginAdmin->plugin->upgradeDataPerform($currentdataversion + 1, $sourcedataversion))
					{
						$oPluginAdmin->plugin->setCurrentDataVersion($sourcedataversion);
						
						if(!$canrollback)
						{
							$oPluginAdmin->plugin->upgradeDataCommit($currentdataversion + 1, $sourcedataversion);
							$oPluginAdmin->plugin->setCommitDataVersion($sourcedataversion);					
						}
						
						echo '<p class="message">Upgrade of plugin data was successful.</p>';
						$gotoadminlink = true;
					}
					else
					{
						echo '<p class="error">Upgrade of plugin data failed.</p>';
					}
				}
				else
				{
					echo '<p class="message">Upgrade of plugin data canceled.</p>';
					$gotoadminlink = true;
				}
			}
			else if($action == 'rollbackplugindata')
			{
				$historygo = intRequestVar('historygo');
				$historygo--;
				
				echo '<div class="dialogbox">';
				echo '<form method="post" action="'.$pluginURL.'">';
				$manager->addTicketHidden();
				echo '<input type="hidden" name="action" value="rollbackplugindata_process" />';
				echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
				echo '<h4 class="light">Rollback plugin data upgrade</h4><div>';
				echo '<p>You may loose any plugin data added after the plugin data upgrade was performed. ';
				echo 'After the rollback is performed must you replace the plugin files with the plugin files for the previous version. ';
				echo '</p><br /><p>Are you sure you want to rollback the plugin data upgrade now?</p>';
				echo '<p class="buttons">';
				echo '<input type="hidden" name="sure" value="yes" /">';
				echo '<input type="submit" value="Perform Rollback" />';
				echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
				echo '</p>';
				echo '</div></form></div>';
			}
			else if($action == 'rollbackplugindata_process')
			{
				if (requestVar('sure') == 'yes' && $currentdataversion > $commitdataversion)
				{
					if($oPluginAdmin->plugin->upgradeDataRollback($currentdataversion, $commitdataversion + 1))
					{
						$oPluginAdmin->plugin->setCurrentDataVersion($commitdataversion);
										
						echo '<p class="message">Rollback of the plugin data upgrade was successful. You must replace the plugin files with the plugin files for the previous version before you can continue.</p>';
					}
					else
					{
						echo '<p class="error">Rollback of the plugin data upgrade failed.</p>';
					}
				}
				else
				{
					echo '<p class="message">Rollback of plugin data canceled.</p>';
					$gotoadminlink = true;
				}
			}	
			else if($action == 'commitplugindata')
			{
				$historygo = intRequestVar('historygo');
				$historygo--;
				
				echo '<div class="dialogbox">';
				echo '<form method="post" action="'.$pluginURL.'">';
				$manager->addTicketHidden();
				echo '<input type="hidden" name="action" value="commitplugindata_process" />';
				echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
				echo '<h4 class="light">Commit plugin data upgrade</h4><div>';
				echo '<p>After the commit of the plugin data upgrade is performed can you not rollback the plugin data to the previous version.</p>';
				echo '</p><br /><p>Are you sure you want to commit the plugin data now?</p>';
				echo '<p class="buttons">';
				echo '<input type="hidden" name="sure" value="yes" /">';
				echo '<input type="submit" value="Perform Commit" />';
				echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
				echo '</p>';
				echo '</div></form></div>';
			}
			else if($action == 'commitplugindata_process')
			{
				if (requestVar('sure') == 'yes' && $currentdataversion > $commitdataversion)
				{
					if($oPluginAdmin->plugin->upgradeDataCommit($commitdataversion + 1, $currentdataversion))
					{
						$oPluginAdmin->plugin->setCommitDataVersion($currentdataversion);
										
						echo '<p class="message">Commit of the plugin data upgrade was successful.</p>';
						$gotoadminlink = true;
					}
					else
					{
						echo '<p class="error">Commit of the plugin data upgrade failed.</p>';
						return;
					}
				}
				else
				{
					echo '<p class="message">Commit of plugin data canceled.</p>';
					$gotoadminlink = true;
				}
			}	
	
			if($gotoadminlink)
			{
				echo '<p><a href="'.$pluginURL.'">Continue to '.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' admin page</a>';
			}
			
			$oPluginAdmin->end();
			exit;
		}
		else
		{
			if($currentdataversion > $sourcedataversion)
			{
				$oPluginAdmin->start($extrahead);
				echo '<h2>'.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' plugin data upgrade</h2>';
				echo '<p class="error">An old version of the plugin files are installed. Downgrade of the plugin data is not supported.</p>';
				$oPluginAdmin->end();
				exit;
			}
			else if($currentdataversion < $sourcedataversion)
			{
				// Upgrade
				if (!($member->isAdmin()))
				{
					$oPluginAdmin->start($extrahead);
					echo '<h2>'.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' plugin data upgrade</h2>';
					echo '<p class="error">The plugin data needs to be upgraded before the plugin can be used. Only a super admin can do this.</p>';
					$oPluginAdmin->end();
					exit;
				}
				
				$oPluginAdmin->start($extrahead);
				echo '<h2>'.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' plugin data upgrade</h2>';
				echo '<div class="dialogbox">';
				echo '<h4 class="light">Upgrade plugin data</h4><div>';
				echo '<form method="post" action="'.$pluginURL.'">';
				$manager->addTicketHidden();
				echo '<input type="hidden" name="action" value="upgradeplugindata" />';
				echo '<p>The plugin data need to be upgraded before the plugin can be used. ';
				echo 'This function will upgrade the plugin data to the latest version.</p>';
				echo '<p class="buttons"><input type="submit" value="Upgrade" />';
				echo '</p></form></div></div>';
				$oPluginAdmin->end();
				exit;
			}
			else
			{
				$skipupgradehandling = (strstr(serverVar('REQUEST_URI'), '?') || serverVar('QUERY_STRING') || strtoupper(serverVar('REQUEST_METHOD') ) == 'POST');
							
				if($commitdataversion < $currentdataversion && $member->isAdmin() && !$skipupgradehandling)
				{
					// Commit or Rollback
					$oPluginAdmin->start($extrahead);
					echo '<h2>'.htmlspecialchars($oPluginAdmin->plugin->getName(), ENT_QUOTES, _CHARSET).' plugin data upgrade</h2>';
					echo '<div class="dialogbox">';
					echo '<h4 class="light">Commit plugin data upgrade</h4><div>';
					echo '<form method="post" action="'.$pluginURL.'">';
					$manager->addTicketHidden();
					echo '<input type="hidden" name="action" value="commitplugindata" />';
					echo '<p>If you choose to continue using this version after you have tested this version of the plugin, ';
					echo 'you have to choose to commit the plugin data upgrade. This function will commit the plugin data ';
					echo 'to the latest version. After the plugin data is committed will you not be able to rollback the ';
					echo 'plugin data to the previous version.</p>';
					echo '<p class="buttons"><input type="submit" value="Commit" />';
					echo '</p></form></div></div>';
					
					echo '<div class="dialogbox">';
					echo '<h4 class="light">Rollback plugin data upgrade</h4><div>';
					echo '<form method="post" action="'.$pluginURL.'">';
					$manager->addTicketHidden();
					echo '<input type="hidden" name="action" value="rollbackplugindata" />';
					echo '<p>If you choose to go back to the previous version of the plugin after you have tested this ';
					echo 'version of the plugin, you have to choose to rollback the plugin data upgrade. This function ';
					echo 'will rollback the plugin data to the previous version. ';
					echo 'After the plugin data is rolled back you have to update the plugin files to the previous version of the plugin.</p>';
					echo '<p class="buttons"><input type="submit" value="Rollback" />';
					echo '</p></form></div></div>';

					echo '<div class="dialogbox">';
					echo '<h4 class="light">Skip plugin data commit/rollback</h4><div>';
					echo '<form method="post" action="'.$pluginURL.'">';
					$manager->addTicketHidden();
					echo '<input type="hidden" name="skipupgradehandling" value="1" />';
					echo '<p>You can choose to skip the commit/rollback for now and test the new version ';
					echo 'of the plugin with upgraded data.'; 
					echo 'You will be asked to commit or rollback the plugin data upgrade the next time ';
					echo 'you use the link to the plugin admin page.</p>';
					echo '<p class="buttons"><input type="submit" value="Skip" />';
					echo '</p></form></div></div>';

					$oPluginAdmin->end();
					exit;
				}
			}
		}
	}
?>