<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
        "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<title>Macaw</title>
	<? $this->load->view('global/head_view') ?>
</head>
<body class="yui-skin-sam">
	<? $this->load->view('global/header_view') ?>
	<? $this->load->view('global/error_messages_view') ?>

	<div id="item_deleted">
		<h1>Item Deleted</h1>
		<table>
			<tr>
				<td>
					<h3>The item was deleted successfully.</h3>
					<a href="<? echo $path ?>">Download <? echo $filename ?></a>
				</td>
			</tr>
		</table>
	</div>
	<? $this->load->view('global/footer_view') ?>
</body>
</html>
