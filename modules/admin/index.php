<?php /* $Id$ */
if (!defined('DP_BASE_DIR')) {
	die('You should not access this file directly.');
}

if (!(getPermission($m, 'view'))) {
	$AppUI->redirect('m=public&a=access_denied');
}
if (!(getPermission('users', 'view'))) {
	$AppUI->redirect('m=public&a=access_denied');
}

$AppUI->savePlace();

if (isset($_GET['tab'])) {
	$AppUI->setState('UserIdxTab', $_GET['tab']);
}
$tab = (($AppUI->getState('UserIdxTab') !== NULL) ? $AppUI->getState('UserIdxTab') : 0);

if (isset($_GET['stub'])) {
	$AppUI->setState('UserIdxStub', $_GET['stub']);
	$AppUI->setState('UserIdxWhere', '');
} else if (isset($_POST['where'])) {
	$AppUI->setState('UserIdxWhere', $_POST['where']);
	$AppUI->setState('UserIdxStub', '');
}
$stub = $AppUI->getState('UserIdxStub');
$where = $AppUI->getState('UserIdxWhere');

$valid_ordering = array(
	'user_username',
	'contact_last_name',
	'contact_company',
	'date_time_in',
	'user_ip',
);
if (isset($_GET['orderby']) && in_array($_GET['orderby'], $valid_ordering)) {
	$AppUI->setState('UserIdxOrderby', $_GET['orderby']);
}
$orderby = (($AppUI->getState('UserIdxOrderby')) ? $AppUI->getState('UserIdxOrderby')
	: 'user_username');
$orderby = (($tab == 3 || ($orderby != 'date_time_in' && $orderby != 'user_ip'))
	? $orderby : 'user_username');

$q = new DBQuery;

// Pull First Letters
$let = ":";

$q->addTable('users', 'u');
$q->addJoin('contacts', 'con', 'con.contact_id = u.user_contact');
$q->addQuery('DISTINCT UPPER(SUBSTRING(u.user_username, 1, 1)) AS L'
	. ', UPPER(SUBSTRING(con.contact_first_name, 1, 1)) AS CF'
	. ', UPPER(SUBSTRING(con.contact_last_name, 1, 1)) AS CL');
$arr = $q->loadList();
foreach ($arr as $L) {
	foreach ($L as $v) {
		if (empty($v)) {
			continue;
		}
		if (empty($let)) {
			$let .= $v;
		} else {
			$let .= (mb_strpos($let, $v) === false) ? $v : '';
		}
	}
}
$q->clear();

// Modern Filter Construction
$a2z = '
<form action="index.php" method="get" name="filterFrm">
    <input type="hidden" name="m" value="admin" />
    <input type="hidden" name="a" value="index" />
    <select name="stub" onchange="document.filterFrm.submit()" class="text" style="min-width: 150px;">
        <option value="0">' . $AppUI->_('All Users') . '</option>';

for ($c = 65; $c < 91; $c++) {
	$cu = chr($c);
	if (mb_strpos($let, $cu) > 0) {
		$selected = ($stub == $cu) ? 'selected="selected"' : '';
		$a2z .= '<option value="' . $cu . '" ' . $selected . '>' . $cu . '</option>';
	}
}
$a2z .= '</select></form>';

// setup the title block
$titleBlock = new CTitleBlock('User Management', 'helix-setup-users.png', $m, "$m.$a");

$where = dPformSafe($where);

// Combined search and filter
$searchCell = '
<form action="index.php?m=admin" method="post" class="flex items-center gap-2">
    <div class="flex items-center gap-2">
        <label>' . $AppUI->_('Search') . ':</label>
        <input autofocus type="search" name="where" class="text" size="20" value="' . $where . '" />
    </div>
    <div class="flex items-center gap-2 ml-4">
        <label>' . $AppUI->_('Filter') . ':</label>
        ' . $a2z . '
    </div>
    <input type="submit" value="' . $AppUI->_('Go') . '" class="button btn-primary" />
</form>';

$titleBlock->addCell($searchCell, '', '', '');
$titleBlock->show();

?>
<script language="javascript">
	<?php
	// security improvement:
// some javascript functions may not appear on client side in case of user not having write permissions
// else users would be able to arbitrarily run 'bad' functions
	if ($canDelete) {
		?>
		function delMe(x, y) {
			if (confirm("<?php echo $AppUI->_('doDelete', UI_OUTPUT_JS) . ' ' . $AppUI->_('User', UI_OUTPUT_JS); ?> " + y + "?")) {
				document.frmDelete.user_id.value = x;
				document.frmDelete.submit();
			}
		}
	<?php } ?>
</script>

<?php
$extra = '<td align="right" width="100%"><input type="button" class=button value="' . $AppUI->_('add user') . '" onclick="javascript:window.location=\'./index.php?m=admin&amp;a=addedituser\';" /></td>';

// tabbed information boxes
$tabBox = new CTabBox('?m=admin', (DP_BASE_DIR . '/modules/admin/'), $tab);
$tabBox->add('vw_active_usr', 'Active Users');
$tabBox->add('vw_inactive_usr', 'Inactive Users');
$tabBox->add('vw_usr_log', 'User Log');
if ($canEdit && $canDelete) {
	$tabBox->add('vw_usr_sessions', 'Active Sessions');
}
$tabBox->show($extra);

?>

<form name="frmDelete" action="./index.php?m=admin" method="post">
	<input type="hidden" name="dosql" value="do_user_aed" />
	<input type="hidden" name="del" value="1" />
	<input type="hidden" name="user_id" value="0" />
</form>