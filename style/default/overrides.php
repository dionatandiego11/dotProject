<?php /* STYLE/DEFAULT $Id$ */

if (!defined('DP_BASE_DIR')) {
	die('You should not access this file directly');
}
class CTitleBlock extends CTitleBlock_core
{
}

##
##  This overrides the show function of the CTabBox_core function
##
class CTabBox extends CTabBox_core
{
	function show(string $extra = '', bool $js_tabs = false): void
	{
		global $AppUI, $dPconfig, $currentTabId, $currentTabName;
		$uistyle = ($AppUI->getPref('UISTYLE')
			? $AppUI->getPref('UISTYLE')
			: (($dPconfig['host_style']) ? $dPconfig['host_style'] : 'default'));
		reset($this->tabs);

		// MODERN TABS RENDERING
		echo '<div class="modern-tabs">';

		// Tab Navigation
		echo '<ul class="nav nav-tabs">';
		foreach ($this->tabs as $k => $v) {
			$class = ($k == $this->active) ? 'active' : '';
			$link = '';

			if ($this->javascript) {
				$link = 'javascript:' . $this->javascript . '(' . $this->active . ', ' . $k . ')';
			} elseif ($js_tabs) {
				$link = 'javascript:show_tab(' . $k . ')';
			} else {
				$link = $this->baseHRef . 'tab=' . $k;
			}

			echo '<li class="nav-item">';
			echo '<a href="' . $link . '" class="nav-link ' . $class . '">';
			echo ($v[2] ? $v[1] : $AppUI->_($v[1]));
			echo '</a>';
			echo '</li>';
		}

		if ($extra) {
			echo '<li class="nav-item ml-auto" style="margin-left: auto;">' . $extra . '</li>';
		}

		echo '</ul>';

		// Tab Content
		echo '<div class="tab-content">';

		// Flat view logic (kept slightly simpler for now, focus on tabbed)
		if ($this->active < 0 || @$AppUI->getPref('TABVIEW') == 2) {
			foreach ($this->tabs as $k => $v) {
				echo '<div class="flat-tab-section">';
				echo '<h3>' . ($v[2] ? $v[1] : $AppUI->_($v[1])) . '</h3>';
				$currentTabId = $k;
				$currentTabName = $v[1];
				include $this->baseInc . $v[0] . ".php";
				echo '</div><hr/>';
			}
		} else {
			// Tabbed view
			if ($this->baseInc . ($this->tabs[$this->active][0] ?? '') !== '') {
				$currentTabId = $this->active;
				$currentTabName = $this->tabs[$this->active][1] ?? '';
				if (!$js_tabs) {
					require $this->baseInc . $this->tabs[$this->active][0] . '.php';
				}
			}

			if ($js_tabs) {
				foreach ($this->tabs as $k => $v) {
					$display = ($k == $this->active) ? 'block' : 'none';
					echo '<div class="tab" id="tab_' . $k . '" style="display:' . $display . '">';
					require $this->baseInc . $v[0] . '.php';
					echo '</div>';
				}
			}
		}

		echo '</div>'; // .tab-content
		echo '</div>'; // .modern-tabs
	}
}
?>