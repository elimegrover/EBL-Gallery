<?php 

if (@txpinterface == 'admin')
    {
		add_privs('ebl_galleryAlbums', '1');  
        register_tab('extensions', 'ebl_galleryAlbums', 'EBL Gallery');
        register_callback('ebl_gallery_main', 'ebl_galleryAlbums');
    }

    register_callback('ebl_imgcommentsaved','comment.saved'); /* Use Hidden Input Field 	*/

function ebl_url_stuff() {
	global $prefs;
 
 	// Get Url Components
 	$urlsegment = chopurl(trim($_SERVER['REQUEST_URI'], "\/"));
 	
 	// Determine URL mode and setup variables
	switch ($prefs['permlink_mode']) {
	    case 'messy':
			$out['messy']	= TRUE;
			$out['image']	= gps('image');
			break;		
	    case 'id_title':
			$out['album']	= $urlsegment['u1'];
			$out['image']	= $urlsegment['u2'];
	        break;
	    case 'section_id_title':
			$out['album']	= $urlsegment['u2'];
			$out['image']	= $urlsegment['u3'];
	        break;
	    case 'year_month_day_title':
			$out['album']	= $urlsegment['u3'];
			$out['image']	= $urlsegment['u4'];
	        break;
	    case 'section_title':
			$out['album']	= $urlsegment['u1'];
			$out['image']	= $urlsegment['u2'];
			break;
	    case 'title_only':
			$out['album']	= $urlsegment['u0'];
			$out['image']	= $urlsegment['u1'];
	        break;
	}
	
	return $out;
}

function ebl_gallery_main() {

		$event 	= gps('event');
		$step	= gps('step');
		
		/* Pagetop */
	    $out = pagetop("EBL Image Edit Preferences", '');

		/* Content */
		if($event == "ebl_galleryAlbums" && $step == '') 
			{ ebl_list_albums(); }
		if($event == "ebl_galleryAlbums" && $step == 'edit') 
			{ echo ebl_gallery_albumEdit(); }
		if($event == "ebl_galleryAlbums" && $step == 'ebl_saveAlbumSetting') 
			{ echo ebl_gallery_albumEdit('save'); }		
		return $out;
}

function ebl_list_albums() {

		global $step, $txp_user, $article_list_pageby, $event;

		extract(gpsa(array('page', 'sort', 'dir', 'crit', 'search_method')));
		if ($sort === '') $sort = get_pref('article_sort_column', 'posted');
		if ($dir === '') $dir = get_pref('article_sort_dir', 'desc');
		$dir = ($dir == 'asc') ? 'asc' : 'desc';


		switch ($sort)
		{
			case 'id':
				$sort_sql = 'ID '.$dir;
			break;

			case 'title':
				$sort_sql = 'Title '.$dir.', Posted desc';
			break;

			case 'category1':
				$sort_sql = 'Category1 '.$dir.', Posted desc';
			break;

			case 'category2':
				$sort_sql = 'Category2 '.$dir.', Posted desc';
			break;

			case 'comments':
				$sort_sql = 'comments_count '.$dir.', Posted desc';
			break;

			default:
				$sort = 'posted';
				$sort_sql = 'Posted '.$dir;
			break;
		}

		set_pref('article_sort_column', $sort, 'list', 2, '', 0, PREF_PRIVATE);
		set_pref('article_sort_dir', $dir, 'list', 2, '', 0, PREF_PRIVATE);

		$switch_dir = ($dir == 'desc') ? 'asc' : 'desc';

		$criteria = "`Section` =  'gallery'";

		$total = safe_count('textpattern', "$criteria");

		echo '<div id="'.$event.'_control" class="txp-control-panel">';

		if ($total < 1)
		{
			echo graf(gTxt('no_articles_recorded'), ' class="indicator"').'</div>';
			return;
		}

		$limit = max($article_list_pageby, 15);

		list($page, $offset, $numPages) = pager($total, $limit, $page);

		$rs = safe_rows_start('*, unix_timestamp(Posted) as posted, unix_timestamp(LastMod) as lastmod, unix_timestamp(Expires) as expires', 'textpattern',
			"$criteria order by $sort_sql limit $offset, $limit"
		);

		if ($rs)
		{

			$total_comments = array();

			$rs2 = safe_rows_start('parentid, count(*) as num', 'txp_discuss', "1 group by parentid order by parentid");

			if ($rs2)
			{
				while ($a = nextRow($rs2))
				{
					$pid = $a['parentid'];
					$num = $a['num'];

					$total_comments[$pid] = $num;
				}
			}

			echo n.'<div id="'.$event.'_container" class="txp-container txp-list">';
			echo n.n.'<form name="longform" id="articles_form" method="post" action="index.php" onsubmit="return verify(\''.gTxt('are_you_sure').'\')">'.

				n.startTable('list','','list','','500px').
				n.'<thead>'.
				n.tr(
					n.column_head('ID', 'id', 'ebl_galleryAlbums', true, $switch_dir, $crit, $search_method, (('id' == $sort) ? "$dir " : '').'id actions').
					column_head('posted', 'posted', 'ebl_galleryAlbums', true, $switch_dir, $crit, $search_method, (('posted' == $sort) ? "$dir " : '').'date posted created').
					column_head('title', 'title', 'ebl_galleryAlbums', true, $switch_dir, $crit, $search_method, (('title' == $sort) ? "$dir " : '').'title').
					column_head('category1', 'category1', 'ebl_galleryAlbums', true, $switch_dir, $crit, $search_method, (('category1' == $sort) ? "$dir " : '').'articles_detail category category1').
					column_head('category2', 'category2', 'ebl_galleryAlbums', true, $switch_dir, $crit, $search_method, (('category2' == $sort) ? "$dir " : '').'articles_detail category category2').
					column_head('comments', 'comments', 'ebl_galleryAlbums', true, $switch_dir, $crit, $search_method, (('comments' == $sort) ? "$dir " : '').'articles_detail comments')
				).
				n.'</thead>';

			include_once txpath.'/publish/taghandlers.php';

			$tfoot = n.'<tfoot>'.tr(
				tda(
					toggle_box('articles_detail'),
					' class="detail-toggle" colspan="2" style="text-align: left; border: none;"'
				));

			echo $tfoot;
			echo '<tbody>';

			$ctr = 1;

			while ($a = nextRow($rs))
			{
				extract($a);

				if (empty($Title))
				{
					$Title = '<em>'.eLink('ebl_galleryAlbums', 'edit', 'ID', $ID, gTxt('untitled')).'</em>';
				}

				else
				{
					$Title = eLink('ebl_galleryAlbums', 'edit', 'ID', $ID, $Title);
				}

				$Category1 = ($Category1) ? '<span title="'.htmlspecialchars(fetch_category_title($Category1)).'">'.$Category1.'</span>' : '';
				$Category2 = ($Category2) ? '<span title="'.htmlspecialchars(fetch_category_title($Category2)).'">'.$Category2.'</span>' : '';

				$view_url = permlinkurl($a);

				$manage = n.'<ul class="articles_detail actions">'.
						n.t.'<li class="action-edit">'.eLink('article', 'edit', 'ID', $ID, gTxt('edit')).'</li>'.
						n.t.'<li class="action-view"><a href="'.$view_url.'" class="article-view">'.gTxt('view').'</a></li>'.
						n.'</ul>';

				$comments = gTxt('none');

				if (isset($total_comments[$ID]) and $total_comments[$ID] > 0)
				{
					$comments = href(gTxt('manage'), 'index.php?event=discuss'.a.'step=list'.a.'search_method=parent'.a.'crit='.$ID).
						' ('.$total_comments[$ID].')';
				}

				$comment_status = ($Annotate) ? gTxt('on') : gTxt('off');

				$comments = n.'<ul>'.
					n.t.'<li class="comments-status">'.$comment_status.'</li>'.
					n.t.'<li class="comments-manage">'.$comments.'</li>'.
					n.'</ul>';

				echo n.n.tr(

					n.td(eLink('article', 'edit', 'ID', $ID, $ID).$manage, '', 'id').

					td(
						gTime($posted), '', ($posted < time() ? '' : 'unpublished ').'date posted created'
					).

					td($Title, '', 'title').

					td($Category1, 100, "articles_detail category category1").
					td($Category2, 100, "articles_detail category category2").

					td($comments, 50, "articles_detail comments")
				);

				$ctr++;
			}

			echo '</tbody>'.
			n.endTable().
			n.'</form>'.

			n.'</div>'.n.'</div>';
		}
}

function ebl_gallery_albumEdit($doWhat ='') {

			$ID = assert_int(gps('ID'));
			

			if($doWhat == 'save') {
				$imageCategory = GPS('imageCategory');
				
				$updateGallery = ebl_upsert('ebl_gallery', "`ebl_album` = '".$ID."', `ebl_category` = '".$imageCategory."'","ebl_album = '".$ID."'");

				echo mysql_error();
			}

			$rs      = safe_row("*","textpattern","ID=$ID"); /* Get Article */
			$rsImage = safe_rows_start('*', "txp_category", "`type` =  'image'"); /* Get Image Categories */
			$rsAlbum = safe_row("*","ebl_gallery","ebl_album=$ID");

			extract($rsAlbum);
			
			$option = '<option value="0">None</option>';
$selected = '';			
			if ($rsImage) {
				while ($a = nextRow($rsImage))
				{
					extract($a);
					$selected = ($name == $ebl_category) ? 'selected="selected"' : '';
					$option	.= n.'<option value="'.$name.'" '.$selected.'>'.$title.'</option>'; 
				}
			}

			if(strlen($ebl_category) > 0 && $ebl_category != '0') {
					$totalImgNum = safe_count('txp_image','category="'.$ebl_category.'"');
					$totalImages = '&nbsp;| '.$totalImgNum.' Total Album Images';
				} 
				else {
					$totalImages = "&nbsp;|&nbsp;No Category Selected";
			}
			
			$selectCategory = '<select name="imageCategory">'.n.$option.n."</select>";
			
			extract($rs);
			
			$form =  n.startTable('list','','list','','500px').
					tr(
						td('<b>ID</b>').
						td($ID)
					).
					tr(
						td('<b>Title<b>').
						td($Title)
					).
					tr(
						td('<b>Text<b>').
						td($Body_html)
					).
					tr(
						td('Use Image Category').
						td($selectCategory.$totalImages)
					).
					tr(
						td(gtxt('Submit Changes')).
						td(
				            n.eInput('ebl_galleryAlbums').
				            n.sInput('ebl_saveAlbumSetting').
				            n.hInput('ID',$ID).					
							n.fInput('submit', 'add', gTxt('save'), 'smallerbox')
						)
					).
				    n.endTable();

			$out = form($form);
			
	return $out;
}

function ebl_galleryview() {
	global $pretext, $prefs, $thispage, $thisarticle;
	
	extract($pretext);
	extract($prefs);
	/* Arm function for image comments */
	register_callback('ebl_imgcommentinfo','comment.form'); /* Insert hidden Input 		*/
	

	/* Do the URL Stuff */
	$urlsegment = ebl_url_stuff();
	$image		= $urlsegment['image'];
	$messy		= $urlsegment['messy'];
	
	/** Get Album Info **/
	$albumID = doSlash($id);
	$rsAlbum = safe_row("*","ebl_gallery","ebl_album=$albumID");
	extract($rsAlbum);
	
	if(strlen($image) > 0)
		{
			/** Show Individual Image **/
			$out = 'individual image';

			/** Stop any injection shenanigans **/
			if (preg_match('=^[^/?*;:{}\\\\]+\.[^/?*;:{}\\\\]+$=', $image) AND preg_match('/(?i)\.(jpg|png|gif)$/i', $image)) {

				$rsImage = safe_row('*', 'txp_image',"`name` =  '".doSlash($image)."' AND `category` =  '".$ebl_category."' ");

				if($rsImage) {

					extract($rsImage);
					$imgUrl = 'http://'.$siteurl.'/'.$img_dir.'/'.$id.$ext;
					$GLOBALS['ebl_imgId'] = $id;
					$GLOBALS['ebl_category'] = $ebl_category;
					$GLOBALS['ebl_main_image'] = $imgUrl;

					$out = parse_form('ebl_single_image');

				} else {
					$out = "Invalid Image";
				}
			} else {
			    return "Invalid Image";
			}
		} else {
			/** Show Album Thumbnails **/
			if(strlen($id) > 0)
				{
					$out = '';

					$rsAlbumImages = safe_rows_start('*', "txp_image", "`category` =  '".$ebl_category."'"); /* Get Images */
					if ($rsAlbumImages) {
						while ($a = nextRow($rsAlbumImages))
						{
							extract($a);

							$imgUrl  = 'http://'.$siteurl.'/'.$img_dir.'/'.$id.'t'.$ext;

							$out 	.= ($messy) 
									? '<a href="'.$request_uri.'&image='.$name.'"><img src="'.$imgUrl.'" /></a>' 
									: '<a href="'.$request_uri.'/'.$name.'"><img src="'.$imgUrl.'" /></a>'; 
						}
					}
				}			
		}

		
		return $out;
}

function ebl_imgname() 		{}
function ebl_imginfo()		{}

function ebl_imgcommentinfo() {
	
	/* Do the URL Stuff */
	$urlsegment = ebl_url_stuff();
	$image		= $urlsegment['image'];
	
	return n.'<input type="hidden" name="eblImageIdForComment" value="'.$image.'" />'.n;
}

function ebl_imgcommentsaved() {
	
		$argv					= func_get_args();
		$eblImageIdForComment	= gps('eblImageIdForComment');
		extract($argv[2]);
		
		safe_insert('ebl_gallery_comments',"imagename = '$eblImageIdForComment', commentid='$commentid'");

}

function ebl_showimagecomment($atts) {
	global $thisarticle, $prefs;

	/* Do the URL Stuff */
	$urlsegment = ebl_url_stuff();
	$album		= $urlsegment['album'];
	$image		= $urlsegment['image'];

		extract($prefs);

		extract(lAtts(array(
			'form'       => 'comments',
			'wraptag'    => ($comments_are_ol ? 'ol' : ''),
			'break'      => ($comments_are_ol ? 'li' : 'div'),
			'class'      => __FUNCTION__,
			'breakclass' => '',
			'limit'      => 0,
			'offset'     => 0,
			'sort'       => 'posted ASC',
		),$atts));

		assert_article();

		extract($thisarticle);

		if (!$comments_count) return '';

		$qparts = array(
			'parentid='.intval($thisid).' and visible='.VISIBLE,
			'and commentid=discussid',
			"and imagename='".doSlash($image)."'",
			'order by '.doSlash($sort),
			($limit) ? 'limit '.intval($offset).', '.intval($limit) : ''
		);

		$rs = safe_rows_start('*, unix_timestamp(posted) as time', 'txp_discuss,ebl_gallery_comments', join(' ', $qparts));

		$out = '';

		if ($rs) {
			$comments = array();

			while($vars = nextRow($rs)) {
				$GLOBALS['thiscomment'] = $vars;
				$comments[] = parse_form($form).n;
				unset($GLOBALS['thiscomment']);
			}

			$out .= doWrap($comments,$wraptag,$break,$class,$breakclass);
		}

		return $out;
				
}

function ebl_singleimage() {
	global $ebl_main_image;

	return '<img src="'.$ebl_main_image.'" />';
}

function ebl_returntoalbum(){
	
}

function ebl_previmage()	{
	global $ebl_category, $ebl_imgId;
	
	extract(safe_row('*', 'txp_image', "`category` =  '".$ebl_category."' AND `id` <'".$ebl_imgId."' ORDER BY id DESC"));

	return (strlen($name) > 0) ? '<a href="'.$name.'">Previous</a>' : '';	
}

function ebl_nextimage()	{

	global $ebl_category, $ebl_imgId;
	
	extract(safe_row('*', 'txp_image', "`category` =  '".$ebl_category."' AND `id` >'".$ebl_imgId."' ORDER BY id ASC"));

	return (strlen($name) > 0) ? '<a href="'.$name.'">Next</a>' : '';
}



function ebl_upsert($table,$set,$where,$debug='')
   {
       // Rewritten, because the other was broken.
       $r = safe_update($table, $set, $where, $debug);
       if ($r and (mysql_affected_rows() or safe_count($table, $where, $debug)))
           return $r;
       else
           return safe_insert($table, $set, $debug); // Obviously, where won't apply here, so why include it?
   }
?>