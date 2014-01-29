<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
        "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<title>Help | Macaw</title>
	<? $this->load->view('global/head_view') ?>
</head>
<body class="yui-skin-sam">
	<h2 style="color:#4F7E97">Macaw Art of Life Metadata Help</h2>
	
	<div style="text-align: left; margin: 20px;">
	<h3 style="color:#4F7E97">Selecting Pages</h3>
	Pages are in <em>multi-select mode</em> by default. Clicking one page and then another will select both pages.
	Use the <strong>None</strong> button at the bottom to unselect all selected images.<br><br>
	
	Holding the <strong>SHIFT</strong> key while clicking will select all pages in a range. Use the buttons at the bottom to clear or change your selections.<br><br>

	Use the Zoom slider at the bottom to see more or fewer pages at a time.<br><br>

	When exactly one page is selected, the preview of the image will be shown on the right.
	
	<h3 style="color:#4F7E97">Entering Metadata</h3>
	When one or more pages are selected, you can enter metadata for the selected pages.<br><br>
	
	<strong>Shortcut!</strong> Type the letter next to the checkbox or radio button to set that value on the selected page(s).<br><br>

	Unselecting a checkbox will remove that value from the selected page(s). To clear the selection from multiple pages, select the pages, check the checkbox, then <strong>uncheck</strong> the checkbox to clear the setting. Once color or black/white have been set, they can't be cleared.<br><br>

	<a href="/help/neh_examples" target="_blank">View some examples</a> of the different page types to help you decide what selections you should be making.
	
	<h3 style="color:#4F7E97">No Images</h3>
	
	If there are no images at all on the page, click the NO IMAGES ON THIS PAGE checkbox. There is no shortcut key for this.
	</div>
	<!-- 
	<a href="<? echo $this->config->item('base_url'); ?>help">&lt;&lt; Help Index</a>
 -->
</body>
</html>
