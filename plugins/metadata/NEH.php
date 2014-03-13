<style type="text/css">
#neh {line-height: 200%;}
#slider-bg {background:url('/images/bg-fader.gif') 5px 0 no-repeat;}
#slider-thumb {left: 0;}
</style>

<div id="neh">
	<table cellspacing="0" cellpadding="5" style="line-height:1.6" width="100%">
		<tr>
			<td valign="top">
				<strong>Image Type:</strong><br>
				<input type="checkbox" name="neh_type_i" id="neh_type_i" value="Illustration" onChange="YAHOO.macaw.NEH.metadataChange(this);">&nbsp;Illustration&nbsp;(I)<br>
				<input type="checkbox" name="neh_type_d" id="neh_type_d" value="Diagram/Chart" onChange="YAHOO.macaw.NEH.metadataChange(this);">&nbsp;Diagram/Chart&nbsp;(D)<br>
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
				<a href="#" onClick="General.openHelpNEH();return false;"><strong>Help?</strong></a>
			</td>
<!-- 
			<td valign="top">
				<div style="padding-left: 20px">
					<strong>Filters:</strong> <button onClick="YAHOO.macaw.NEH.resetFilter()">Reset</button> <br>
					Image Type:<br>
					<input id="page_type_1" type="checkbox" name="photograph" value="photograph" onChange="YAHOO.macaw.NEH.filterPages();">&nbsp;Photographs<br>
					<input id="page_type_2" type="checkbox" name="drawing" value="drawing" onChange="YAHOO.macaw.NEH.filterPages();">&nbsp;Drawings/Paintings<br>
					<input id="page_type_3" type="checkbox" name="print" value="print" onChange="YAHOO.macaw.NEH.filterPages();">&nbsp;Prints<br>
					<input id="page_type_4" type="checkbox" name="diagram" value="diagram" onChange="YAHOO.macaw.NEH.filterPages();">&nbsp;Diagrams<br>
					<input id="page_type_5" type="checkbox" name="map" value="map" onChange="YAHOO.macaw.NEH.filterPages();">&nbsp;Maps<br>
				</div>
			</td>
			<td valign="top">
				<div style="padding-left: 20px; width: 130px;">
					<br>
					Color Depth:<br>
					<input id="color_1" type="checkbox" name="color" value="color" onChange="YAHOO.macaw.NEH.filterPages();"> Color<br>
					<input id="color_2" type="checkbox" name="monochrome" value="monochrome" onChange="YAHOO.macaw.NEH.filterPages();"> Monochrome<br>
					Page Coverage: <span id="size-val">0%</span>
					<div id="slider-bg" class="yui-h-slider" tabindex="-1" title="Slider">
							<div id="slider-thumb" class="yui-slider-thumb"><img src="/images/thumb-n.gif"></div>
					</div>
				</div>
			</td>
			<td valign="top">
				<div style="padding-left: 20px">
					<strong>Sort by:</strong> <br>
					<input id="sort_1" type="radio" name="sort" value="natural" checked onChange="YAHOO.macaw.NEH.filterPages();"> Natural Order<br>
					<input id="sort_2" type="radio" name="sort" value="size" onChange="YAHOO.macaw.NEH.filterPages();"> Size<br>
					<input id="sort_3" type="radio" name="sort" value="size_reversed" onChange="YAHOO.macaw.NEH.filterPages();"> Size (reversed)<br>
				</div>
			</td>
 -->
		</tr>
	</table>
</div>