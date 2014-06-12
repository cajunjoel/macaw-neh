<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Dashboard Controller
 *
 * MACAW Metadata Collection and Workflow System
 *
 * Handles showing, adding, removing widgets. Also handles saving of the
 * displayed widgets to a user's profile.
 *
 **/

class Dashboard extends Controller {

	function Dashboard() {
		parent::Controller();
	}

	/**
	 * Show the dashboard page
	 *
	 * Causes the dashboard page to be displayed. Includes a list of widgets
	 * from the user's preferences. Includes a list of available widgets that
	 * aren't already in the user's preferences.
	 *
	 * @return html The dashboard_view.php page.
	 */
	function index() {
		$this->common->check_session();
		$data['summary'] = $this->_get_summary();
		$data['topusers'] = $this->_get_topusers();
		$this->load->view('dashboard/dashboard_view', $data);
	}

	/**
	 * Get Summary widget data
	 *
	 * AJAX: Handed off from the widget() function, gathers and outputs the data
	 * for the Summary widget
	 *
	 * @access internal
	 * @return json The description and data of the named widget (echoed to browser)
	 *
	 */
	function _get_summary() {
		$row = $this->book->get_status_counts_neh();
		
 		$html = '<div id="summary-widget" class="widget">'.
						'<h3>Summary</h3>'.
						'<div class="inner">'.
						'Image Types identified:<br>'.
						'&nbsp;&nbsp;&nbsp;&nbsp;'.$row->type_illustration.' Paintings/Drawings/Diagrams<br>'.
						'&nbsp;&nbsp;&nbsp;&nbsp;'.$row->type_photo.' Photographs<br>'.
						'&nbsp;&nbsp;&nbsp;&nbsp;'.$row->type_diagram.' Chart/Table<br>'. 
						'&nbsp;&nbsp;&nbsp;&nbsp;'.$row->type_map.' Maps<br>'.
						'&nbsp;&nbsp;&nbsp;&nbsp;'.$row->type_bookplate.' Bookplates<br>'.
						'&nbsp;&nbsp;&nbsp;&nbsp;'.$row->no_images.' With No Images<br>'.
						$row->total_items.' total items / '.$row->total_pages.' total pages<br>'.
						$row->new_items.' new items / '.$row->pages_new_items.' new pages<br>'.
						$row->in_progress.' in progress items / '.$row->pages_in_progress.' in progress pages<br>'.
						$row->completed.' completed items / '.$row->pages_complete.' completed pages<br>'.
						$row->exported.' exported items / '.$row->pages_exported.' exported pages<br>'.
						$row->pct_complete.'% complete overall'.
						'</div>'.
						'</div>';
		return $html;
	}

	/**
	 * Get Summary widget data
	 *
	 * AJAX: Handed off from the widget() function, gathers and outputs the data
	 * for the Top pages by user widget
	 *
	 * @access internal
	 * @return json The description and data of the named widget (echoed to browser)
	 *
	 */
	function _get_topusers() {
		$data = $this->book->get_top_users_neh();		
 		$html = '<div id="topusers-widget" class="widget">'.
 						'<h3>Completed Items by User</h3>'.
						'<div class="inner"><table>';
		$html .= '<thead><tr><th>Name</th><th>Items</th><th>Pages</th></tr></thead>';
		foreach ($data as $r) {
			$html .= '<tr><td>'.$r['full_name'].'</td><td class="numeric">'.$r['items'].'</td><td class="numeric">'.$r['pages'].'</td></tr>';
		}
		$html .='</table></div>'.
 						'</div>';
		return $html;
	}
}
