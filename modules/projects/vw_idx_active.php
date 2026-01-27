<?php /* PROJECTS $Id$ */
if (!defined('DP_BASE_DIR')) {
	die('You should not access this file directly.');
}

global $AppUI, $projects, $company_id, $pstatus, $project_types, $currentTabId, $currentTabName;
global $priority, $dPconfig;

$df = $AppUI->getPref('SHDATEFORMAT');

$editProjectsAllowed = getPermission('projects', 'edit');
foreach ($projects as $row) {
	$editProjectsAllowed = (($editProjectsAllowed)
		|| getPermission('projects', 'edit', $row['project_id']));
}

$base_table_cols = 9;
$table_cols = $base_table_cols + (($editProjectsAllowed) ? 1 : 0);
$added_cols = $table_cols - $base_table_cols;
?>

<form action='./index.php' method='get' class="overflow-x-auto">
	<table class="min-w-full divide-y divide-gray-200 shadow-sm rounded-lg overflow-hidden">
		<thead class="bg-gray-50">
			<tr>
				<td colspan="<?php echo ($base_table_cols); ?>"
					class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<?php echo $AppUI->_('sort by'); ?>:
				</td>
				<?php if ($added_cols) { ?>
					<td colspan="<?php echo ($added_cols); ?>">&nbsp;</td>
				<?php } ?>
			</tr>
			<tr>
				<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?m=projects&amp;orderby=project_color_identifier" class="hover:text-gray-700">
						<?php echo $AppUI->_('Color'); ?>
						(<a href="?m=projects&amp;orderby=project_percent_complete" class="hover:text-gray-700">%</a>)
					</a>
				</th>
				<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?m=projects&amp;orderby=company_name" class="hover:text-gray-700">
						<?php echo $AppUI->_('Company'); ?>
					</a>
				</th>
				<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?m=projects&amp;orderby=project_name" class="hover:text-gray-700">
						<?php echo $AppUI->_('Project Name'); ?>
					</a>
				</th>
				<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?m=projects&amp;orderby=project_start_date" class="hover:text-gray-700">
						<?php echo $AppUI->_('Start'); ?>
					</a>
				</th>
				<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?m=projects&amp;orderby=project_end_date" class="hover:text-gray-700">
						<?php echo $AppUI->_('Due Date'); ?>
					</a>
				</th>
				<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?m=projects&amp;orderby=project_actual_end_date" class="hover:text-gray-700">
						<?php echo $AppUI->_('Actual'); ?>
					</a>
				</th>
				<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?m=projects&amp;orderby=task_log_problem%20DESC,project_priority"
						class="hover:text-gray-700">
						<?php echo $AppUI->_('P'); ?>
					</a>
				</th>
				<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?m=projects&amp;orderby=user_username" class="hover:text-gray-700">
						<?php echo $AppUI->_('Owner'); ?>
					</a>
				</th>
				<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
					<a href="?m=projects&amp;orderby=total_tasks"
						class="hover:text-gray-700"><?php echo $AppUI->_('Tasks'); ?></a>
					<a href="?m=projects&amp;orderby=my_tasks"
						class="hover:text-gray-700">(<?php echo $AppUI->_('My'); ?>)</a>
				</th>
				<?php if ($editProjectsAllowed) { ?>
					<th scope="col"
						class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
						<?php echo $AppUI->_('Selection'); ?>
					</th>
				<?php } ?>
			</tr>
		</thead>
		<tbody class="bg-white divide-y divide-gray-200">
			<?php
			$none = true;
			$companyAccessCache = array();
			foreach ($projects as $row) {
				if ($row['project_status'] == 3) {
					$none = false;
					$start_date = ((intval(@$row['project_start_date'])) ? new CDate($row['project_start_date']) : null);
					$end_date = ((intval(@$row['project_end_date'])) ? new CDate($row['project_end_date']) : null);
					$actual_end_date = ((intval(@$row['project_actual_end_date'])) ? new CDate($row['project_actual_end_date']) : null);
					$style = ((($actual_end_date > $end_date) && !empty($end_date)) ? 'class="text-red-600 font-bold"' : '');

					// Determine color styles
					$hex = $row['project_color_identifier'];
					// Simple contrast check
					$brightness = 128; // Default
					// (Assume bestColor function handles hex to text color logic, or we simplify)
					?>
					<tr class="hover:bg-gray-50 transition-colors">
						<td class="px-6 py-4 whitespace-nowrap">
							<div class="flex items-center">
								<div class="flex-shrink-0 h-8 w-8 rounded-full flex items-center justify-center text-xs font-bold shadow-sm"
									style="background-color:<?php echo $row['project_color_identifier']; ?>; color:<?php echo bestColor($row['project_color_identifier']); ?>">
									<?php echo sprintf('%.0f%%', $row['project_percent_complete']); ?>
								</div>
							</div>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
							<?php
							$companyId = (int) $row['project_company'];
							if (!array_key_exists($companyId, $companyAccessCache)) {
								$companyAccessCache[$companyId] = getPermission('companies', 'access', $companyId);
							}

							if ($companyAccessCache[$companyId]) {
								echo '<a href="?m=companies&amp;a=view&amp;company_id=' . $row['project_company'] . '" class="text-primary-600 hover:text-primary-900 font-medium" title="' . htmlspecialchars($row['company_description'], ENT_QUOTES) . '">' . htmlspecialchars($row['company_name'], ENT_QUOTES) . '</a>';
							} else {
								echo htmlspecialchars($row['company_name'], ENT_QUOTES);
							}
							?>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
							<a href="?m=projects&amp;a=view&amp;project_id=<?php echo $row['project_id']; ?>"
								class="text-primary-600 hover:text-primary-900"
								title="<?php echo htmlspecialchars($row['project_description'], ENT_QUOTES); ?>">
								<?php echo htmlspecialchars($row['project_name'], ENT_QUOTES); ?>
							</a>
							<!-- Removed dPshowImage info icon if description exists, tooltip is on link -->
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
							<?php echo $start_date ? $start_date->format($df) : '-'; ?>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
							<?php echo $end_date ? $end_date->format($df) : '-'; ?>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
							<?php
							if ($actual_end_date) {
								echo '<a href="?m=tasks&amp;a=view&amp;task_id=' . $row['critical_task'] . '" ' . $style . '>' . $actual_end_date->format($df) . '</a>';
							} else {
								echo '-';
							}
							?>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-center">
							<?php
							if ($row['task_log_problem']) {
								echo '<a href="?m=tasks&amp;a=index&amp;f=all&amp;project_id=' . $row['project_id'] . '" class="text-red-500 hover:text-red-700" title="Problem Report">';
								echo '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
								echo '</a>';
							} else if ($row['project_priority'] != 0) {
								$color = $row['project_priority'] > 0 ? 'text-red-500' : 'text-green-500';
								$icon = $row['project_priority'] > 0 ? '↑' : '↓';
								echo '<span class="' . $color . ' font-bold text-lg" title="Priority ' . $row['project_priority'] . '">' . $icon . abs($row['project_priority']) . '</span>';
							} else {
								echo '&nbsp;';
							}
							?>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
							<?php echo htmlspecialchars($row['user_username'], ENT_QUOTES); ?>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500">
							<?php echo $row['total_tasks']; ?>
							<?php if ($row['my_tasks'])
								echo ' <span class="font-bold text-primary-600">(' . $row['my_tasks'] . ')</span>'; ?>
						</td>
						<?php if ($editProjectsAllowed) { ?>
							<td class="px-6 py-4 whitespace-nowrap text-center">
								<?php if (getPermission('projects', 'edit', $row['project_id'])) { ?>
									<input type="checkbox" name="project_id[]" value="<?php echo $row['project_id']; ?>"
										class="rounded text-primary-600 focus:ring-primary-500 h-4 w-4" />
								<?php } ?>
							</td>
						<?php } ?>
					</tr>
				<?php }
			}

			if ($none) { ?>
				<tr>
					<td colspan="<?php echo ($table_cols); ?>" class="px-6 py-4 text-center text-gray-500">
						<?php echo $AppUI->_('No projects available'); ?></td>
				</tr>
			<?php } else { ?>
				<tr class="bg-gray-50">
					<td colspan="<?php echo ($table_cols); ?>" class="px-6 py-3 text-right">
						<div class="flex items-center justify-end space-x-3">
							<?php echo arraySelect($pstatus, 'project_status', 'class="block w-40 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md"', 2, true); ?>
							<input type="hidden" name="update_project_status" value="1" />
							<input type="hidden" name="m" value="projects" />
							<input type="submit"
								class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
								value="<?php echo $AppUI->_('Update projects status'); ?>" />
						</div>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
</form>