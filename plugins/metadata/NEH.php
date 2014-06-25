<style type="text/css">
#neh {line-height: 200%;}
#slider-bg {background:url('/images/bg-fader.gif') 5px 0 no-repeat;}
#slider-thumb {left: 0;}
</style>
<!--
begin;
update metadata set value = 'Painting/Drawing/Diagram' where value = 'Illustration';
update metadata set value = 'Chart/Table' where value = 'Diagram/Chart';
commit;
-->

<div id="neh">
	<table cellspacing="0" cellpadding="5" style="line-height:1.6" width="100%">
		<tr>
			<td valign="top">
				<strong>Image Type:</strong><br>
				<input type="checkbox" name="neh_type_i" id="neh_type_i" value="Painting/Drawing/Diagram" onChange="YAHOO.macaw.NEH.metadataChange(this);">&nbsp;Painting/Drawing/Diagram&nbsp;(I)<br>
				<input type="checkbox" name="neh_type_d" id="neh_type_d" value="Chart/Table" onChange="YAHOO.macaw.NEH.metadataChange(this);">&nbsp;Chart/Table&nbsp;(D)<br>
				<input type="checkbox" name="neh_type_m" id="neh_type_m" value="Map" onChange="YAHOO.macaw.NEH.metadataChange(this);">&nbsp;Map&nbsp;(M)<br>
				<input type="checkbox" name="neh_type_p" id="neh_type_p" value="Photograph" onChange="YAHOO.macaw.NEH.metadataChange(this);">&nbsp;Photograph&nbsp;(P)<br>
				<input type="checkbox" name="neh_type_l" id="neh_type_l" value="Bookplate" onChange="YAHOO.macaw.NEH.metadataChange(this);">&nbsp;Bookplate&nbsp;(L)<br>

			</td>
			<td valign="top">
				<strong>Color Depth:</strong><br>
				<input type="radio" name="neh_color" value="Color" onChange="YAHOO.macaw.NEH.metadataChange(this);">&nbsp;Color&nbsp;(C)<br>
				<input type="radio" name="neh_color" value="Black/White" onChange="YAHOO.macaw.NEH.metadataChange(this);">&nbsp;Black/White&nbsp;(B)<br>
			</td>
			<td valign="top">
				<strong>Misc:</strong><br>
				<input type="checkbox" name="no_images" id="no_images" value="none" onChange="YAHOO.macaw.NEH.metadataChange(this);">&nbsp;NO IMAGES ON THIS PAGE<br>
				<br>				
				<a href="https://archive.org/details/<?php echo $ia_identifier; ?>" style="text-decoration: underline" target="_blank">View Original Item<br>at the Internet Archive</a>
			</td>
			<td valign="top">
				<a href="/images/Macaw-Art-of-Life-Help.pdf" target="_blank"><strong>Help?</strong></a>
			</td>
		</tr>
	</table>
</div>