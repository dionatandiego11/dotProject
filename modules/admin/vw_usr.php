<?php /* ADMIN  $Id$ */
if (!defined('DP_BASE_DIR')) {
	die('You should not access this file directly.');
}

?>
<table cellpadding="2" cellspacing="1" border="0" width="100%" class="tbl">
	<tr>
		<td width="60" align="right">
			&nbsp; <?php echo $AppUI->_('sort by'); ?>:&nbsp;
		</td>
		<?php if ((int) dPgetParam($_GET, 'tab', 0) == 0) { ?>
			<th width="125">
				<?php echo $AppUI->_('Login History'); ?>
			</th>
		<?php } ?>
		<th width="150">
			<a href="?m=admin&amp;a=index&amp;orderby=user_username"
				class="hdr"><?php echo $AppUI->_('Login Name'); ?></a>
		</th>
		<th>
			<a href="?m=admin&amp;a=index&amp;orderby=contact_last_name"
				class="hdr"><?php echo $AppUI->_('Real Name'); ?></a>
		</th>
		<th>
			<a href="?m=admin&amp;a=index&amp;orderby=contact_company"
				class="hdr"><?php echo $AppUI->_('Company'); ?></a>
		</th>
	</tr>
	<?php

	$perms =& $AppUI->acl();
	foreach ($users as $row) {
		if ($perms->checkLogin($row['user_id']) != $canLogin) {
			continue;
		}
		?>
		<tr>
			<td align="right" nowrap="nowrap">
				<?php
				if ($canEdit) { ?>
					<table cellspacing="0" cellpadding="0" border="0">
						<tr>
							<td>
								<a href="./index.php?m=admin&amp;a=addedituser&amp;user_id=<?php echo $row['user_id']; ?>"
									title="<?php echo $AppUI->_('edit'); ?>" class="text-primary-600 hover:text-primary-800">
									<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
										fill="currentColor">
										<path
											d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
									</svg>
								</a>
							</td>
							<td>
								<a href="?m=admin&amp;a=viewuser&amp;user_id=<?php echo $row['user_id']; ?>&amp;tab=3"
									title="<?php echo $AppUI->_('edit permissions'); ?>"
									class="text-yellow-600 hover:text-yellow-800">
									<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
										fill="currentColor">
										<path fill-rule="evenodd"
											d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
											clip-rule="evenodd" />
									</svg>
								</a>
							</td>
							<td>
								<?php
								$user_display = addslashes($row['contact_first_name'] . ' ' . $row['contact_last_name']);
								$user_display = trim($user_display);
								if (empty($user_display)) {
									$user_display = $row['user_username'];
								}
								?>
								<a href="javascript:delMe(<?php echo $row['user_id']; ?>, '<?php echo $user_display; ?>')"
									title="<?php echo $AppUI->_('delete'); ?>" class="text-red-600 hover:text-red-800">
									<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
										fill="currentColor">
										<path fill-rule="evenodd"
											d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
											clip-rule="evenodd" />
									</svg>
								</a>
							</td>
						</tr>
					</table>
				<?php } ?>
			</td>
			<?php
			if ((int) dPgetParam($_REQUEST, 'tab', 0) == 0) { ?>
				<td>
					<?php
					$q = new DBQuery;
					$q->addTable('user_access_log', 'ual');
					$q->addQuery('user_access_log_id,'
						. ' (unix_timestamp(now()) - unix_timestamp(date_time_in))/3600 as hours,'
						. ' (unix_timestamp(now()) - unix_timestamp(date_time_last_action))/3600'
						. ' as idle, if (isnull(date_time_out)'
						. " or date_time_out ='0000-00-00 00:00:00','1','0') as online");
					$q->addWhere('user_id =' . $row['user_id']);
					$q->addOrder('user_access_log_id DESC');
					$q->setLimit(1);
					$user_logs = $q->loadList();

					if ($user_logs) {
						foreach ($user_logs as $row_log) {
							if ($row_log["online"] == '1') {
								echo ('<span class="badge badge-success">' . round($row_log['idle'], 2) . ' '
									. $AppUI->_('hrs. idle') . '</span>');
							} else {
								echo '<span class="text-muted text-sm">' . $AppUI->_('Offline') . '</span>';
							}
						}
					} else {
						echo '<span class="text-muted text-sm">' . $AppUI->_('Never Visited') . '</span>';
					}
			}
			?>
			</td>
			<td>
				<a href="?m=admin&amp;a=viewuser&amp;user_id=<?php echo $row['user_id']; ?>"
					class="font-medium text-primary-600 hover:text-primary-800"><?php
					echo $row['user_username']; ?></a>
			</td>
			<td>
				<a href="mailto:<?php echo $row['contact_email']; ?>" title="<?php echo $row['contact_email']; ?>"
					class="text-gray-500 hover:text-primary-600">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" viewBox="0 0 20 20" fill="currentColor">
						<path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
						<path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
					</svg>
				</a>
				<?php
				if ($row['contact_last_name'] && $row['contact_first_name']) {
					echo $row['contact_last_name'] . ', ' . $row['contact_first_name'];
				} else {
					echo '<span style="font-style: italic">unknown</span>';
				}
				?>
			</td>
			<td>
				<a
					href="?m=companies&amp;a=view&amp;company_id=<?php echo $row['contact_company']; ?>"><?php echo $row['company_name']; ?></a>
			</td>
		</tr>
		<?php
	}
	?>

</table>