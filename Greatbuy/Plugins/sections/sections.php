<?php
/*

*Plugin Name: Sections
*Description: Parses HTML content for sections demarcated by heading elements: wraps HTML5 section elements around them, and generates table of contents with links to each section.
*Version: 1.0.0
*Author: Tejas Prajapati
*Author URI: https://tp7211.netlify.app/
*Text Domain: Sections

*/
 
function sectionize_activate(){
	add_option('sectionize_id_prefix', 'section-'); //§
	add_option('sectionize_start_section', '<section id="%id">');
	add_option('sectionize_end_section',  '</section>');
	add_option('sectionize_include_toc_threshold', 2); //-1 means never include TOC
	add_option('sectionize_before_toc', '<nav class="toc">');
	add_option('sectionize_after_toc',  '</nav>');
	add_option('sectionize_disabled',  false);
}
register_activation_hook(__FILE__, "sectionize_activate");

function sectionize($original_content,
					$id_prefix = null,
					$start_section = null,
					$end_section = null,
					$include_toc_threshold = null,
					$before_toc = null,
					$after_toc = null)
{
	//Return immediately if sectionize is disabled for this post
	if(_sectionize_get_postmeta_or_option('sectionize_disabled'))
		return $original_content;
	
	//Verify that the content actually has headings and gather them up
	if(!preg_match_all('{<h([1-6])\b.*?>(.*?)</h\1\s*>}si', $original_content, $headingMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) # | PREG_OFFSET_CAPTURE
		return $original_content;
	
	//Prepended to the sanitized title of a heading element
	if(is_null($id_prefix))
		$id_prefix = _sectionize_get_postmeta_or_option('sectionize_id_prefix');
	
	//The markup inserted before and after the sections
	if(is_null($start_section))
		$start_section = _sectionize_get_postmeta_or_option('sectionize_start_section');
	if(is_null($end_section))
		$end_section = _sectionize_get_postmeta_or_option('sectionize_end_section');
	$end_section_len = strlen($end_section);
	
	//Whether to include the TOC, and what comes before and after it
	if(is_null($include_toc_threshold))
		$include_toc_threshold = _sectionize_get_postmeta_or_option('sectionize_include_toc_threshold');
	
	//Determine whether or not the TOC should be included: -1 threshold means
	//  never include TOC, and any positive integer means the minimum number of
	//  headings that must be in the content before the TOC will get prepended.
	$include_toc = ($include_toc_threshold >= 0 && $include_toc_threshold <= count($headingMatches));
	if($include_toc){
		if(is_null($before_toc))
			$before_toc = _sectionize_get_postmeta_or_option('sectionize_before_toc');
		if(is_null($after_toc))
			$after_toc = _sectionize_get_postmeta_or_option('sectionize_after_toc');
	}

	//Get TOC and updated content ready
	$level = 0; //current indentation level
	$toc = '';
	if($include_toc)
		$toc = $before_toc . apply_filters('sectionize_start_toc_list', "<ol>", $level);
	$content = $original_content;
	$offset = 0;
	
	//Keep track of the IDs used so that we don't collide
	$usedIDs = array();
	for($i = 0; $i < count($headingMatches); $i++){
		
		// Generate a unique ID for the section
		$headingText = html_entity_decode(strip_tags($headingMatches[$i][2][0]), ENT_QUOTES, get_option('blog_charset'));
		$sanitizedTitle = sanitize_title_with_dashes($headingText);
		$id = apply_filters('sectionize_id', $id_prefix . $sanitizedTitle, $id_prefix, $headingText);
		if(isset($usedIDs[$id])){
			$count = 0;
			do {
				$count++;
				$id2 = $id . '-' . $count;
			}
			while(isset($usedIDs[$id2]));
			$id = $id2;
			unset($id2);
		}
		$usedIDs[$id] = true;
		
		if($i){
			$levelDiff = (int)$headingMatches[$i][1][0] - (int)$headingMatches[$i-1][1][0];
		
			// This level is greater (deeper)
			if($levelDiff == 1){
				if($include_toc)
					$toc .= apply_filters('sectionize_start_toc_list', "<ol>", $level);
				$level++;
			}
			// This level is lesser (shallower)
			else if($levelDiff < 0){
				$level += $levelDiff;
				//Error
				if($level < 0)
					return "\n<!-- It appears you started with a heading that is larger than one following it. -->\n" . $original_content;
				
				//End Section
				$content = substr_replace($content, $end_section, $headingMatches[$i][0][1]+$offset, 0);
				$offset += $end_section_len;
				
				if($include_toc)
					$toc .= '</li>';
				while($levelDiff < 0){
					if($include_toc)
						$toc .= apply_filters('sectionize_end_toc_list', "</ol>")
						      . apply_filters('sectionize_end_toc_item', '</li>');
					
					//End Section
					$content = substr_replace($content, $end_section, $headingMatches[$i][0][1]+$offset, 0);
					$offset += $end_section_len;
					
					$levelDiff++;
				}
			}
			//Heading is the same as the previous
			else if($levelDiff == 0) {
				if($include_toc)
					$toc .= apply_filters('sectionize_end_toc_item', '</li>');
				
				//End Section
				$content = substr_replace($content, $end_section, $headingMatches[$i][0][1]+$offset, 0);
				$offset += $end_section_len;
			}
			//Error!
			else { //($levelDiff > 1)
				return "\n<!-- Headings must only be incremented one at a time! You went from <h" . $headingMatches[$i-1][1][0] . "> to <h" . $headingMatches[$i][1][0] . "> -->\n" . $original_content;
			}
		}
		
		if($include_toc){
			//Open new item
			$toc .= apply_filters('sectionize_start_toc_item', '<li>', $level);
			
			// Link to the section
			$toc .= apply_filters('sectionize_toc_link', (
				"<a href='#" . esc_attr($id) . "'>" .
				apply_filters('sectionize_toc_text', $headingMatches[$i][2][0], $level) .
				"</a>")
			, $level);
		}
		
		//Start new section
		$_start_section = apply_filters('sectionize_start_section', str_replace('%id', esc_attr($id), $start_section));
		$content = substr_replace($content, $_start_section, $headingMatches[$i][0][1]+$offset, 0);
		$offset += strlen($_start_section);
	}
	while($level >= 0){
		if($include_toc)
			$toc .= apply_filters('sectionize_end_toc_item', '</li>')
			      . apply_filters('sectionize_end_toc_list', "</ol>");
		$level--;
		
		//End Section
		$content .= $end_section;
	}
	
	return $include_toc ?
		$toc . $after_toc . $content :
		$content;
}
add_filter('the_content', 'sectionize');


/**
 * Helper which returns postmeta[$name] if it exists, otherwise get the option
 */
function _sectionize_get_postmeta_or_option($name){
	global $post;
	if(!empty($post) && $post->ID){
		global $wp_query;
		$value = get_post_meta($post->ID, $name, true);
		if($value !== '')
			return $value;
	}
	return get_option($name);
}

/**
 * Default sectionized TOC item link text filter which does strip_tags
 * and trims colon off of end.
 */
function sectionize_toc_text_default_filter($text){
	return trim(rtrim(strip_tags($text), ':'));
}
add_filter('sectionize_toc_text', 'sectionize_toc_text_default_filter');
