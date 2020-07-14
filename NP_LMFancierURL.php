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
	
	See lmfancierurl/help.html for plugin description, install, usage and change history.
*/

class NP_LMFancierURL extends NucleusPlugin
{
	var $blogPartTypeId;
	var $itemPartTypeId;
	var $archivesPartTypeId;
	var $archivePartTypeId;
	var $memberPartTypeId;
	var $categoryPartTypeId;
	var $specialPartTypeId;
	var $itemFreeformPartTypeId;
	var $aPreValueItem;
	var $aParsedURL;
	var $orgConfSelf;
	
	// name of plugin
	function getName()
	{
		return 'LMFancierURL';
	}

	// author of plugin
	function getAuthor()
	{
		return 'Leo (www.slightlysome.net)';
	}

	// an URL to the plugin website
	// can also be of the form mailto:foo@bar.com
	function getURL()
	{
		return 'http://www.slightlysome.net/nucleus-plugins/np_lmfancierurl';
	}

	// version of the plugin
	function getVersion()
	{
		return '3.0.1';
	}

	// a description to be shown on the installed plugins listing
	function getDescription()
	{
		return 
'The NP_LMFancierURL plugin provides the posibility to use search engine optimized URLs for a Nucleus CMS blog site. 
A URL friendly version of each blog name, member name, item title, category name and extra skin name is generated 
by the plugin. These URL friendly values are then used when the URL for blogs, members, categories, items and extra 
skin is parsed or generated. These values can be edited by the blog administrator or the site super administrator.';
	}

	function supportsFeature ($what)
	{
		switch ($what)
		{
			case 'SqlTablePrefix':
				return 1;
			case 'SqlApi':
				return 1;
			case 'HelpPage':
				return 1;
			default:
				return 0;
		}
	}

	function hasAdminArea()
	{
		return 1;
	}

	function getMinNucleusVersion()
	{
		return '350';
	}
	
	function getEventList() 
	{ 
		return array(
			// Blog events
			'PostAddBlog', 'PostDeleteBlog', 
			// Item events
			'AddItemFormExtras', 'EditItemFormExtras', 'PostAddItem', 'PostUpdateItem', 'PostDeleteItem', 'PreMoveItem', 'PostMoveItem',
			// Member events
			'PostRegister', 'PostDeleteMember', 
			// Category events
			'PostAddCategory', 'PostDeleteCategory', 'PostMoveCategory',
			// URL Events
			'ParseURL', 'GenerateURL',
			// Other events
			'PostPluginOptionsUpdate', 'AdminPrePageFoot', 'QuickMenu'
			); 
	}

	function getPluginDep() 
	{
		return array('NP_LMURLParts');
	}

	function getTableList()
	{	
		return 	array($this->_getTableCustomBaseURL());
	}

	function install()
	{
		$sourcedataversion = $this->getDataVersion();

		$this->upgradeDataPerform(1, $sourcedataversion);
		$this->setCurrentDataVersion($sourcedataversion);
		$this->upgradeDataCommit(1, $sourcedataversion);
		$this->setCommitDataVersion($sourcedataversion);					
	}
	
	function unInstall()
	{
		if ($this->getOption('del_uninstall') == 'yes')	
		{
			foreach ($this->getTableList() as $table) 
			{
				sql_query("DROP TABLE IF EXISTS ".$table);
			}
			
			$typeid = $this->_getBlogPartTypeId();
			if($typeid) $this->_getURLPartPlugin()->removeType($typeid);
			
			$typeid = $this->_getItemPartTypeId();
			if($typeid) $this->_getURLPartPlugin()->removeType($typeid);

			$typeid = $this->_getArchivesPartTypeId();
			if($typeid) $this->_getURLPartPlugin()->removeType($typeid);

			$typeid = $this->_getArchivePartTypeId();
			if($typeid) $this->_getURLPartPlugin()->removeType($typeid);

			$typeid = $this->_getMemberPartTypeId();
			if($typeid) $this->_getURLPartPlugin()->removeType($typeid);

			$typeid = $this->_getCategoryPartTypeId();
			if($typeid) $this->_getURLPartPlugin()->removeType($typeid);

			$typeid = $this->_getSpecialPartTypeId();
			if($typeid) $this->_getURLPartPlugin()->removeType($typeid);

			$typeid = $this->_getItemFreeformPartTypeId();
			if($typeid) $this->_getURLPartPlugin()->removeType($typeid);
		}
	}

	function init()
	{
		global $CONF;

		$this->aParsedURL = array();
		$this->orgConfSelf = $CONF['Self'];
	}

	function doSkinVar($skinType, $what = '')
	{
		global $itemid;
		
		if($skinType == 'item' && $what == 'canonicalitemlink')
		{
			echo createItemLink($itemid);
		}
	}

	////////////////////////////////////////////////////////////////////////
	// Blog Events
	function event_PostAddBlog(&$data)
	{
		$oBlog = $data['blog'];

		$blogid = $oBlog->getId();
		$blogname = $oBlog->getName();
		$skinid = $oBlog->getDefaultSkin();
		
		$res = $this->_addChangeBlogPart($blogid, $blogname);
		if ($res === false) { return false; }
		
		$res = $this->_initializeSpecialPartForBlog($skinid, $blogid);		
		if ($res === false) { return false; }

		$res = $this->_insertCustomBaseURL($blogid, '', 0, 0, 0);
		if ($res === false) { return false; }
	}
	
	function event_PostUpdateBlog(&$data)
	{
		$oBlog = $data['blog'];

		$blogid = $oBlog->getId();
		$blogname = $oBlog->getName();
		$skinid = $oBlog->getDefaultSkin();

		$res = $this->_addChangeBlogPart($blogid, $blogname);
		if ($res === false) { return false; }

		$res = $this->_initializeSpecialPartForBlog($skinid, $blogid);		
		if ($res === false) { return false; }
	}

	function event_PostDeleteBlog(&$data)
	{
		$blogid = $data['blogid'];
		
		$this->_removeBlogPart($blogid);
		
		$res = $this->_deleteCustomBaseURL($blogid);
		if ($res === false) { return false; }
	}

	////////////////////////////////////////////////////////////////////////
	// Item Events
	function event_AddItemFormExtras(&$data)
	{
		$blogid = $data['blog']->getID();
		
		if($this->_allowFreeformItemInput($blogid, 'create'))
		{
			echo '<h3>'.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).'</h3>';
			echo '<label for="plug_'.strtolower($this->getName()).'_itemfreeform">Freeform item URL part:</label> <input name="plug_'.strtolower($this->getName()).'_itemfreeform" id="plug_'.strtolower($this->getName()).'_itemfreeform" size="100" maxlength="200" value="" />';
		}
	}
	
	function event_EditItemFormExtras(&$data)
	{
		$blogid = $data['blog']->getID();
		$itemid = $data['itemid'];

		if($this->_allowFreeformItemInput($blogid, 'update'))
		{
			$typeid = $this->_getItemFreeformPartTypeId();
			if($typeid === false) { return false; }

			$itemfreeform = $this->_getURLPartPlugin()->findURLPartByTypeIdRefIdBlogId($typeid, $itemid, $blogid);
			if ($itemfreeform === false) { return false; }
				
			echo '<h3>'.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).'</h3>';
			echo '<label for="plug_'.strtolower($this->getName()).'_itemfreeform">Freeform item URL part:</label> <input name="plug_'.strtolower($this->getName()).'_itemfreeform" id="plug_'.strtolower($this->getName()).'_itemfreeform" size="100" maxlength="200" value="'.stringToAttribute($itemfreeform).'" />';
		}
	}

	function event_PostAddItem(&$data)
	{
		$itemid = $data['itemid'];

		$aItemInfo = $this->_getItemByItemId($itemid);
		if ($aItemInfo === false) { return false; }
		
		foreach($aItemInfo as $aItem)
		{
			$itemid = $aItem['itemid'];
			$itemname = $aItem['itemname'];
			$timestamp = $aItem['timestamp'];
			$catid = $aItem['catid'];
			$blogid = $aItem['blogid'];
			$draft = $aItem['draft'];

			$res = $this->_addChangeItemPart($itemid, $itemname, $draft);
			if($res === false) { return false; }

			$this->_addChangeArchivePart($itemid, $timestamp, $draft);
			if($res === false) { return false; }
		}

		if($this->_allowFreeformItemInput($blogid, 'create'))
		{
			$itemfreeform = postVar('plug_'.strtolower($this->getName()).'_itemfreeform');
		}
		else
		{
			$itemfreeform = '';
		}

		if(!$itemfreeform)
		{
			if($this->_useFreeformItemTemplate($blogid, 'create'))
			{
				$itemfreeform = $this->_fillFreeformItemTemplate($blogid, $itemid, $catid, $itemname, $timestamp);
			}
		}

		if($itemfreeform)
		{
			$res = $this->_addChangeItemFreeformPart($itemid, $itemname, $itemfreeform);
			if($res === false) { return false; }
		}
	}
	
	function event_PostUpdateItem(&$data)
	{
		$itemid = $data['itemid'];

		$aItemInfo = $this->_getItemByItemId($itemid);
		if ($aItemInfo === false) { return false; }
		
		foreach($aItemInfo as $aItem)
		{
			$itemid = $aItem['itemid'];
			$itemname = $aItem['itemname'];
			$timestamp = $aItem['timestamp'];
			$catid = $aItem['catid'];
			$blogid = $aItem['blogid'];
			$draft = $aItem['draft'];

			$res = $this->_addChangeItemPart($itemid, $itemname, $draft);
			if($res === false) { return false; }

			$res = $this->_addChangeArchivePart($itemid, $timestamp, $draft);
			if($res === false) { return false; }
		}
		
		if($this->_allowFreeformItemInput($blogid, 'update'))
		{
			$itemfreeform = postVar('plug_'.strtolower($this->getName()).'_itemfreeform');
			$candelete = true;
		}
		else
		{
			$itemfreeform = '';
			$candelete = false;
		}

		if(!$itemfreeform)
		{
			if($this->_useFreeformItemTemplate($blogid, 'update'))
			{
				$itemfreeform = $this->_fillFreeformItemTemplate($blogid, $itemid, $catid, $itemname, $timestamp);
			}
		}

		if($itemfreeform)
		{
			$res = $this->_addChangeItemFreeformPart($itemid, $itemname, $itemfreeform);
			if($res === false) { return false; }
		}
		else if($candelete)
		{
			$res = $this->_removeItemFreeformPart($itemid);	
			if($res === false) { return false; }
		}
	}

	function event_PostDeleteItem(&$data)
	{
		$itemid = $data['itemid'];
		
		$res = $this->_removeItemPart($itemid);
		if($res === false) { return false; }

		$res = $this->_removeItemFreeformPart($itemid);
		if($res === false) { return false; }
	}
	
	function event_PreMoveItem(&$data)
	{
		$itemid = $data['itemid'];
		
		$aItem = $this->_getItemByItemId($itemid);
		if($aItem === false) { return false; }
		$aItem = $aItem['0'];

		$this->_setPreValueItem($aItem);		
	}

	function event_PostMoveItem(&$data)
	{
		$itemid = $data['itemid'];
		$destblogid = $data['destblogid'];

		$aPreItem = $this->_getPreValueItem();
		$fromblogid = $aPreItem['blogid'];

		if($fromblogid == $destblogid)
		{
			if(!$this->_allowFreeformItemInput($destblogid, 'update') && $this->_useFreeformItemTemplate($destblogid, 'update'))
			{
				$aItem = $this->_getItemByItemId($itemid);
				if ($aItem === false) { return false; }
				$aItem = $aItem['0'];

				$itemid = $aItem['itemid'];
				$itemname = $aItem['itemname'];
				$timestamp = $aItem['timestamp'];
				$catid = $aItem['catid'];

				$typeid = $this->_getItemFreeformPartTypeId();
				if($typeid === false) { return false; }

				$itemfreeform = $this->_getURLPartPlugin()->findURLPartByTypeIdRefIdBlogId($typeid, $itemid, $fromblogid);
				if ($itemfreeform === false) { return false; }
				
				if($itemfreeform)
				{
					$itemfreeform = $this->_fillFreeformItemTemplate($destblogid, $itemid, $catid, $itemname, $timestamp);

					$res = $this->_addChangeItemFreeformPart($itemid, $itemname, $itemfreeform);
					if($res === false) { return false; }
				}
			}
		}
		else
		{
			$res = $this->_removeItemPart($itemid);
			if($res === false) { return false; }

			$aItem = $this->_getItemByItemId($itemid);
			if ($aItem === false) { return false; }
			$aItem = $aItem['0'];

			$itemid = $aItem['itemid'];
			$itemname = $aItem['itemname'];
			$timestamp = $aItem['timestamp'];
			$catid = $aItem['catid'];
			$draft = $aItem['draft'];

			$res = $this->_addChangeItemPart($itemid, $itemname, $draft);
			if($res === false) { return false; }

			$res = $this->_addChangeArchivePart($itemid, $timestamp, $draft);
			if($res === false) { return false; }

			$typeid = $this->_getItemFreeformPartTypeId();
			if($typeid === false) { return false; }

			$itemfreeform = $this->_getURLPartPlugin()->findURLPartByTypeIdRefIdBlogId($typeid, $itemid, $fromblogid);
			if ($itemfreeform === false) { return false; }
			
			if($itemfreeform)
			{
				$res = $this->_removeItemFreeformPart($itemid);
				if($res === false) { return false; }
				
				if(!$this->_allowFreeformItemInput($destblogid, 'update') && $this->_useFreeformItemTemplate($destblogid, 'update'))
				{
					$itemfreeform = $this->_fillFreeformItemTemplate($destblogid, $itemid, $catid, $itemname, $timestamp);
				}
			
				$res = $this->_addChangeItemFreeformPart($itemid, $itemname, $itemfreeform);
				if($res === false) { return false; }
			}
		}
	}
	
	////////////////////////////////////////////////////////////////////////
	// Member Events
	function event_PostRegister(&$data)
	{
		$oMember = $data['member'];

		$memberid = $oMember->getId();
		$membername = $oMember->getDisplayName();
	
		$this->_addChangeMemberPart($memberid, $membername);
	}
	
	function event_PostUpdateMember(&$data)
	{
		$oMember = $data['member'];

		$memberid = $oMember->getId();
		$membername = $oMember->getDisplayName();
	
		$this->_addChangeMemberPart($memberid, $membername);
	}

	function event_PostDeleteMember(&$data)
	{
		$oMember = $data['member'];
		$memberid = $oMember->getId();
		
		$this->_removeMemberPart($memberid);
	}

	////////////////////////////////////////////////////////////////////////
	// Category Events
	function event_PostAddCategory(&$data)
	{
		$categoryid = $data['catid'];
		$categoryname = $data['name'];
	
		$this->_addChangeCategoryPart($categoryid, $categoryname);
	}
	
	function event_PostUpdateCategory(&$data)
	{
		$categoryid = $data['catid'];

		$aCategory = $this->_getCategoryByCategoryId($categoryid);
		if ($aCategory === false) { return false; }
		$aCategory = $aCategory['0'];
		
		$categoryname = $aCategory['categoryname'];
	
		$this->_addChangeCategoryPart($categoryid, $categoryname);
		
		$blogid = getBlogIDFromCatID($categoryid);
		
		if(!$this->_allowFreeformItemInput($toblogid, 'update') && $this->_useFreeformItemTemplate($toblogid, 'update'))
		{
			$typeid = $this->_getItemFreeformPartTypeId();
			if($typeid === false) { return false; }

			$aItemInfo = $this->_getItemByCategoryId($categoryid);
			if ($aItemInfo === false) { return false; }
			
			foreach($aItemInfo as $aItem)
			{
				$itemid = $aItem['itemid'];
				$itemname = $aItem['itemname'];
				$timestamp = $aItem['timestamp'];
				$catid = $aItem['catid'];

				$itemfreeform = $this->_getURLPartPlugin()->findURLPartByTypeIdRefIdBlogId($typeid, $itemid, $blogid);
				if ($itemfreeform === false) { return false; }
				
				if($itemfreeform)
				{
					$itemfreeform = $this->_fillFreeformItemTemplate($blogid, $itemid, $catid, $itemname, $timestamp);

					$res = $this->_addChangeItemFreeformPart($itemid, $itemname, $itemfreeform);
					if($res === false) { return false; }
				}
			}
		}
	}

	function event_PostDeleteCategory(&$data)
	{
		$categoryid = $data['catid'];

		$this->_removeCategoryPart($categoryid);
	}

	function event_PostMoveCategory(&$data)
	{
		$categoryid = $data['catid'];
		$oFromBlog = $data['sourceblog'];
		$oToBlog = $data['destblog'];

		$fromblogid = $oFromBlog->getId();
		$toblogid = $oToBlog->getId();
		
		if($fromblogid <> $toblogid)
		{
			$res = $this->_removeCategoryPart($categoryid);
			if($res === false) { return false; }

			$aCategory = $this->_getCategoryByCategoryId($categoryid);
			if ($aCategory === false) { return false; }
			$aCategory = $aCategory['0'];

			$categoryname = $aCategory['categoryname'];

			$res = $this->_addChangeCategoryPart($categoryid, $categoryname);
			if($res === false) { return false; }
			
			$aItemInfo = $this->_getItemByCategoryId($categoryid);
			if ($aItemInfo === false) { return false; }
			
			foreach($aItemInfo as $aItem)
			{
				$itemid = $aItem['itemid'];
				$itemname = $aItem['itemname'];
				$timestamp = $aItem['timestamp'];
				$catid = $aItem['catid'];
				$draft = $aItem['draft'];

				$res = $this->_removeItemPart($itemid);
				if($res === false) { return false; }

				$res = $this->_addChangeItemPart($itemid, $itemname, $draft);
				if($res === false) { return false; }

				$res = $this->_addChangeArchivePart($itemid, $timestamp, $draft);
				if($res === false) { return false; }

				$typeid = $this->_getItemFreeformPartTypeId();
				if($typeid === false) { return false; }

				$itemfreeform = $this->_getURLPartPlugin()->findURLPartByTypeIdRefIdBlogId($typeid, $itemid, $fromblogid);
				if ($itemfreeform === false) { return false; }
				
				if($itemfreeform)
				{
					$res = $this->_removeItemFreeformPart($itemid);
					if($res === false) { return false; }
					
					if(!$this->_allowFreeformItemInput($toblogid, 'update') && $this->_useFreeformItemTemplate($toblogid, 'update'))
					{
						$itemfreeform = $this->_fillFreeformItemTemplate($toblogid, $itemid, $catid, $itemname, $timestamp);
					}
				
					$res = $this->_addChangeItemFreeformPart($itemid, $itemname, $itemfreeform);
					if($res === false) { return false; }
				}
			}
		}
	}

	////////////////////////////////////////////////////////////////////////
	// URL events
	function event_ParseURL(&$data) 
	{
		global $itemid, $memberid, $catid, $blogid, $archivelist, $archive, $special, $CONF, $manager;		
		
		if ($data['complete']) { return; }
	
		$usedglobalurlscheme = '';
		$usebaseurl = '';
		$identifiesblog = 0;
		$defaultblogid = 0;
		$hidedefblog = $this->getOption('hidedefblog');

		$fullurl = 'http://'.serverVar('HTTP_HOST').serverVar('REQUEST_URI');
		
		$aCustomBaseURLInfo = $this->_getCustomBaseURLWithBlogNameFromURL($fullurl);
		
		if($aCustomBaseURLInfo)
		{
			foreach($aCustomBaseURLInfo as $aCustomBaseURL)
			{
				$tmp_blogid = $aCustomBaseURL['blogid'];
				$tmp_defaultblog = $aCustomBaseURL['defaultblog'];
				$tmp_identifiesblog = $aCustomBaseURL['identifiesblog'];
				$tmp_baseurl = $aCustomBaseURL['baseurl'];
				
				if($tmp_identifiesblog)
				{
					$identifiesblog = $tmp_identifiesblog;
					$usebaseurl = $tmp_baseurl;
					$blogid = $tmp_blogid;
					$usedglobalurlscheme = $this->getOption('globalurlscheme');

				}
				else
				{
					if($tmp_defaultblog || !$defaultblogid)
					{
						$defaultblogid = $tmp_blogid;
					}
					
					$usebaseurl = $tmp_baseurl;
				}
			}
			
			if($usebaseurl)
			{
				$CONF['Self'] = $usebaseurl;
				$CONF['ItemURL'] = $CONF['Self'];
				$CONF['ArchiveURL'] = $CONF['Self'];
				$CONF['ArchiveListURL'] = $CONF['Self'];
				$CONF['SearchURL'] = $CONF['Self'];
				$CONF['BlogURL'] = $CONF['Self'];
				$CONF['CategoryURL'] = $CONF['Self'];
			}
		}

		if(!$defaultblogid)
		{
			$defaultblogid = $CONF['DefaultBlog'];
		}
				
		if($data['info'])
		{
			$aParts = explode('/', trim($data['info']));
			
			$cnt = count($aParts);
			$n = 0;
			$first = true;
			$urlerror = false;
			$keyparamname = '';
			
			$showblogsinstalled = $manager->pluginInstalled('NP_ShowBlogs');
			$technoratitagsinstalled = $manager->pluginInstalled('NP_TechnoratiTags');
			
			$globalurlscheme = $this->getOption('globalurlscheme');
			$usedhidedefblog = 'no';
			$usedblogurlscheme = '';
			$bloglevelurl = false;
			$multipart = false;

			while ($n < $cnt && !$multipart)
			{
				$urlpart = $aParts[$n];
				
				if($urlpart)
				{
					if($technoratitagsinstalled && $urlpart == 'tags')
					{
						$n = $cnt;
						selectSkin('tags');
					}
					else if($showblogsinstalled && $urlpart == 'page')
					{
						$n++;
					}
					else
					{
						if($keyparamname && ((string) intval($urlpart) == (string) $urlpart))
						{
							if($blogid)
							{
								if($usedblogurlscheme)
								{
									if($usedblogurlscheme <> 'classic')
									{
										$urlerror = true;
									}
								}
								else 
								{
									$usedblogurlscheme = 'classic';
								}
							}
							else
							{
								$usedglobalurlscheme = 'classic';
							}

							${$keyparamname} = (int) $urlpart;

							$keyparamname = '';
						}
						else
						{
							$aURLPart = false;

							if($bloglevelurl == false && $blogid)
							{
								// Check for blog multipart url
							
								if($n > 0)
								{
									$urlmultipart = '/'.implode('/', array_slice($aParts, $n));
								}
								else
								{
									$urlmultipart = '/'.implode('/', $aParts);
								}
								
								$aURLPart = $this->_getURLPartPlugin()->findURLPartForParseURL($urlmultipart, $blogid);
								if($aURLPart === false) { return false; }
								
								if($aURLPart)
								{
									$multipart = true;
								}
							}
							
							if(!$aURLPart)
							{
								$aURLPart = $this->_getURLPartPlugin()->findURLPartForParseURL($urlpart, $blogid);
								if($aURLPart === false) { return false; }
							}
							
							if(!$aURLPart && $first && !$blogid)
							{
								$blogid = $defaultblogid;
								
								// Check for blog multipart url
								if($n > 0)
								{
									$urlmultipart = '/'.implode('/', array_slice($aParts, $n));
								}
								else
								{
									$urlmultipart = '/'.implode('/', $aParts);
								}
								
								$aURLPart = $this->_getURLPartPlugin()->findURLPartForParseURL($urlmultipart, $blogid);
								if($aURLPart === false) { return false; }
								
								if($aURLPart)
								{
									$multipart = true;
								}
								else
								{
									$aURLPart = $this->_getURLPartPlugin()->findURLPartForParseURL($urlpart, $blogid);
									if($aURLPart === false) { return false; }
								}

								$usedglobalurlscheme = $this->getOption('globalurlscheme');
								$usedhidedefblog = 'yes';
							}
						
							if(!$aURLPart && $first && $blogid && $identifiesblog)
							{
								$aURLPart = $this->_getURLPartPlugin()->findURLPartForParseURL($urlpart, 0);
								if($aURLPart === false) { return false; }
								
								if($aURLPart['0']['paramname'] == 'blogid')
								{
									$aURLPart = false;
								}
							}

							if($aURLPart)
							{
								$aURLPart = $aURLPart['0'];
								
								$paramname = $aURLPart['paramname'];
								$refid = $aURLPart['refid'];
								$urlpartname = $aURLPart['urlpartname'];
								$uniquecode = $aURLPart['uniquecode'];
								
								if($uniquecode == 'B')
								{
									$bloglevelurl = true;
								}
								
								if($paramname == 'blogid' && $identifiesblog && $first)
								{
									$urlerror = true;
								}
								
								if($paramname)
								{
									if($refid)
									{
										if($keyparamname)
										{
											if($blogid)
											{
												if($usedblogurlscheme)
												{
													if($usedblogurlscheme <> 'fancier')
													{
														$urlerror = true;
													}
												}
												else 
												{
													$usedblogurlscheme = 'fancier';
												}
											}
											else
											{
												$usedglobalurlscheme = 'fancier';
											}
										}

										${$paramname} = (int) $refid;
									}
									else
									{
										if($blogid)
										{
											$scheme = $this->_getBlogURLScheme($blogid);
											
											if($keyparamname == $paramname)
											{
												if($scheme == 'fancier' || $scheme == 'classic')
												{
													if($usedblogurlscheme)
													{
														if($scheme <> $usedblogurlscheme)
														{
															$urlerror = true;
														}
													}
													else
													{
														$usedblogurlscheme = $scheme;
													}
												}
												else
												{
													$urlerror = true;
												}
											}
										}
										
										${$paramname} = $urlpartname;
									}

									if(!($keyparamname == 'itemid' &&  $paramname == 'archive'))
									{
										$keyparamname = '';
									}

									if(isset($this->aParsedURL[$paramname]))
									{
										array_push($this->aParsedURL[$paramname],${$paramname});
									}
									else
									{
										$this->aParsedURL[$paramname] = array(${$paramname});
									}
								}
								else
								{
									if($urlpartname == 'Archives') 
									{
										$archivelist = $blogid;
										$this->aParsedURL['archivelist'] = array($blogid);

										$usedblogurlscheme = $this->_getBlogURLScheme($blogid);
										
										$keyparamname = '';
									}
									else
									{
										$aType = $this->_getURLPartPlugin()->findTypeByTypeName($urlpartname);
										if($aType === false) { return false; }
										
										if($aType)
										{
											$aType = $aType['0'];
											
											$keyparamname = $aType['paramname'];								
										}
									}
								}
							}
							else
							{
								$urlerror = true;
							}
						}
						$first = false;
					}
				}
				$n++;
			}
			
			if(!$usedglobalurlscheme)
			{
				$usedglobalurlscheme = 'compact';
			}

			if($usedglobalurlscheme <> $globalurlscheme)
			{
				$urlerror = true;
			}

			if($bloglevelurl && $blogid)
			{
				
				if(!$usedblogurlscheme)
				{
					$usedblogurlscheme = 'compact';
				}

				$blogurlscheme = $this->_getBlogURLScheme($blogid);

				if($usedblogurlscheme <> $blogurlscheme)
				{
					$urlerror = true;
				}
			}
			
			if($usedhidedefblog <> $hidedefblog && $blogid == $defaultblogid)
			{
				$urlerror = true;
			}
			
			if($blogid)
			{
				$baseurl = $this->orgConfSelf;
				
				$aCustomBaseURL = $this->_getCustomBaseURLWithBlogNameFromBlogID($blogid);
		
				if($aCustomBaseURL)
				{
					$aCustomBaseURL = $aCustomBaseURL['0'];

					$enablebaseurl = $aCustomBaseURL['enablebaseurl'];
					
					if($enablebaseurl)
					{
						$baseurl = $aCustomBaseURL['baseurl'];
					}
				}
						
				if($baseurl != substr($fullurl, 0, strlen($baseurl)))
				{
					$blogid = $defaultblogid;
					$urlerror = true;
				}				
			}
			
			if($urlerror)
			{
				header("HTTP/1.0 404 Not Found");
				doError('<h1>404 Not Found</h1><p>The requested URL '.serverVar('REQUEST_URI').' was not found on this server.</p>');
			}

			if($itemid && $catid)
			{
				$itemparsecat = $this->getBlogOption($blogid, 'blogitemparsecat');
				
				if($itemparsecat == 'global')
				{
					$itemparsecat = $this->getOption('globalitemparsecat');
				}
				
				if($itemparsecat == 'never')
				{
					$catid = 0;
				}
			}

			if($memberid && !$blogid)
			{
				$blogid = $defaultblogid;
			}
		}
		else
		{
			if(!$blogid)
			{
				if($hidedefblog != 'yes' && $this->getOption('redirectdefaultblog') == 'yes' && $fullurl == $CONF['Self'].'/')
				{
					$bloglink = createBlogidLink($defaultblogid);
					$location = 'Location: '.$bloglink;
					header($location, true, 302);
				}
				else
				{
					$blogid = $defaultblogid;
				}
			}
		}

		$data['complete'] = true;
	}
	
	function event_GenerateURL(&$data) 
	{
		global $CONF, $manager;

		$globalscheme = $this->getOption('globalurlscheme');
		$enablebaseurl = 0;
		$baseurl = '';
		$defaultblog = 0;
		$identifiesblog = 0;
		$usebaseurl = '';
		
		if($data['completed'])
		{		
			return;
		}

		$blogid = 0;
		
		$params = $data['params'];

		if(isset($params['extra'])) 
		{
			$allparam = $params['extra'];

			if(isset($allparam['archivelist']))
			{
				$allparam['archivelist'] = "";
			}
		} 
		else 
		{
			$allparam = array();
		}
		
		$data['completed'] = true;
		
		switch ($data['type'])
		{
			case 'item':
				$itemid = $params['itemid'];
				$allparam['itemid'] = $itemid;
				$blogid = getBlogIDFromItemID($itemid);
				$allparam['blogid'] = $blogid;
				break;
				
			case 'member':
				$memberid = $params['memberid'];
				$allparam['memberid'] = $memberid;
				unset($allparam['catid']);
				
				$memberblogid = $GLOBALS['blogid'];

				$aCustomBaseURL = $this->_getCustomBaseURLWithBlogNameFromBlogID($memberblogid);
				
				if($aCustomBaseURL)
				{
					$aCustomBaseURL = $aCustomBaseURL['0'];

					if($aCustomBaseURL['enablebaseurl'])
					{
						$enablebaseurl = $aCustomBaseURL['enablebaseurl'];
						$baseurl = $aCustomBaseURL['baseurl'];
						$defaultblog = $aCustomBaseURL['defaultblog'];
						$identifiesblog = $aCustomBaseURL['identifiesblog'];
					}
				}
					
				if($enablebaseurl)
				{
					$usebaseurl = $baseurl;
				}
				
				if($memberblogid && !$identifiesblog && $this->getBlogOption($memberblogid, 'memberlinkblogid') == 'yes')
				{
					$allparam['blogid'] = $memberblogid;
				}
				break;
			
			case 'category':
				$catid = $params['catid'];
				$allparam['catid'] = $catid;
				$blogid = getBlogIDFromCatID($catid);
				$allparam['blogid'] = $blogid;
				break;

			case 'blog':
				$blogid = $params['blogid'];
				$allparam['blogid'] = $blogid;
				break;
				
			case 'archivelist':
				$blogid = $params['blogid'];
				$allparam['blogid'] = $blogid;
				$allparam['archivelist'] = "";
				break;
				
			case 'archive':
				$blogid = $params['blogid'];
				$allparam['blogid'] = $blogid;
				$allparam['archive'] = $params['archive'];
				break;

			default:
				$data['completed'] = false;
				break;
		}

		if ($data['completed'] == true) 
		{
			$itemprefix = '';
			$itemfreeform = '';
			
			$url = array();

			if($blogid)
			{
				$blogscheme = $this->getBlogOption($blogid, 'blogurlscheme');

				$aCustomBaseURL = $this->_getCustomBaseURLWithBlogNameFromBlogID($blogid);
				
				if($aCustomBaseURL)
				{
					$aCustomBaseURL = $aCustomBaseURL['0'];

					if($aCustomBaseURL['enablebaseurl'])
					{
						$enablebaseurl = $aCustomBaseURL['enablebaseurl'];
						$baseurl = $aCustomBaseURL['baseurl'];
						$defaultblog = $aCustomBaseURL['defaultblog'];
						$identifiesblog = $aCustomBaseURL['identifiesblog'];
					}
				}
					
				if($enablebaseurl)
				{
					$usebaseurl = $baseurl;
				}
			}
			
			if(!$blogid || $blogscheme == "global")
			{
				$blogscheme = $globalscheme;
			}

			if(isset($allparam['itemid']))
			{
				$typeid = $this->_getItemFreeformPartTypeId();
				if($typeid === false) { return false; }

				$itemfreeform = $this->_getURLPartPlugin()->findURLPartByTypeIdRefIdBlogId($typeid, $allparam['itemid'], $allparam['blogid']);
				if($itemfreeform === false) { return false; }
			}
			
			if(isset($allparam['itemid']) && $blogscheme <> "classic" && !$itemfreeform)
			{
				$itemurldate = $this->getBlogOption($blogid, 'blogitemurldate');
				
				if($itemurldate == 'global')
				{
					$itemurldate = $this->getOption('globalitemurldate');
				}
				
				if($itemurldate <> 'none')
				{
					$aItem = $this->_getItemByItemId($allparam['itemid']);
					if($aItem === false) { return false; }
					
					if($aItem)
					{
						$aItem = $aItem['0'];
						$timestamp = $aItem['timestamp'];
						
						$aVal = $this->_genArchiveNameAndId($timestamp);
						
						if($itemurldate == 'yyyymmdd')
						{
							$urlpartname = $aVal['archivedayname'];
						}
						elseif($itemurldate == 'yyyymm')
						{
							$urlpartname = $aVal['archivemonname'];
						}
						else
						{
							$urlpartname = '';
						}
						
						if($urlpartname)
						{
							$typeid = $this->_getArchivePartTypeId();
							if($typeid === false) { return false; }
							
							$itemprefix = $this->_getURLPartPlugin()->findURLPartByTypeIdURLPartNameBlogId($typeid, $urlpartname, $blogid);
							if ($itemprefix === false) { return false; }
						}
					}
				}
			}

			if(isset($allparam['itemid']) && !$itemfreeform)
			{
				$itemurlurlcat = $this->getBlogOption($blogid, 'blogitemurlcat');
				
				if($itemurlurlcat == 'global')
				{
					$itemurlurlcat = $this->getOption('globalitemurlcat');
				}
			
				if($itemurlurlcat <> 'systemdecide')
				{
					if($itemurlurlcat == 'always')
					{
						if(!isset($allparam['catid']))
						{
							$aItem = $this->_getItemByItemId($allparam['itemid']);
							if($aItem === false) { return false; }

							if($aItem)
							{
								$aItem = $aItem['0'];
								$allparam['catid'] = $aItem['catid'];
							}
						}
					}
					elseif($itemurlurlcat == 'never')
					{
						if(isset($allparam['catid']))
						{
							unset($allparam['catid']); 
						}
					}
					
				
				}
			}

			$eventdata = array(
					'type' => $data['type'],
					'params' => &$allparam
				);
			$manager->notify('LMFancierURL_GenerateURLParams', $eventdata);
						
			foreach ($allparam as $param => $values) 
			{
				if(!is_array($values))
				{
					$values = array($values);
				}

				foreach ($values as $value) 
				{
					if	(
							!($param == 'blogid' && $value == $CONF['DefaultBlog'] && $this->getOption('hidedefblog')=='yes') 
							&& ((!$itemfreeform) || ($itemfreeform && in_array($param, array('itemid', 'blogid'))))
							&& !($param == 'blogid' && $identifiesblog && $usebaseurl)
						)
					{
						$aType = $this->_getURLPartPlugin()->findTypeByParamName($param);
						if($aType === false) { return false; }

						$urlpart = '';
						$keyword = '';
						
						if($aType)
						{
							$aType = $aType['0'];
							
							$typeid = $aType['typeid'];
							$typeorder = $aType['typeorder'];
							$uniquecode = $aType['uniquecode'];

							if($uniquecode == 'L')
							{
								$urlscheme = $globalscheme;
							}
							else
							{
								$urlscheme = $blogscheme;
							}
							
							if((string) intval($value) == (string) $value)
							{
								$refid = $value;
								$urlpartname = "";
							}
							else
							{
								$refid = 0;
								$urlpartname = $value;
							}

							$keyword = $this->_getURLPartPlugin()->findKeyWordForTypeId($typeid, $blogid);
							if ($keyword === false) { return false; }

							if($refid)
							{
								if($urlscheme <> 'classic')
								{
									$urlpart = $this->_getURLPartPlugin()->findURLPartByTypeIdRefIdBlogId($typeid, $refid, $blogid);
									if ($urlpart === false) { return false; }
								}

								if(!$urlpart)
								{
									$urlpart = (string) $refid;
								}
							}
							elseif($urlpartname)
							{
								if($urlscheme <> 'classic' || $typeid == $this->_getArchivePartTypeId())
								{
									$urlpart = $this->_getURLPartPlugin()->findURLPartByTypeIdURLPartNameBlogId($typeid, $urlpartname, $blogid);
									if ($urlpart === false) { return false; }

									if(!$urlpart)
									{
										// Check if it's an archive part thats missing and add it
										if($typeid == $this->_getArchivePartTypeId())
										{
											$urlpartid = $this->_getURLPartPlugin()->addChangeURLPart($urlpartname, $typeid, 0, $blogid);
											if ($urlpartid === false) { return false; }
											
											$urlpart = $this->_getURLPartPlugin()->findURLPartByTypeIdURLPartNameBlogId($typeid, $urlpartname, $blogid);
											if ($urlpart === false) { return false; }
										}
									}
								}
								
								if(!$urlpart)
								{
									$urlpart = (string) $urlpartname;
								}
							}
							
							if($urlscheme == 'compact' && $urlpart)
							{
								$keyword = '';
							}
						}
						else
						{
							$keyword = $param;
							if($value)
							{
								$urlpart = (string) $value;
							}
						}

						if($typeid == $this->_getItemPartTypeId() AND $itemfreeform)
						{
							$parts = explode('/', $itemfreeform);
							
							foreach($parts as $pos => $part)
							{
								$parts[$pos] = urlencode($part);
							}
							$urlpart = implode("/", $parts);
							
							if(substr($urlpart, 0, 1) != '/')
							{
								$urlpart = '/'.$urlpart;
							}
							
							$url[$typeorder] = $urlpart;
						}
						else
						{
							if($keyword)
							{
								if(!isset($url[$typeorder]))
								{
									$url[$typeorder] = '';
								}

								$url[$typeorder] .= '/'.urlencode($keyword);
							}
							
							if($urlpart)
							{
								if(!isset($url[$typeorder]))
								{
									$url[$typeorder] = '';
								}

								if($itemprefix  && $typeid == $this->_getItemPartTypeId())
								{
									$url[$typeorder] .= '/'.urlencode($itemprefix);
								}
								
								$url[$typeorder] .= '/'.$urlpart;
							}
						}
					}
				}
			}
		
			ksort($url);

			if($enablebaseurl)
			{
				if(!$usebaseurl)
				{
					$usebaseurl = substr($CONF['IndexURL'], 0, -1);
				}
				$data['url'] = $usebaseurl.implode($url);
			}
			else
			{
				$data['url'] = $this->orgConfSelf.implode($url);
			}
		}
	}
	
	////////////////////////////////////////////////////////////////////////
	// Other Events
	function event_PostPluginOptionsUpdate(&$data)
	{
		switch ($data['context'])
		{
			// Workaround for missing event: PostUpdateBlog
			case 'blog':
				$this->event_PostUpdateBlog($data);
				break;
			// Workaround for missing event: PostUpdateMember
			case 'member':
				$this->event_PostUpdateMember($data);
				break;
			// Workaround for missing event: PostUpdateCategory
			case 'category':
				$this->event_PostUpdateCategory($data);
				break;
		}
	}
	
	function event_AdminPrePageFoot(&$data)
	{
		// Workaround for missing event: AdminPluginNotification
		$data['notifications'] = array();
			
		$this->event_AdminPluginNotification($data);
			
		foreach($data['notifications'] as $aNotification)
		{
			echo '<h2>Notification from plugin: '.htmlspecialchars($aNotification['plugin'], ENT_QUOTES, _CHARSET).'</h2>';
			echo $aNotification['text'];
		}
	}
	
	function event_AdminPluginNotification(&$data)
	{
		global $member;
		
		$actions = array('overview', 'pluginlist', 'plugin_LMFancierURL');
		$text = "";
		
		if(in_array($data['action'], $actions))
		{
			if(!$this->_checkURLPartsSourceVersion())
			{
				$text .= '<p><b>The installed version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin needs version '.$this->_needURLPartsSourceVersion().' or later of the LMURLParts plugin to function properly.</b> The latest version of the LMURLParts plugin can be downloaded from the LMURLParts <a href="http://www.slightlysome.net/nucleus-plugin/np_lmurlparts">plugin page</a>.</p>';
			}
			elseif(!$this->_checkURLPartsDataVersion())
			{
				$text .= '<p><b>The LMURLParts plugin data needs to be upgraded before the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin can function properly.</b></p>';
			}

			$sourcedataversion = $this->getDataVersion();
			$commitdataversion = $this->getCommitDataVersion();
			$currentdataversion = $this->getCurrentDataVersion();
		
			if($currentdataversion > $sourcedataversion)
			{
				$text .= '<p>An old version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin files are installed. Downgrade of the plugin data is not supported. The correct version of the plugin files must be installed for the plugin to work properly.</p>';
			}
			
			if($currentdataversion < $sourcedataversion)
			{
				$text .= '<p>The version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin data is for an older version of the plugin than the version installed. ';
				$text .= 'The plugin data needs to be upgraded or the source files needs to be replaced with the source files for the old version before the plugin can be used. ';

				if($member->isAdmin())
				{
					$text .= 'Plugin data upgrade can be done on the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' <a href="'.$this->getAdminURL().'">admin page</a>.';
				}
				
				$text .= '</p>';
			}
			
			if($commitdataversion < $currentdataversion && $member->isAdmin())
			{
				$text .= '<p>The version of the '.$this->getName().' plugin data is upgraded, but the upgrade needs to commited or rolled back to finish the upgrade process. ';
				$text .= 'Plugin data upgrade commit and rollback can be done on the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' <a href="'.$this->getAdminURL().'">admin page</a>.</p>';
			}
			
			if($this->_checkURLPartsSourceVersion())
			{
				$res = $this->_checkSpecialPartForAllBlogs();
				
				if($res === false)
				{
					$text .= '<p>Function _checkSpecialPartForAllBlogs failed.</p>';
				}
				else if(!$res)
				{
					$text .= '<p>The '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin has detected a difference between special skin parts configured in Nucleus and the special skin parts registered in the LMURLParts plugin. ';
					$text .= 'The special skin part URL parts registered in the LMURLParts plugin can be updated on the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' <a href="'.$this->getAdminURL().'">admin page</a>.</p>';
				}
			}
		}
		
		if($text)
		{
			array_push(
				$data['notifications'],
				array(
					'plugin' => $this->getName(),
					'text' => $text
				)
			);
		}
	}

	function event_QuickMenu(&$data) 
	{
		global $member;

		if ($member->isAdmin()) 
		{
			array_push($data['options'],
				array('title' => 'LMFancierURL',
					'url' => $this->getAdminURL(),
					'tooltip' => 'Administer NP_LMFancierURL'));
		}
	}

	////////////////////////////////////////////////////////////////////////
	// Public functions
	function getURLValue($type)
	// Returns the values used in the URL for a certain type of paramter
	{
		if(isset($this->aParsedURL[$type]))
		{
			$aURLValue = $this->aParsedURL[$type];
		}
		else
		{
			$aURLValue = array();
		}
		
		return $aURLValue;
	}
	
	////////////////////////////////////////////////////////////////////////
	// Internal functions
	function &_getURLPartPlugin()
	{
		global $manager;
		
		$oURLPartPlugin =& $manager->getPlugin('NP_LMURLParts');

		if(!$oURLPartPlugin)
		{
			// Panic
			echo '<p>Couldn\'t get plugin NP_LMURLParts. This plugin must be installed for the NP_LMFancierURL plugin to work.</p>';
			return false;
		}
		
		return $oURLPartPlugin;
	}

	function _genArchiveNameAndId($timestamp)
	{
			$aDateTime = getdate($timestamp);
			
			$archivemonname = $aDateTime['year'].'-'.substr('0'.$aDateTime['mon'], -2);
			$archivedayname = $archivemonname.'-'.substr('0'.$aDateTime['mday'], -2);

			$archivemonid = intVal($aDateTime['year'].substr('0'.$aDateTime['mon'], -2));
			$archivedayid = intVal($aDateTime['year'].substr('0'.$aDateTime['mon'], -2).substr('0'.$aDateTime['mday'], -2));
			
		return array('archivemonname' => $archivemonname, 'archivedayname' => $archivedayname, 'archivemonid'=> $archivemonid, 'archivedayid' => $archivedayid);
	}

	function _setPreValueItem($aPreValueItem)
	{
		$this->aPreValueItem = $aPreValueItem;
	}
			
	function _getPreValueItem()
	{
		return $this->aPreValueItem;
	}

	function _getBlogURLScheme($blogid)
	{
		if($blogid)
		{
			$blogurlscheme = $this->getBlogOption($blogid, 'blogurlscheme');
											
			if($blogurlscheme == 'global')
			{
				$blogurlscheme = $this->getOption('globalurlscheme');
			}
		}
		else
		{
			$blogurlscheme = false;
		}
		
		return $blogurlscheme;
	}

	function _needURLPartsSourceVersion()
	{
		return '1.1.1';
	}
	
	function _checkURLPartsSourceVersion()
	{
		$urlPartsVersion = $this->_needURLPartsSourceVersion();
		$aVersion = explode('.', $urlPartsVersion);
		$needmajor = $aVersion['0']; $needminor = $aVersion['1']; $needpatch = $aVersion['2'];
		
		$urlPartsVersion = $this->_getURLPartPlugin()->getVersion();
		$aVersion = explode('.', $urlPartsVersion);
		$major = $aVersion['0']; $minor = $aVersion['1']; $patch = $aVersion['2'];
		
		if($major < $needmajor || (($major == $needmajor) && ($minor < $needminor)) || (($major == $needmajor) && ($minor == $needminor) && ($patch < $needpatch)))
		{
			return false;
		}

		return true;
	}

	function _checkURLPartsDataVersion()
	{
		if(!method_exists($this->_getURLPartPlugin(), 'getDataVersion'))
		{
			return false;
		}
		
		$current = $this->_getURLPartPlugin()->getCurrentDataVersion();
		$source = $this->_getURLPartPlugin()->getDataVersion();
		
		if($current < $source)
		{
			return false;
		}

		return true;
	}

	////////////////////////////////////////////////////////////////////////
	// Internal functions: Blog urlparts
	function _getBlogPartTypeId()
	{
		if(!$this->blogPartTypeId)
		{
			$this->blogPartTypeId = $this->_getURLPartPlugin()->findTypeId("Blog", $this->getName());
			
			if($this->blogPartTypeId === false || $this->blogPartTypeId == 0) { return false; }
		}

		return $this->blogPartTypeId;
	}
	
	function _initializeBlogPart()
	{
		global $CONF;
		
		$typeid = $this->_getBlogPartTypeId();
		if(! $typeid)
		{
			$typeid = $this->blogPartTypeId = $this->_getURLPartPlugin()->addType("Blog", $this->getName(), 'L', 'blogid', 1, $CONF['BlogKey']);
			if($typeid === false) { return false; }
		}
		
		$aBlogInfo = $this->_getBlogAll();
		if ($aBlogInfo === false) { return false; }
		
		$res = $this->_getURLPartPlugin()->urlPartMaintStart($typeid);
		if ($res === false) { return false; }
		
		foreach($aBlogInfo as $aBlog)
		{
			$blogid = $aBlog['blogid'];
			$blogname = $aBlog['blogname'];
			$skinid = $aBlog['skinid'];
		
			$res = $this->_getURLPartPlugin()->addChangeURLPart($blogname, $typeid, $blogid, 0);
			if ($res === false) { return false; }
			
			$res = $this->_initializeSpecialPartForBlog($skinid, $blogid);
			if ($res === false) { return false; }
		}
		
		$res = $this->_getURLPartPlugin()->urlPartMaintDone($typeid);
		if ($res === false) { return false; }

		return true;
	}
	
	function _addChangeBlogPart($blogid, $blogname)
	{
		$typeid = $this->_getBlogPartTypeId();
		if($typeid === false) { return false; }

		return $this->_getURLPartPlugin()->addChangeURLPart($blogname, $typeid, $blogid, 0);
	}
	
	function _removeBlogPart($blogid)
	{
		$typeid = $this->_getBlogPartTypeId();
		if($typeid === false) { return false; }

		$res = $this->_getURLPartPlugin()->removeURLPartForBlogId($blogid);
		if($res === false) { return false; }
		
		return $this->_getURLPartPlugin()->removeURLPart("", $typeid, $blogid, 0);
	}	

	////////////////////////////////////////////////////////////////////////
	// Internal functions: Item urlparts
	function _getItemPartTypeId()
	{
		if(!$this->itemPartTypeId)
		{
			$this->itemPartTypeId = $this->_getURLPartPlugin()->findTypeId("Item", $this->getName());
			
			if($this->itemPartTypeId === false || $this->itemPartTypeId == 0) { return false; }
		}

		return $this->itemPartTypeId;
	}
	
	function _initializeItemPart()
	{
		global $CONF;

		$typeid = $this->_getItemPartTypeId();
		if(! $typeid)
		{
			$typeid = $this->itemPartTypeId = $this->_getURLPartPlugin()->addType("Item", $this->getName(), 'B', 'itemid', 100, $CONF['ItemKey']);
			if($typeid === false) { return false; }
		}

		$aItemInfo = $this->_getItemAll();
		if ($aItemInfo === false) { return false; }
		
		$res = $this->_getURLPartPlugin()->urlPartMaintStart($typeid);
		if ($res === false) { return false; }

		foreach($aItemInfo as $aItem)
		{
			$itemid = $aItem['itemid'];
			$blogid = $aItem['blogid'];
			$itemname = $aItem['itemname'];
		
			$res = $this->_getURLPartPlugin()->addChangeURLPart($itemname, $typeid, $itemid, $blogid);
		}

		$res = $this->_getURLPartPlugin()->urlPartMaintDone($typeid);
		if ($res === false) { return false; }
		
		return true;
	}
	
	function _addChangeItemPart($itemid, $itemname, $draft)
	{
		$typeid = $this->_getItemPartTypeId();
		if($typeid === false) { return false; }

		$blogid = getBlogIDFromItemID($itemid);
		if(! $blogid) { return false; }
		
		if($draft)
		{
			$itemname = 'draft-'.$itemname;
		}
		
		return $this->_getURLPartPlugin()->addChangeURLPart($itemname, $typeid, $itemid, $blogid);
	}
	
	function _removeItemPart($itemid)
	{
		$typeid = $this->_getItemPartTypeId();
		if($typeid === false) { return false; }

		return $this->_getURLPartPlugin()->removeURLPart("", $typeid, $itemid, 0);
	}	

	////////////////////////////////////////////////////////////////////////
	// Internal functions: Archives urlparts
	function _getArchivesPartTypeId()
	{
		if(!$this->archivesPartTypeId)
		{
			$this->archivesPartTypeId = $this->_getURLPartPlugin()->findTypeId("Archives", $this->getName());
			
			if($this->archivesPartTypeId === false || $this->archivesPartTypeId == 0) { return false; }
		}

		return $this->archivesPartTypeId;
	}

	function _initializeArchivesPart()
	{
		global $CONF;

		$typeid = $this->_getArchivesPartTypeId();
		if(!$typeid)
		{
			$typeid = $this->archivesPartTypeId = $this->_getURLPartPlugin()->addType("Archives", $this->getName(), 'B', 'archivelist', 50, $CONF['ArchivesKey']);
			if($typeid === false) { return false; }
		}

		return true;
	}

	////////////////////////////////////////////////////////////////////////
	// Internal functions: Archive urlparts
	function _getArchivePartTypeId()
	{
		if(!$this->archivePartTypeId)
		{
			$this->archivePartTypeId = $this->_getURLPartPlugin()->findTypeId("Archive", $this->getName());
			
			if($this->archivePartTypeId === false || $this->archivePartTypeId == 0) { return false; }
		}

		return $this->archivePartTypeId;
	}

	function _initializeArchivePart()
	{
		global $CONF;

		$typeid = $this->_getArchivePartTypeId();
		if(!$typeid)
		{
			$typeid = $this->archivePartTypeId = $this->_getURLPartPlugin()->addType("Archive", $this->getName(), 'B', 'archive', 50, $CONF['ArchiveKey']);
			if($typeid === false) { return false; }
		}

		$aItemInfo = $this->_getItemAll();
		if ($aItemInfo === false) { return false; }

		$res = $this->_getURLPartPlugin()->urlPartMaintStart($typeid);
		if ($res === false) { return false; }

		foreach($aItemInfo as $aItem)
		{
			$timestamp = $aItem['timestamp'];
			$blogid = $aItem['blogid'];
			$draft = $aItem['draft'];
		
			if(! $draft)
			{
				$aVal = $this->_genArchiveNameAndId($timestamp);
				
				$res = $this->_getURLPartPlugin()->addChangeURLPart($aVal['archivemonname'], $typeid, 0 /*$aVal['archivemonid']*/, $blogid);
				$res = $this->_getURLPartPlugin()->addChangeURLPart($aVal['archivedayname'], $typeid, 0 /*$aVal['archivedayid']*/, $blogid);
			}
		}

		$res = $this->_getURLPartPlugin()->urlPartMaintDone($typeid);
		if ($res === false) { return false; }
		
		return true;
	}
	
	function _addChangeArchivePart($itemid, $timestamp, $draft)
	{
		if($draft == 0)
		{
			$typeid = $this->_getArchivePartTypeId();
			if($typeid === false) { return false; }
			
			$blogid = getBlogIDFromItemID($itemid);
			if(! $blogid) { return false; }
			
			$aVal = $this->_genArchiveNameAndId($timestamp);
			
			$res = $this->_getURLPartPlugin()->addChangeURLPart($aVal['archivemonname'], $typeid, 0 /*$aVal['archivemonid']*/, $blogid);
			if($res === false) { return false; };

			$res = $this->_getURLPartPlugin()->addChangeURLPart($aVal['archivedayname'], $typeid, 0 /*$aVal['archivedayid']*/, $blogid);
			if($res === false) { return false; };
		}
		return true;
	}
	
	function _removeArchivePart($itemid, $timestamp, $blogid)
	{
		$typeid = $this->_getArchivePartTypeId();
		if($typeid === false) { return false; }

		$aVal = $this->_genArchiveNameAndId($timestamp);

		$res = $this->_getURLPartPlugin()->removeURLPart($aVal['archivemonname'], $typeid, 0 /*$aVal['archivemonid']*/, $blogid);
		if($res === false) { return false; };
		
		$res = $this->_getURLPartPlugin()->removeURLPart($aVal['archivedayname'], $typeid, 0 /*$aVal['archivedayid']*/, $blogid);
		if($res === false) { return false; };

		return true;
	}	
	
	
	////////////////////////////////////////////////////////////////////////
	// Internal functions: Member urlparts
	function _getMemberPartTypeId()
	{
		if(!$this->memberPartTypeId)
		{
			$this->memberPartTypeId = $this->_getURLPartPlugin()->findTypeId("Member", $this->getName());
			
			if($this->memberPartTypeId === false || $this->memberPartTypeId == 0) { return false; }
		}

		return $this->memberPartTypeId;
	}
	
	function _initializeMemberPart()
	{
		global $CONF;

		$typeid = $this->_getMemberPartTypeId();
		if(! $typeid)
		{
			$typeid = $this->memberPartTypeId = $this->_getURLPartPlugin()->addType("Member", $this->getName(), 'L', 'memberid', 1, $CONF['MemberKey']);
			if($typeid === false) { return false; }
		}

		$aMemberInfo = $this->_getMemberAll();
		if ($aMemberInfo === false) { return false; }
		
		$res = $this->_getURLPartPlugin()->urlPartMaintStart($typeid);
		if ($res === false) { return false; }

		foreach($aMemberInfo as $aMember)
		{
			$memberid = $aMember['memberid'];
			$membername = $aMember['membername'];
		
			$res = $this->_getURLPartPlugin()->addChangeURLPart($membername, $typeid, $memberid, 0);
		}

		$res = $this->_getURLPartPlugin()->urlPartMaintDone($typeid);
		if ($res === false) { return false; }

		return true;
	}
	
	function _addChangeMemberPart($memberid, $membername)
	{
		$typeid = $this->_getMemberPartTypeId();
		if($typeid === false) { return false; }

		return $this->_getURLPartPlugin()->addChangeURLPart($membername, $typeid, $memberid, 0);
	}
	
	function _removeMemberPart($memberid)
	{
		$typeid = $this->_getMemberPartTypeId();
		if($typeid === false) { return false; }

		return $this->_getURLPartPlugin()->removeURLPart("", $typeid, $memberid, 0);
	}	

	////////////////////////////////////////////////////////////////////////
	// Internal functions: Category urlparts
	function _getCategoryPartTypeId()
	{
		if(!$this->categoryPartTypeId)
		{
			$this->categoryPartTypeId = $this->_getURLPartPlugin()->findTypeId("Category", $this->getName());
			
			if($this->categoryPartTypeId === false || $this->categoryPartTypeId == 0) { return false; }
		}

		return $this->categoryPartTypeId;
	}
	
	function _initializeCategoryPart()
	{
		global $CONF;

		$typeid = $this->_getCategoryPartTypeId();
		if(! $typeid)
		{
			$typeid = $this->categoryPartTypeId = $this->_getURLPartPlugin()->addType("Category", $this->getName(), 'B', 'catid', 40, $CONF['CategoryKey']);
			if($typeid === false) { return false; }
		}

		$aCategoryInfo = $this->_getCategoryAll();
		if ($aCategoryInfo === false) { return false; }
		
		$res = $this->_getURLPartPlugin()->urlPartMaintStart($typeid);
		if ($res === false) { return false; }

		foreach($aCategoryInfo as $aCategory)
		{
			$categoryid = $aCategory['categoryid'];
			$categoryname = $aCategory['categoryname'];
			$blogid = $aCategory['blogid'];
		
			$res = $this->_getURLPartPlugin()->addChangeURLPart($categoryname, $typeid, $categoryid, $blogid);
		}

		$res = $this->_getURLPartPlugin()->urlPartMaintDone($typeid);
		if ($res === false) { return false; }

		return true;
	}
	
	function _addChangeCategoryPart($categoryid, $categoryname)
	{
		$typeid = $this->_getCategoryPartTypeId();
		if($typeid === false) { return false; }

		$aCategory = $this->_getCategoryByCategoryId($categoryid);
		if ($aCategoryInfo === false) { return false; }
		$aCategory = $aCategory['0'];
		
		$blogid = $aCategory['blogid'];
		
		return $this->_getURLPartPlugin()->addChangeURLPart($categoryname, $typeid, $categoryid, $blogid);
	}
	
	function _removeCategoryPart($categoryid)
	{
		$typeid = $this->_getCategoryPartTypeId();
		if($typeid === false) { return false; }

		return $this->_getURLPartPlugin()->removeURLPart("", $typeid, $categoryid, 0);
	}	

	////////////////////////////////////////////////////////////////////////
	// Internal functions: Special Skin urlparts
	function _getSpecialPartTypeId()
	{
		if(!$this->specialPartTypeId)
		{
			$this->specialPartTypeId = $this->_getURLPartPlugin()->findTypeId("Special", $this->getName());
			
			if($this->specialPartTypeId === false || $this->specialPartTypeId == 0) { return false; }
		}

		return $this->specialPartTypeId;
	}

	function _initializeSpecialPartForBlog($skinid, $blogid)
	{
		global $CONF;

		$typeid = $this->_getSpecialPartTypeId();
		if(! $typeid)
		{
			$typeid = $this->specialPartTypeId = $this->_getURLPartPlugin()->addType("Special", $this->getName(), 'B', 'special', 50, $CONF['SpecialskinKey']);
			if($typeid === false) { return false; }
		}

		$aSpecialInfo = $this->_getSpecialFromSkinId($skinid);
		if ($aSpecialInfo === false) { return false; }

		$res = $this->_getURLPartPlugin()->urlPartMaintStart($typeid, $blogid);
		if ($res === false) { return false; }

		foreach($aSpecialInfo as $aSpecial)
		{
			$skinname = $aSpecial['skinname'];

			$res = $this->_getURLPartPlugin()->addChangeURLPart($skinname, $typeid, 0, $blogid);
		}

		$res = $this->_getURLPartPlugin()->urlPartMaintDone($typeid);
		if ($res === false) { return false; }

		return true;
	}

	function _initializeSpecialPartForAllBlogs()
	{
		$aBlogInfo = $this->_getBlogAll();
		if ($aBlogInfo === false) { return false; }
		
		foreach($aBlogInfo as $aBlog)
		{
			$blogid = $aBlog['blogid'];
			$skinid = $aBlog['skinid'];
		
			$res = $this->_initializeSpecialPartForBlog($skinid, $blogid);
			if ($res === false) { return false; }
		}
		
		return true;
	}

	function _checkSpecialPartForBlog($skinid, $blogid)
	{
		$typeid = $this->_getSpecialPartTypeId();
	
		$aSpecialInfo = $this->_getSpecialFromSkinId($skinid);
		if ($aSpecialInfo === false) { return false; }
	
		$aURLPartInfo = $this->_getURLPartPlugin()->findURLPartByTypeIdBlogId($typeid, $blogid);
		if ($aURLPartInfo === false) { return false; }

		foreach($aSpecialInfo as $aSpecial)
		{
			$skinname = $aSpecial['skinname'];
			$found = false;

			foreach($aURLPartInfo as $aURLPart)
			{
				$urlpartname = $aURLPart['urlpartname'];
				
				if($urlpartname == $skinname)
				{
					$found = true;
				}
			}
			
			if(!$found) { return ''; }
		}
		
		foreach($aURLPartInfo as $aURLPart)
		{
			$urlpartname = $aURLPart['urlpartname'];
			$found = false;

			foreach($aSpecialInfo as $aSpecial)
			{
				$skinname = $aSpecial['skinname'];

				if($urlpartname == $skinname)
				{
					$found = true;
				}
			}

			if(!$found) { return ''; }
		}
		
		return true;
	}
	
	function _checkSpecialPartForAllBlogs()
	{
		$aBlogInfo = $this->_getBlogAll();
		if ($aBlogInfo === false) { return false; }
		
		foreach($aBlogInfo as $aBlog)
		{
			$blogid = $aBlog['blogid'];
			$skinid = $aBlog['skinid'];
		
			$res = $this->_checkSpecialPartForBlog($skinid, $blogid);
			if ($res === false) { return false; }
			
			if(!$res) { return ''; }
		}
		
		return true;
	}

	////////////////////////////////////////////////////////////////////////
	// Internal functions: Item Freeform urlparts
	function _getItemFreeformPartTypeId()
	{
		if(!$this->itemFreeformPartTypeId)
		{
			$this->itemFreeformPartTypeId = $this->_getURLPartPlugin()->findTypeId("Item Freeform", $this->getName());
			
			if($this->itemFreeformPartTypeId === false || $this->itemFreeformPartTypeId == 0) { return false; }
		}

		return $this->itemFreeformPartTypeId;
	}
	
	function _initializeItemFreeformPart()
	{
		global $CONF;

		$typeid = $this->_getItemFreeformPartTypeId();
		if(! $typeid)
		{
			$typeid = $this->itemFreeformPartTypeId = $this->_getURLPartPlugin()->addType("Item Freeform", $this->getName(), 'M', 'itemid', 100, '');
			if($typeid === false) { return false; }
		}

		return true;
	}
	
	function _addChangeItemFreeformPart($itemid, $itemname, $itemfreeform)
	{
		$typeid = $this->_getItemFreeformPartTypeId();
		if($typeid === false) { return false; }

		$blogid = getBlogIDFromItemID($itemid);
		if(! $blogid) { return false; }
		
		return $this->_getURLPartPlugin()->addChangeURLPart($itemname, $typeid, $itemid, $blogid, $itemfreeform);
	}
	
	function _removeItemFreeformPart($itemid)
	{
		$typeid = $this->_getItemFreeformPartTypeId();
		if($typeid === false) { return false; }

		return $this->_getURLPartPlugin()->removeURLPart("", $typeid, $itemid, 0);
	}	

	function _useFreeformItemTemplate($blogid, $operation)
	{
		$use = false;
		
		$option = $this->getBlogOption($blogid, 'blogffitemtemplon');
		
		if($option == 'global')
		{
			$option = $this->getOption('globalffitemtemplon');
		}
		
		switch($option)
		{
			case 'never':
				$use = false;
				break;
			case 'itemcreate':
				if($operation == 'create')
				{
					$use = true;
				}
				else
				{
					$use = false;
				}
				break;
			case 'itemcreateupdate':
				if($operation == 'create' || $operation == 'update')
				{
					$use = true;
				}
				else
				{
					$use = false;
				}
				break;
			default:
				$use = false;
				break;
		}
	
		return $use;
	}
	
	function _allowFreeformItemInput($blogid, $operation)
	{
		$allow = false;

		$option = $this->getBlogOption($blogid, 'blogffiteminput');
		
		if($option == 'global')
		{
			$option = $this->getOption('globalffiteminput');
		}
		
		switch($option)
		{
			case 'never':
				$allow = false;
				break;
			case 'itemcreate':
				if($operation == 'create')
				{
					$allow = true;
				}
				else
				{
					$allow = false;
				}
				break;
			case 'itemcreateupdate':
				if($operation == 'create' || $operation == 'update')
				{
					$allow = true;
				}
				else
				{
					$allow = false;
				}
				break;
			default:
				$allow = false;
				break;
		}
	
		return $allow;
	}
	
	function _fillFreeformItemTemplate($blogid, $itemid, $catid, $itemname, $timestamp)
	{
		$itemfreeform = '';

		$usetemplate = $this->getBlogOption($blogid, 'blogffitemtempluse');
		
		switch($usetemplate)
		{
			case 'globaltemplate':
				$template = $this->getOption('globalffitemtempl');
				break;
			case 'blogtemplate':
				$template = $this->getBlogOption($blogid, 'blogffitemtempl');
				break;
			default:
				$template = '';
				break;
		}

		if(!$template)
		{
			$template = '/%cat%/%itemid5%/%yy%-%mm%-%dd%/%title%';
		}
		
		$aCategory = $this->_getCategoryByCategoryId($catid);
		if ($aCategory === false) { return false; }
		$aCategory = $aCategory['0'];
		$catname = $aCategory['categoryname'];
		
		$aDateTime = getdate($timestamp);
		$yy = substr($aDateTime['year'], -2);
		$yyyy = $aDateTime['year'];
		$mm = str_pad($aDateTime['mon'], 2, '0', STR_PAD_LEFT);
		$dd = str_pad($aDateTime['mday'], 2, '0', STR_PAD_LEFT);
					
		$search = array('%title%', '%itemid%', '%itemid3%', '%itemid4%', '%itemid5%', '%itemid6%', 
							'%cat%', '%yy%', '%yyyy%', '%mm%', '%dd%');
	
		$replace = array($itemname, $itemid, 
						str_pad($itemid, 3, '0', STR_PAD_LEFT), 
						str_pad($itemid, 4, '0', STR_PAD_LEFT),
						str_pad($itemid, 5, '0', STR_PAD_LEFT),
						str_pad($itemid, 6, '0', STR_PAD_LEFT),
						$catname, $yy, $yyyy, $mm, $dd);
						
		$itemfreeform = str_replace($search, $replace, $template);
		
		return $itemfreeform;
	}
	
	////////////////////////////////////////////////////////////////////////
	// Internal functions: Custom base URL

	function _initializeTableCustomBaseURL()
	{
		global $CONF;
		
		$aBlogInfo = $this->_getBlogAll();
		if ($aBlogInfo === false) { return false; }
		
		foreach($aBlogInfo as $aBlog)
		{
			$blogid = $aBlog['blogid'];
		
			$res = $this->_insertCustomBaseURL($blogid, '', 0, 0, 0);
			if($res === false) { return false; }
		}
		
		return true;
	}

	////////////////////////////////////////////////////////////////////////
	// Internal functions: Data access Blog
	
	function _getBlogAll()
	{
		return $this->_getBlogInfo();
	}

	function _getBlogInfo()
	{
		$ret = array();
		
		$query = "SELECT bnumber AS blogid, bname AS blogname, bdefskin AS skinid FROM ".sql_table('blog');
		$res = sql_query($query);
		
		if($res)
		{
			while ($o = sql_fetch_object($res)) 
			{
				array_push($ret, array(
					'blogid'	=> $o->blogid,
					'blogname'	=> $o->blogname,
					'skinid'	=> $o->skinid
					));
			}
		}
		else
		{
			return false;
		}
		return $ret;
	}

	////////////////////////////////////////////////////////////////////////
	// Internal functions: Data access Item
	
	function _getItemAll()
	{
		return $this->_getItemInfo(0, 0);
	}
	
	function _getItemByItemId($itemid)
	{
		return $this->_getItemInfo($itemid, 0);
	}

	function _getItemByCategoryId($categoryid)
	{
		return $this->_getItemInfo(0, $categoryid);
	}
	
	function _getItemInfo($itemid, $categoryid)
	{
		$ret = array();
		
		$query = "SELECT inumber AS itemid, ititle AS itemname, iblog AS blogid, UNIX_TIMESTAMP(itime) as timestamp, idraft AS draft, icat AS catid FROM ".sql_table('item')." ";
		
		if($itemid)
		{
			$query .= "WHERE inumber = ".$itemid." ";
		}
		elseif($categoryid)
		{
			$query .= "WHERE  icat = ".$categoryid." ";
		}

		$res = sql_query($query);
		
		if($res)
		{
			while ($o = sql_fetch_object($res)) 
			{
				array_push($ret, array(
					'itemid'	=> $o->itemid,
					'itemname'	=> $o->itemname,
					'blogid'	=> $o->blogid,
					'timestamp'	=> $o->timestamp,
					'draft'		=> $o->draft,
					'catid'		=> $o->catid
					));
			}
		}
		else
		{
			return false;
		}
		return $ret;
	}

	////////////////////////////////////////////////////////////////////////
	// Internal functions: Data access Member
	
	function _getMemberAll()
	{
		return $this->_getMemberInfo(0);
	}

	function _getMemberByMemberId($memberid)
	{
		return $this->_getMemberInfo($memberid);
	}
	
	function _getMemberInfo($memberid)
	{
		$ret = array();
		
		$query = "SELECT mnumber AS memberid, mname AS membername FROM ".sql_table('member')." ";
		
		if($memberid)
		{
			$query .= "WHERE mnumber = ".$memberid." ";
		}

		$res = sql_query($query);
		
		if($res)
		{
			while ($o = sql_fetch_object($res)) 
			{
				array_push($ret, array(
					'memberid'		=> $o->memberid,
					'membername'	=> $o->membername
					));
			}
		}
		else
		{
			return false;
		}
		return $ret;
	}

	////////////////////////////////////////////////////////////////////////
	// Internal functions: Data access Category
	
	function _getCategoryAll()
	{
		return $this->_getCategoryInfo(0);
	}

	function _getCategoryByCategoryId($categoryid)
	{
		return $this->_getCategoryInfo($categoryid);
	}
	
	function _getCategoryInfo($categoryid)
	{
		$ret = array();
		
		$query = "SELECT catid AS categoryid, cname AS categoryname, cblog AS blogid FROM ".sql_table('category')." ";
		
		if($categoryid)
		{
			$query .= "WHERE catid = ".$categoryid." ";
		}

		$res = sql_query($query);
		
		if($res)
		{
			while ($o = sql_fetch_object($res)) 
			{
				array_push($ret, array(
					'categoryid'	=> $o->categoryid,
					'categoryname'	=> $o->categoryname,
					'blogid'		=> $o->blogid
					));
			}
		}
		else
		{
			return false;
		}
		return $ret;
	}

	////////////////////////////////////////////////////////////////////////
	// Internal functions: Data access Skin
	function _getSpecialFromSkinId($skinid)
	{
		return $this->_getSpecialInfo($skinid);
	}
	
	function _getSpecialInfo($skinid)
	{
		$ret = array();
		
		$query = "SELECT sdesc AS skinid, stype AS skinname FROM ".sql_table('skin')." "
				."WHERE stype NOT IN ('index','item','archive','archivelist','search','error','member','imagepopup') "
				."AND sdesc = ".$skinid." ";
		
		$res = sql_query($query);
		
		if($res)
		{
			while ($o = sql_fetch_object($res)) 
			{
				array_push($ret, array(
					'skinid'	=> $o->skinid,
					'skinname'	=> $o->skinname
					));
			}
		}
		else
		{
			return false;
		}
		return $ret;
	}

	////////////////////////////////////////////////////////////////////////
	// Internal functions: Data access CustomBaseURL

	function _insertCustomBaseURL($blogid, $baseurl, $enablebaseurl, $defaultblog, $identifiesblog)
	{
		$query = "INSERT ".$this->_getTableCustomBaseURL()." (blogid, baseurl, enablebaseurl, defaultblog, identifiesblog) "
				."VALUES ("
				.IntVal($blogid).", "
				."'".sql_real_escape_string($baseurl)."', "
				.IntVal($enablebaseurl).", "
				.IntVal($defaultblog).", "
				.IntVal($identifiesblog)." "
				.")";
					
		$res = sql_query($query);
		
		if(!$res)
		{
			return false;
		}
		
		return true;
	}
	
	function _updateCustomBaseURL($blogid, $baseurl, $enablebaseurl, $defaultblog, $identifiesblog)
	{
		$query = "UPDATE ".$this->_getTableCustomBaseURL()." SET "
				."baseurl = '".sql_real_escape_string($baseurl)."', "
				."enablebaseurl = ".IntVal($enablebaseurl).", "
				."defaultblog = ".IntVal($defaultblog).", "
				."identifiesblog = ".IntVal($identifiesblog)." "
				."WHERE blogid = ".IntVal($blogid)." ";
					
		$res = sql_query($query);

		if(!$res)
		{
			return false;
		}
		
		return true;
	}
	
	function _deleteCustomBaseURL($blogid)
	{
		$query = "DELETE FROM ".$this->_getTableCustomBaseURL()." "
				."WHERE blogid = ".IntVal($blogid)." ";
					
		$res = sql_query($query);

		if(!$res)
		{
			return false;
		}
		
		return true;
	}

	function _getCustomBaseURLWithBlogNameAll()
	{
		return $this->_getCustomBaseURLWithBlogName(0, '');
	}

	function _getCustomBaseURLWithBlogNameFromURL($url)
	{
		return $this->_getCustomBaseURLWithBlogName(0, $url);
	}
	
	function _getCustomBaseURLWithBlogNameFromBlogID($blogid)
	{
		return $this->_getCustomBaseURLWithBlogName($blogid, '');
	}

	function _getCustomBaseURLWithBlogName($blogid, $url)
	{
		$ret = array();
		
		if($this->getCurrentDataVersion() >= 5)
		{
			$query = "SELECT c.blogid, b.bname as blogname, c.baseurl, c.enablebaseurl, c.defaultblog, c.identifiesblog "
					."FROM ".$this->_getTableCustomBaseURL()." c, ".sql_table('blog')." b "
					."WHERE c.blogid = b.bnumber ";

			if($blogid)
			{
				$query .= "AND c.blogid = ".IntVal($blogid)." ";
			}
			
			if($url)
			{
				$query .= "AND c.baseurl = Left('".sql_real_escape_string($url)."', Length(c.baseurl)) AND enablebaseurl > 0 ";
			}

			$query .= "ORDER BY b.bname ";
			
			$res = sql_query($query);
			
			if($res)
			{
				while ($o = sql_fetch_object($res)) 
				{
					array_push($ret, array(
						'blogid'    => $o->blogid,
						'blogname'       => $o->blogname,
						'baseurl'		=> $o->baseurl,
						'enablebaseurl'       => $o->enablebaseurl,
						'defaultblog'       => $o->defaultblog,
						'identifiesblog'         => $o->identifiesblog
						));
				}
			}
			else
			{
				return false;
			}
		}
		
		return $ret;
	}

	////////////////////////////////////////////////////////////////////////
	// Plugin Upgrade handling functions
	function getCurrentDataVersion()
	{
		$currentdataversion = $this->getOption('currentdataversion');
		
		if(!$currentdataversion)
		{
			$currentdataversion = 1;
		}
		
		return $currentdataversion;
	}

	function setCurrentDataVersion($currentdataversion)
	{
		$res = $this->setOption('currentdataversion', $currentdataversion);
		$this->clearOptionValueCache(); // Workaround for bug in Nucleus Core
		
		return $res;
	}

	function getCommitDataVersion()
	{
		$commitdataversion = $this->getOption('commitdataversion');
		
		if(!$commitdataversion)
		{
			$commitdataversion = 1;
		}

		return $commitdataversion;
	}

	function setCommitDataVersion($commitdataversion)
	{	
		$res = $this->setOption('commitdataversion', $commitdataversion);
		$this->clearOptionValueCache(); // Workaround for bug in Nucleus Core
		
		return $res;
	}

	function getDataVersion()
	{
		return 6;
	}
	
	function upgradeDataTest($fromdataversion, $todataversion)
	{
		// returns true if rollback will be possible after upgrade
		$res = true;
				
		return $res;
	}
	
	function upgradeDataPerform($fromdataversion, $todataversion)
	{
		// Returns true if upgrade was successfull
		
		for($ver = $fromdataversion; $ver <= $todataversion; $ver++)
		{
			switch($ver)
			{
				case 1:
					$this->createOption('del_uninstall', 'Delete NP_LMFancierURL data on uninstall?', 'yesno','no');
					$this->createOption('hidedefblog','Hide /blog/name for default blog', 'yesno', 'yes');
					$this->createOption('globalurlscheme','Select global URL scheme', 'select', 'fancier', 'Classic|classic|Fancier|fancier|Compact|compact');
					$this->createBlogOption('blogurlscheme','Select Blog URL scheme', 'select', 'global', 'Use Global|global|Classic|classic|Fancier|fancier|Compact|compact');
					
					$this->createOption('globalitemurldate','Include the date in URLs for items', 'select', 'none', 'None|none|yyyy-mm-dd|yyyymmdd|yyyy-mm|yyyymm');
					$this->createBlogOption('blogitemurldate','Include the date in URLs for items', 'select', 'global', 'Use Global|global|None|none|yyyy-mm-dd|yyyymmdd|yyyy-mm|yyyymm');

					$this->createOption('globalitemurlcat','Include the category in URLs for items', 'select', 'systemdecide', 'System Decide|systemdecide|Always|always|Never|never');
					$this->createBlogOption('blogitemurlcat','Include the category in URLs for items', 'select', 'global', 'Use Global|global|System Decide|systemdecide|Always|always|Never|never');

					$this->createOption('globalitemparsecat','Parse the category in URLs for items', 'select', 'always', 'Always|always|Never|never');
					$this->createBlogOption('blogitemparsecat','Parse the category in URLs for items', 'select', 'global', 'Use Global|global|Always|always|Never|never');

					$this->_initializeAllURLParts();

					$res = true;
					break;
				case 2:
					$this->createOption('currentdataversion', 'currentdataversion', 'text','1', 'access=hidden');
					$this->createOption('commitdataversion', 'commitdataversion', 'text','1', 'access=hidden');
					$res = true;
					break;
				case 3:
					$this->_initializeItemFreeformPart();
					$res = true;
					break;
				case 4:
					$this->createOption('globalffiteminput','Allow freeform item URL part input on', 'select', 'never', 'Never|never|Item Create|itemcreate|Item Create+Update|itemcreateupdate');
					$this->createBlogOption('blogffiteminput','Allow freeform item URL part input on', 'select', 'global', 'Use Global|global|Never|never|Item Create|itemcreate|Item Create+Update|itemcreateupdate');
				
					$this->createOption('globalffitemtempl','Freeform item URL part template', 'text');
					$this->createBlogOption('blogffitemtempl','Freeform item URL part template', 'text');
					$this->createBlogOption('blogffitemtempluse','Which freeform item URL part template to use', 'select', 'globaltemplate', 'Global Template|globaltemplate|Blog Template|blogtemplate');
					
					$this->createOption('globalffitemtemplon','Use freeform item URL part template on', 'select', 'never', 'Never|never|Item Create|itemcreate|Item Create+Update|itemcreateupdate');
					$this->createBlogOption('blogffitemtemplon','Use freeform item template on', 'select', 'global', 'Use Global|global|Never|never|Item Create|itemcreate|Item Create+Update|itemcreateupdate');

					$res = true;
					break;
				
				case 5:
					$this->_createTableCustomBaseURL();
					$this->_initializeTableCustomBaseURL();

					$res = true;
					break;

				case 6:
					$this->createOption('redirectdefaultblog','Redirect to default blog index page?', 'yesno', 'no');
					$this->createBlogOption('memberlinkblogid','Include blog urlpart in member link?', 'yesno', 'no');

					$res = true;
					break;

				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}
		
		return true;
	}
	
	function upgradeDataRollback($fromdataversion, $todataversion)
	{
		// Returns true if rollback was successfull
		for($ver = $fromdataversion; $ver >= $todataversion; $ver--)
		{
			switch($ver)
			{
				case 2:
					$this->deleteOption('currentdataversion');
					$this->deleteOption('commitdataversion');
					$res = true;
					break;
				case 3:
					$typeid = $this->_getItemFreeformPartTypeId();
					if($typeid) $this->_getURLPartPlugin()->removeType($typeid);
					$res = true;
					break;
				case 4:
					$this->deleteOption('globalffiteminput');
					$this->deleteBlogOption ('blogffiteminput');
				
					$this->deleteOption('globalffitemtempl');
					$this->deleteBlogOption('blogffitemtempl');
					$this->deleteBlogOption('blogffitemtempluse');
					
					$this->deleteOption('globalffitemtemplon');
					$this->deleteBlogOption('blogffitemtemplon');
					$res = true;
					break;

				case 5:
					$this->_dropTableCustomBaseURL();
					
					$res = true;
					break;

				case 6:
					$this->deleteOption('redirectdefaultblog');
					$this->deleteBlogOption('memberlinkblogid');

					$res = true;
					break;

				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}

		return true;
	}

	function upgradeDataCommit($fromdataversion, $todataversion)
	{
		// Returns true if commit was successfull
		for($ver = $fromdataversion; $ver <= $todataversion; $ver++)
		{
			switch($ver)
			{
				case 2:
				case 3:
				case 4:
				case 5:
				case 6:
					$res = true;
					break;
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}
		return true;
	}
	
	function _checkColumnIfExists($table, $column)
	{
		// Retuns: $column: Found, '' (empty string): Not found, false: error
		$found = '';
		
		$res = sql_query("SELECT * FROM ".$table." WHERE 1 = 2");

		if($res)
		{
			$numcolumns = sql_num_fields($res);

			for($offset = 0; $offset < $numcolumns && !$found; $offset++)
			{
				if(sql_field_name($res, $offset) == $column)
				{
					$found = $column;
				}
			}
		}
		
		return $found;
	}
	
	function _addColumnIfNotExists($table, $column, $columnattributes)
	{
		$found = $this->_checkColumnIfExists($table, $column);
		
		if($found === false) 
		{
			return false;
		}
		
		if(!$found)
		{
			$res = sql_query("ALTER TABLE ".$table." ADD ".$column." ".$columnattributes);

			if(!$res)
			{
				return false;
			}
		}

		return true;
	}

	function _dropColumnIfExists($table, $column)
	{
		$found = $this->_checkColumnIfExists($table, $column);
		
		if($found === false) 
		{
			return false;
		}
		
		if($found)
		{
			$res = sql_query("ALTER TABLE ".$table." DROP COLUMN ".$column);

			if(!$res)
			{
				return false;
			}
		}

		return true;
	}
	
	function _initializeAllURLParts()
	{
		$this->_initializeBlogPart();
		$this->_initializeItemPart();		
		$this->_initializeArchivesPart();		
		$this->_initializeArchivePart();		
		$this->_initializeMemberPart();		
		$this->_initializeCategoryPart();
		
		return true;
	}
	
	function _getTableCustomBaseURL()
	{
		// select * from nucleus_plug_lmfancierurl_custombaseurl;
		return sql_table('plug_lmfancierurl_custombaseurl');
	}

	function _createTableCustomBaseURL()
	{
		$query  = "CREATE TABLE IF NOT EXISTS ".$this->_getTableCustomBaseURL();
		$query .= "( ";
		$query .= "blogid int(11) NOT NULL, ";
		$query .= "baseurl varchar(255) NOT NULL, ";
		$query .= "enablebaseurl int(11) NOT NULL, ";
		$query .= "defaultblog int(11) NOT NULL, ";
		$query .= "identifiesblog int(11) NOT NULL, ";
		$query .= "PRIMARY KEY (blogid) ";
		$query .= ") ";
		
		sql_query($query);
	}

	function _dropTableCustomBaseURL()
	{
		sql_query("DROP TABLE IF EXISTS ".$this->_getTableCustomBaseURL());
	}
}
?>