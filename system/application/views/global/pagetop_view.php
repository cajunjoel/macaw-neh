<!-- TODO: Recode this to check the cookie for the book that's selected and set the breadcrumbs appropriately -->
<div id="page_top">



		<div id="enter_barcode">
			<form action="#" onSubmit="Barcode.submit();return false">
			<input type="text" name="barcode" id="txtBarcode" maxlength="32" value="Enter Item ID" onFocus="if (this.value == 'Enter Item ID') {this.value = ''}" <? if ($this->session->userdata('username') == 'demo') { echo('value="39088009903683"'); }  ?> >
			<input type="submit" value="Go">
			</form>
		</div>

	<div id="login_details">
		<a href="#" onClick="User.edit('');"><? echo $this->session->userdata('full_name') ?></a> |
		<a href="<? echo $this->config->item('base_url'); ?>login/logout">Logout</a> |
		<a href="/images/Macaw-Art-of-Life-Help.pdf" target="_blank">Help</a>
	</div>

	<div class="clear"><!-- --></div>
</div>