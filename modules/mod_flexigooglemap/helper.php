<?php
/**
* @version 0.6 stable $Id: helper.php yannick berges
* @package Joomla
* @subpackage FLEXIcontent
* @copyright (C) 2015 Berges Yannick - www.com3elles.com
* @license GNU/GPL v2

* special thanks to ggppdk and emmanuel dannan for flexicontent
* special thanks to my master Marc Studer

* FLEXIadmin module is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
**/

// no direct access
defined('_JEXEC') or die('Restricted access');

class modFlexigooglemapHelper
{
	public static function getItemsLocations(&$params)
	{
		$fieldaddressid = $params->get('fieldaddressid');
		if ( empty($fieldaddressid) )
		{
			echo '<div class="alert alert-warning">' . JText::_('MOD_FLEXIGOOGLEMAP_ADDRESSFORGOT') .'</div>';
			return null;
		}

		// By default include children categories
		$treeinclude = $params->get('treeinclude', 1);

		// Make sure categories is an array
		$catids = $params->get('catid');
		$catids = is_array($catids) ? $catids : array($catids);

		// Retrieve extra categories, such children or parent categories
		$catids_arr = flexicontent_cats::getExtraCats($catids, $treeinclude, array());

		// Check if zero allowed categories
		if (empty($catids_arr))
		{
			return array();
		}

		$count = $params->get('count');
		$forced_itemid = $params->get('forced_itemid', 0);

		// Include : 1 or Exclude : 0 categories
		$method_category = $params->get('method_category', '1');

		$catWheres = $method_category == 0
			? ' rel.catid IN (' . implode(',', $catids_arr) . ')'
			: ' rel.catid NOT IN (' . implode(',', $catids_arr) . ')';

		$db = JFactory::getDbo();
		$queryLoc = 'SELECT a.id, a.title, b.field_id, b.value , a.catid '
			.' FROM #__content  AS a'
			.' JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = a.id '
			.' LEFT JOIN #__flexicontent_fields_item_relations AS b ON a.id = b.item_id '
			.' WHERE b.field_id = ' . $fieldaddressid.' AND ' . $catWheres . '  AND state = 1'
			.' ORDER BY title '.$count
			;
		$db->setQuery( $queryLoc );
		$itemsLoc = $db->loadObjectList();

		foreach ($itemsLoc as &$itemLoc)
		{
			$itemLoc->link = JRoute::_(FlexicontentHelperRoute::getItemRoute($itemLoc->id, $itemLoc->catid, $forced_itemid, $itemLoc));
		}

		return $itemsLoc;
	}


	public static function renderMapLocations($params)
	{
		$uselink = $params->get('uselink', '');
		$useadress = $params->get('useadress', '');

		$linkmode = $params->get('linkmode', '');
		$readmore = JText::_($params->get('readmore', 'MOD_FLEXIGOOGLEMAP_READMORE_TXT'));

		$usedirection = $params->get('usedirection', '');
		$directionname = JText::_($params->get('directionname', 'MOD_FLEXIGOOGLEMAP_DIRECTIONNAME_TXT'));

		$infotextmode = $params->get('infotextmode', '');
		$relitem_html = $params->get('relitem_html','');

		$fieldaddressid = $params->get('fieldaddressid');
		$forced_itemid = $params->get('forced_itemid', 0);

		$mapLocations = array();

		// Fixed category mode
		if ($params->get('catidmode') == 0)
		{
			$itemsLocations = modFlexigooglemapHelper::getItemsLocations($params);
			foreach ($itemsLocations as $itemLoc)
			{
				if ( empty($itemLoc->value) ) continue;   // skip empty value

				$coord = unserialize($itemLoc->value);
				if ( !isset($coord['lat']) || !isset($coord['lon']) ) continue;    // skip empty value

				$title = rtrim( addslashes($itemLoc->title) );
				$link = '';
				$addr = '';
				$linkdirection = '';

				if ($uselink)
				{
					$link = $itemLoc->link;
					$link = '<p class="link"><a href="'.$link.'" target="'.$linkmode.'">' . $readmore . '</a></p>';
					$link = addslashes($link);
				}

				if ($useadress && !empty($coord['addr_display']))
				{
					$addr = '<p>'.$coord['addr_display'].'</p>';
					$addr = addslashes($addr);
					$addr = preg_replace("/(\r\n|\n|\r)/", " ", $addr);
				}

				if ($usedirection)
				{
					// generate link to google maps directions
					$map_link = empty($coord['url'])  ?  false  :  $coord['url'];

					// if no url, compatibility with old values
					if (empty($map_link))
					{
						$map_link = "http://maps.google.com/maps?q=";
						if (!empty($coord['addr1']) && !empty($coord['city']) && (!empty($coord['province']) || !empty($coord['state']))  && !empty($coord['zip']))
						{
							$map_link .= urlencode(($coord['addr1'] ? $coord['addr1'].',' : '')
								.($coord['city'] ? $coord['city'].',' : '')
								.($coord['state'] ? $coord['state'].',' : ($coord['province'] ? $coord['province'].',' : ''))
								.($coord['zip'] ? $coord['zip'].',' : '')
								.($coord['country'] ? JText::_('PLG_FC_ADDRESSINT_CC_'.$coord['country']) : ''));
						}
						else
						{
							$map_link .= urlencode($coord['lat'] . "," . $coord['lon']); 
						}
					}

					$linkdirection= '<div class="directions"><a href="' . $map_link . '" target="_blank" class="direction">' . $directionname . '</a></div>';
				}

				$contentwindows = $infotextmode //données venant d'un text area du type html + shortcode (your text {{text-2}}) venant de la fonction createDisplayHTML()
					? $relitem_html
					: $addr . ' ' . $link;

				$coordinates = $coord['lat'] .','. $coord['lon'];
				$mapLocations[] = "['<h4 class=\"fleximaptitle\">$title</h4>$contentwindows $linkdirection'," . $coordinates . "]\r\n";
			}
		}

		// Current category mode or current item mode, these are pre-created (global variables)
		else
		{
			// Current category mode
			if ($params->get('catidmode') == 1)
			{
				// Get items of current view
				global $fc_list_items;
				if ( empty($fc_list_items) )
				{
					$fc_list_items = array();
				}
			}

			// Get current item
			else
			{
				global $fc_view_item;
				$fc_list_items = !empty($fc_view_item)
					? array($fc_view_item)
					: array();
			}

			foreach ($fc_list_items as $address)
			{
				// Skip item if it has no address value
				if ( empty($address->fieldvalues[$fieldaddressid]) )
				{
					continue;
				}

				// Get first value, typically this is value [0], and unserialize it
				foreach($address->fieldvalues[$fieldaddressid] as $coord)
				{
					$coord = flexicontent_db::unserialize_array($coord, false, false);
					if (!$coord) continue;

					// Skip value that has no cordinates
					if ( !isset($coord['lat']) || !isset($coord['lon']) ) continue;
					if ( !strlen($coord['lat']) || !strlen($coord['lon']) ) continue;

					$title = addslashes($address->title);
					$link = '';
					$addr = '';
					$linkdirection = '';

					if ($uselink)
					{
						$link = JRoute::_(FlexicontentHelperRoute::getItemRoute($address->id, $address->catid, $forced_itemid, $address));
						$link = '<p class="link"><a href="'.$link.'" target="'.$linkmode.'">' . $readmore . '</a></p>';
						$link = addslashes($link);
					}

					if ($useadress && !empty($coord['addr_display']))
					{
						$addr = '<p>'.$coord['addr_display'].'</p>';
						$addr = addslashes($addr);
						$addr = preg_replace("/(\r\n|\n|\r)/", " ", $addr);
					}

					if ($usedirection)
					{
						// generate link to google maps directions
						$map_link = empty($coord['url'])  ?  false  :  $coord['url'];

						// if no url, compatibility with old values
						if (empty($map_link))
						{
							$map_link = "http://maps.google.com/maps?q=";
							if (!empty($coord['addr1']) && !empty($coord['city']) && (!empty($coord['province']) || !empty($coord['state']))  && !empty($coord['zip']))
							{
								$map_link .= urlencode(($coord['addr1'] ? $coord['addr1'].',' : '')
									.($coord['city'] ? $coord['city'].',' : '')
									.($coord['state'] ? $coord['state'].',' : ($coord['province'] ? $coord['province'].',' : ''))
									.($coord['zip'] ? $coord['zip'].',' : '')
									.($coord['country'] ? JText::_('PLG_FC_ADDRESSINT_CC_'.$coord['country']) : ''));
							}
							else
							{
								$map_link .= urlencode($coord['lat'] . "," . $coord['lon']); 
							}
						}

						$linkdirection= '<div class="directions"><a href="' . $map_link . '" target="_blank" class="direction">' . $directionname . '</a></div>';
					}

					$contentwindows = $infotextmode
						? $relitem_html
						: $addr . ' ' . $link;

					$coordinates = $coord['lat'] .','. $coord['lon'];
					$mapLocations[] = "['<h4 class=\"fleximaptitle\">$title</h4>$contentwindows $linkdirection'," . $coordinates . "]\r\n";
				}
			}
		}

		return $mapLocations;
	}


	public static function getMarkerURL(&$params)
	{
		// Get marker mode, 'lettermarkermode' was old parameter name, (in future wew may more more modes, so the old parameter name was renamed)
		$markermode = $params->get('markermode', $params->get('lettermarkermode', 0));

		switch ($markermode)
		{
			case 1:   // 'Letter' mode
				$color_to_file = array(
					'red'=>'spotlight-waypoint-b.png', 'green'=>'spotlight-waypoint-a.png', ''=>'spotlight-waypoint-b.png' /* '' is for not set*/
				);
				return "'https://mts.googleapis.com/vt/icon/name=icons/spotlight/"
					. $color_to_file[$params->get('markercolor', '')]
					. "?text=" . $params->get('lettermarker')
					. "&psize=16&font=fonts/arialuni_t.ttf&color=ff330000&scale=1&ax=44&ay=48"
					. "'";

			default:  // 'Local image file' mode
				$markerimage = $params->get('markerimage');
				return $markerimage ? ("'" . JUri::root(true) . '/' . $markerimage . "'") : 'null';
		}
	}

		
	
	
	
	
	
	
	
	
	
	
	
	
	
	// Helper method to create HTML display of an item list according to replacements
		public function createDisplayHTML($field, $item, $grouped_fields, $infotextmode, $max_count, $pretext, $posttext)
		{
			//return array('"<b>Custom HTML</b>" display for fieldgroup field, is not implemented yet, please use default HTML');
	
			if (!$infotextmode)
			{
				return "Empty custom HTML variable for group field: ". $field->label;
			}
	
	
			/**
			 * Parse and identify custom fields
			 */
	
			$result = preg_match_all("/\{\{([a-zA-Z_0-9-]+)(##)?([a-zA-Z_0-9-]+)?\}\}/", $infotextmode, $field_matches);
			$gf_reps    = $result ? $field_matches[0] : array();
			$gf_names   = $result ? $field_matches[1] : array();
			$gf_methods = $result ? $field_matches[3] : array();
	
			/*foreach ($gf_names as $i => $gf_name)
			{
				$parsed_fields[] = $gf_names[$i] . ($gf_methods[$i] ? "->". $gf_methods[$i] : "");
			}
			echo "$contentwindows :: Fields for Related Items List: ". implode(", ", $parsed_fields ? $parsed_fields : array() ) ."<br/>\n";*/
	
			
			//Definition du nom des champs
			$_name_to_field = array($grouped_fields);
			foreach($grouped_fields as $i => $grouped_field)
			{
				$_name_to_field[$grouped_field->name] = $grouped_fields[$i];
			}
			print_r(array_keys($_name_to_field)); echo "<br/>";
	
	
			/**
			 * Replace ITEM properties
			 */
	
			preg_match_all("/{item->([0-9a-zA-Z_]+)}/", $infotextmode, $matches);
	
			foreach ($matches[0] as $i => $replacement_tag)
			{
				$prop_name = $matches[1][$i];
				if (isset($item->{$prop_name}))
				{
					$infotextmode = str_replace($replacement_tag, $item->{$prop_name}, $infotextmode);
				}
			}
	
	
			/**
			 * Replace language strings
			 */
	
			$result = preg_match_all("/\%\%([^%]+)\%\%/", $infotextmode, $translate_matches);
			$translate_strings = $result ? $translate_matches[1] : array('FLEXI_READ_MORE_ABOUT');
	
			foreach ($translate_strings as $translate_string)
			{
				$infotextmode = str_replace('%%'.$translate_string.'%%', JText::_($translate_string), $infotextmode);
			}
	
	
			/**
			 * Render and Replace HTML display of fields
			 */
	
			$_rendered_fields = array();
			if ( count($gf_names) )
			{
				$app = JFactory::getApplication();
				$view = $app->input->get('flexi_callview', $app->input->get('view', 'item', 'cmd'), 'cmd');
				$gf_props = array();
				foreach($gf_names as $pos => $grp_field_name)
				{
					// Check that field exists and is assigned the fieldgroup field (needed only when using custom fieldgroup display HTML)
					if (!isset($_name_to_field[$grp_field_name]))
					{
						continue;
					}
	
					// Check that field is assigned to the content type
					if (!isset($item->fields[$grp_field_name]))
					{
						continue;
					}
	
					$_grouped_field = $_name_to_field[$grp_field_name];
	
					// Get item's field object, set 'value' and 'ingroup' properties
					$grouped_field = $item->fields[$_grouped_field->name];
					$grouped_field->value = $_grouped_field->value;
					$grouped_field->ingroup = 1;  // render as array
					$_values = null;
					$_rendered_fields[$pos] = $grouped_field;
	
					// Check if display method is 'label' aka nothing to render
					if ( $gf_methods[$pos] == 'label' ) continue;
	
					// Get custom display method (optional)
					$method = $gf_methods[$pos] ? $gf_methods[$pos] : 'display';
	
					// Backup display method of the field in cases it is displayed outside of fieldgroup too
					if (isset($grouped_field->$method))
					{
						$grouped_field->{$method.'_non_arr'} = $grouped_field->$method;
						unset($grouped_field->$method);
					}
	
					// SAME field with SAME method, may have been used more than ONCE, inside the custom HTML parameter, so check if field has been rendered already
					if ( isset($grouped_field->{$method.'_arr'}) && is_array($grouped_field->{$method.'_arr'}) ) continue;
	
					// Render the display method for the given field
					//echo 'Rendering: '. $grouped_field->name . ', method: ' . $method . '<br/>';
					//FLEXIUtilities::call_FC_Field_Func($grouped_field->field_type, 'onDisplayFieldValue', array(&$grouped_field, $item, $_values, $method));
					FlexicontentFields::renderField($item, $grouped_field, $_values, $method, $view, $_skip_trigger_plgs = true);  // We will trigger only once the final result
	
					// Set custom display variable of field inside group
					$grouped_field->{$method.'_arr'} = isset($grouped_field->$method) ? $grouped_field->$method : null;
					unset($grouped_field->$method);
					unset($grouped_field->ingroup);
	
					// Restore non-fieldgroup display of the field
					if (isset($grouped_field->{$method.'_non_arr'}))
					{
						$grouped_field->$method = $grouped_field->{$method.'_non_arr'};
						unset($grouped_field->{$method.'_non_arr'});
					}
				}
			}
	
	
			/**
			 * Render the value list of the fieldgroup, using custom HTML for each
			 * value-set of the fieldgroup, and performing the field replacements
			 */
	
			// Get labels to hide on empty values
			// $hide_lbl_ifnoval = $this->getHideLabelsOnEmpty($field);
	
			$custom_display = array();
			//echo "<br/>max_count: ".$max_count."<br/>";
			for ($n = 0; $n < $max_count; $n++)
			{
				$rendered_html = $infotextmode;
				foreach($_rendered_fields as $pos => $_rendered_field)
				{
					$method = $gf_methods[$pos] ? $gf_methods[$pos] : 'display';
	
					//echo 'Replacing: '. $_rendered_field->name . ', method: ' . $method . ', index: ' .$n. '<br/>';
					if ($method !== 'label' && $method !== 'id' && $method !== 'name')
					{
						$_html = isset($_rendered_field->{$method.'_arr'}[$n]) ? $_rendered_field->{$method.'_arr'}[$n] : '';
					}
	
					// Skip (hide) label for field having none display HTML (is such behaviour was configured)
					elseif ($method === 'label')
					{
						$_html = isset($hide_lbl_ifnoval[$_rendered_field->id])  &&  (!isset($_rendered_field->{$method.'_arr'}) || !isset($_rendered_field->{$method.'_arr'}[$n]) || !strlen($_rendered_field->{$method.'_arr'}[$n]))
							? ''
							: $_rendered_field->label;
					}
	
					// id (and in future other properties ?)
					else
					{
						$_html = ! in_array($method, array('id', 'name'))
							? ''
							: $_rendered_field->{$method};
					}
	
					$rendered_html = str_replace($gf_reps[$pos], $_html, $rendered_html);
				}
	
				// Replace value position
				$rendered_html = str_replace('{{value##count}}', $n, $rendered_html);
	
				$custom_display[$n] = $pretext . $rendered_html . $posttext;
			}
	
			return $custom_display;
		}
}