<style type="text/css">
#neh {line-height: 200%;}
#slider-bg {background:url('/images/bg-fader.gif') 5px 0 no-repeat;}
#slider-thumb {left: 0;}
</style>

<div id="neh">
	
	<table cellspacing="0" cellpadding="5" style="line-height:1.6;width: 100%; margin-top: -10px">
		<tr>
			<td valign="top" style="padding-left: 20px; padding-top: -10px; color: black">
				<strong>Filters:</strong> <button onClick="return YAHOO.macaw.NEH_filter.resetFilter();" >Reset</button> <br>
				Image Type:<br>
				<input id="page_type_1" type="checkbox" name="neh_type_i" value="Illustration" onChange="YAHOO.macaw.NEH_filter.filterPages();">&nbsp;Illustration<br>
				<input id="page_type_2" type="checkbox" name="neh_type_d" value="Diagram/Chart" onChange="YAHOO.macaw.NEH_filter.filterPages();">&nbsp;Diagram/Chart<br>
				<input id="page_type_3" type="checkbox" name="neh_type_m" value="Map" onChange="YAHOO.macaw.NEH_filter.filterPages();">&nbsp;Map<br>
				<input id="page_type_4" type="checkbox" name="neh_type_p" value="Photograph" onChange="YAHOO.macaw.NEH_filter.filterPages();">&nbsp;Photograph<br>
				<input id="page_type_5" type="checkbox" name="neh_type_l" value="Bookplate" onChange="YAHOO.macaw.NEH_filter.filterPages();">&nbsp;Bookplate<br>
			</td>
			<td valign="top" style="width: 130px;color: black">
				<br>
				Color Depth:<br>
				<input id="color_1" type="checkbox" name="color" value="Color" onChange="YAHOO.macaw.NEH_filter.filterPages();"> Color<br>
				<input id="color_2" type="checkbox" name="monochrome" value="Black/White" onChange="YAHOO.macaw.NEH_filter.filterPages();"> Monochrome<br><br>
<!-- 
				Page Coverage: <span id="size-val">0%</span>
				<div id="slider-bg" class="yui-h-slider" tabindex="-1" title="Slider">
					<div id="slider-thumb" class="yui-slider-thumb"><img src="/images/thumb-n.gif"></div>
				</div>
 -->
			</td>
			<td valign="top" style="width: 130px;color: black">
				<br>
				User:<br>
				<select name="user" id="user" onChange="YAHOO.macaw.NEH_filter.filterPages();">					
					<option value="-1">(all users)</option>
					<? foreach ($users as $u) { ?>
					<option value="<? echo $u->id ?>"><? echo $u->full_name ?></option>
					<? } ?>
				</select>
			</td>
			<td valign="top" style="text-align: left;color: black">
				<strong>Sort by:</strong> <br>
				<select name="sort" id="sort" onChange="YAHOO.macaw.NEH_filter.filterPages();">
					<option value="sequence_number=asc">Natural Order</option>
					<option value="sequence_number=desc">Natural Order (rev.)</option>
					<option value="size=asc">Size</option>
					<option value="size=desc">Size (reversed)</option>
				</select>
				<br><br>
				View 
				<select name="perpage" id="perpage" onChange="YAHOO.macaw.NEH_filter.filterPages();">
					<option value="100">100</option>
					<option value="500">500</option>
					<option value="1000">1,000</option>
<!-- 
					<option value="5000">5,000</option>					
					<option value="10000">10,000</option>					
 -->
				</select> per page<br><br>
				<button id="btnPrevPage">&lt; Prev. Page</button>
				<button id="btnNextPage">Next Page &gt;</button>
				<div id="current-page" style="color: #999;text-align:right">Current Page 1</div>
			</td>
		</tr>
	</table>
</div>