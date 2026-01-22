<?php /* STYLE/MODERN - Style Overrides */
if (!defined('DP_BASE_DIR')) {
    die('You should not access this file directly');
}

/**
 * Modern Theme Overrides
 * 
 * This file contains PHP-based style overrides and custom functions
 * for the modern theme.
 */

// Define custom color scheme constants
if (!defined('MODERN_PRIMARY')) {
    define('MODERN_PRIMARY', '#3b82f6');
    define('MODERN_PRIMARY_DARK', '#1d4ed8');
    define('MODERN_SUCCESS', '#10b981');
    define('MODERN_WARNING', '#f59e0b');
    define('MODERN_DANGER', '#ef4444');
}

/**
 * Get a CSS class based on task status
 */
function getModernTaskClass($percentComplete, $startDate, $endDate)
{
    $now = time();
    $start = strtotime($startDate);
    $end = strtotime($endDate);

    if ($percentComplete >= 100) {
        return 'badge badge-success';
    } elseif ($now > $end) {
        return 'badge badge-danger';
    } elseif ($now > $start && $percentComplete < 100) {
        return 'badge badge-warning';
    } else {
        return 'badge badge-info';
    }
}

/**
 * Get status text for a task
 */
function getModernStatusText($percentComplete, $startDate, $endDate)
{
    global $AppUI;
    $now = time();
    $start = strtotime($startDate);
    $end = strtotime($endDate);

    if ($percentComplete >= 100) {
        return $AppUI->_('Complete');
    } elseif ($now > $end) {
        return $AppUI->_('Overdue');
    } elseif ($now > $start && $percentComplete < 100) {
        return $AppUI->_('In Progress');
    } elseif ($now < $start) {
        return $AppUI->_('Not Started');
    } else {
        return $AppUI->_('Active');
    }
}

/**
 * Get priority badge class
 */
function getModernPriorityClass($priority)
{
    switch ($priority) {
        case 1:
        case 'high':
            return 'badge badge-danger';
        case -1:
        case 'low':
            return 'badge badge-info';
        default:
            return 'badge badge-secondary';
    }
}

/**
 * Generate a modern progress bar HTML
 */
function getModernProgressBar($percent, $showLabel = true)
{
    $percent = max(0, min(100, (int) $percent));
    $colorClass = '';

    if ($percent >= 100) {
        $colorClass = 'background: linear-gradient(90deg, #10b981 0%, #059669 100%);';
    } elseif ($percent >= 75) {
        $colorClass = 'background: linear-gradient(90deg, #06b6d4 0%, #0891b2 100%);';
    } elseif ($percent >= 50) {
        $colorClass = 'background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%);';
    } elseif ($percent >= 25) {
        $colorClass = 'background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);';
    } else {
        $colorClass = 'background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);';
    }

    $html = '<div class="progress" style="height: 8px; border-radius: 4px;">';
    $html .= '<div class="progress-bar" style="width: ' . $percent . '%; ' . $colorClass . ' border-radius: 4px;"></div>';
    $html .= '</div>';

    if ($showLabel) {
        $html .= '<span style="font-size: 0.75rem; color: #6b7280;">' . $percent . '%</span>';
    }

    return $html;
}

/**
 * Generate avatar HTML from name
 */
function getModernAvatar($name, $size = 32)
{
    $initials = '';
    $parts = explode(' ', trim($name));

    if (count($parts) >= 2) {
        $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
    } else {
        $initials = strtoupper(substr($name, 0, 2));
    }

    // Generate a consistent color based on the name
    $colors = [
        '#3b82f6',
        '#8b5cf6',
        '#ec4899',
        '#ef4444',
        '#f97316',
        '#eab308',
        '#22c55e',
        '#14b8a6'
    ];
    $colorIndex = abs(crc32($name)) % count($colors);
    $bgColor = $colors[$colorIndex];

    return '<div style="
		width: ' . $size . 'px;
		height: ' . $size . 'px;
		border-radius: 50%;
		background: ' . $bgColor . ';
		color: white;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		font-weight: 600;
		font-size: ' . ($size * 0.4) . 'px;
	">' . htmlspecialchars($initials) . '</div>';
}

/**
 * Wrap content in a modern card
 */
function wrapInCard($content, $title = '', $icon = '')
{
    $html = '<div class="card" style="margin-bottom: 1rem;">';

    if (!empty($title)) {
        $html .= '<div class="card-header" style="display: flex; align-items: center; gap: 0.5rem;">';
        if (!empty($icon)) {
            $html .= $icon;
        }
        $html .= '<h3 style="margin: 0; font-size: 1rem;">' . htmlspecialchars($title) . '</h3>';
        $html .= '</div>';
    }

    $html .= '<div class="card-body">';
    $html .= $content;
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}
?>