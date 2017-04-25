<div class="wf-row">
	<div class="wf-col-xs-12">
		<div class="wf-dashboard-item active">
			<div class="wf-dashboard-item-inner">
				<div class="wf-dashboard-item-content">
					<div class="wf-dashboard-item-title">
						<strong>Firewall Summary: </strong><span class="wf-dashboard-item-inline-subtitle">Attacks Blocked for <?php echo esc_html(preg_replace('/^[^:]+:\/\//', '', network_site_url())); ?></span>
					</div>
					<div class="wf-dashboard-item-action"><div class="wf-dashboard-item-action-disclosure"></div></div>
				</div>
			</div>
			<div class="wf-dashboard-item-extra">
				<ul class="wf-dashboard-item-list">
					<li>
						<?php
						$hasSome = false;
						foreach ($d->localBlocks as $row) {
							if ($row['24h'] > 0 || $row['7d'] > 0 || $row['30d'] > 0) {
								$hasSome = true;
								break;
							}
						}
						
						if (!$hasSome):
						?>
							<div class="wf-dashboard-item-list-text"><em>No blocks have been recorded.</em></div>
						<?php else: ?>
							<table class="wf-table wf-table-hover">
								<thead>
								<tr>
									<th>Block Type</th>
									<th>Today</th>
									<th>Week</th>
									<th>Month</th>
								</tr>
								</thead>
								<tbody>
								<?php
								$totals = array('24h' => 0, '7d' => 0, '30d' => 0);
								foreach ($d->localBlocks as $row): ?>
									<tr>
										<td><?php echo esc_html($row['title']); ?></td>
										<td><?php echo esc_html(number_format_i18n($row['24h'])); ?></td>
										<td><?php echo esc_html(number_format_i18n($row['7d'])); ?></td>
										<td><?php echo esc_html(number_format_i18n($row['30d'])); ?></td>
									</tr>
									<?php $totals['24h'] += $row['24h']; $totals['7d'] += $row['7d']; $totals['30d'] += $row['30d']; ?>
								<?php endforeach; ?>
								</tbody>
								<tfoot>
								<tr>
									<th>Total</th>
									<th><?php echo esc_html(number_format_i18n($totals['24h'])); ?></th>
									<th><?php echo esc_html(number_format_i18n($totals['7d'])); ?></th>
									<th><?php echo esc_html(number_format_i18n($totals['30d'])); ?></th>
								</tr>
								</tfoot>
							</table>
						<?php endif; ?>
					</li>
				</ul>
			</div>
		</div>
	</div>
</div>