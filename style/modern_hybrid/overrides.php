<?php /* STYLE/MODERN_HYBRID - Overrides */
if (!defined('DP_BASE_DIR')) {
    die('You should not access this file directly');
}

/**
 * CTitleBlock - Required class for module title blocks
 */
class CTitleBlock extends CTitleBlock_core
{
}

/**
 * CTabBox - Tab container with modern styling
 */
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
            // Remove legacy table tags from extra content if present
            $extraClean = preg_replace('/<t[d|r][^>]*>/i', '', $extra);
            $extraClean = str_ireplace(array('</td>', '</tr>'), '', $extraClean);
            echo '<li class="nav-item ml-auto flex items-center" style="margin-left: auto;">' . $extraClean . '</li>';
        }

        echo '</ul>';

        // Tab Content
        echo '<div class="tab-content">';

        // Flat view logic
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

/**
 * Modern Hybrid Theme - Tab Renderer
 * 
 * Esta função substitui completamente a renderização de abas do tema legado.
 * Usa Tailwind CSS para layout moderno.
 */
function style_render_tabs($tabBox)
{
    global $AppUI, $currentTabId, $currentTabName;

    $s = '';

    // Tab Navigation Header
    $s .= '<div class="border-b border-gray-200 mb-6">';
    $s .= '<nav class="-mb-px flex space-x-8 overflow-x-auto" aria-label="Tabs">';

    foreach ($tabBox->tabs as $k => $v) {
        $isActive = ($k == $tabBox->active);
        $class = $isActive
            ? 'border-blue-500 text-blue-600'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';

        $s .= '<a href="' . $tabBox->baseHRef . 'tab=' . $k . '" class="' . $class . ' whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm no-underline">';
        $s .= htmlspecialchars($AppUI->_($v[1]));
        $s .= '</a>';
    }

    $s .= '</nav>';
    $s .= '</div>';

    // Output the navigation first
    echo $s;

    // Now output the tab content with proper globals set
    echo '<div class="tab-content">';

    if (isset($tabBox->tabs[$tabBox->active])) {
        $activeTab = $tabBox->tabs[$tabBox->active];
        $file = $tabBox->baseInc . $activeTab[0] . '.php';

        // Set globals that tab content files depend on
        $currentTabId = $tabBox->active;
        $currentTabName = $activeTab[1];

        if (file_exists($file)) {
            include $file;
        } else {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">';
            echo 'Tab file not found: ' . htmlspecialchars($file);
            echo '</div>';
        }
    }

    echo '</div>';

    // Return empty string since we already echoed everything
    return '';
}
?>