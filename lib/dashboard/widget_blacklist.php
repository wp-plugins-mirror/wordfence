<?php
//$d is defined here as a wfDashboard instance

if (!isset($limit)) { $limit = 10; }
?>
<div class="wf-row">
	<div class="wf-col-xs-12">
		<div class="wf-dashboard-item active">
			<div class="wf-dashboard-item-inner">
				<div class="wf-dashboard-item-content">
					<div class="wf-dashboard-item-title">
						<strong>IP Blacklist: </strong><span class="wf-dashboard-item-inline-subtitle">Last 7 Days</span>
					</div>
					<div class="wf-dashboard-item-action"><div class="wf-dashboard-item-action-disclosure"></div></div>
				</div>
			</div>
			<div class="wf-dashboard-item-extra">
				<ul class="wf-dashboard-item-list">
					<li>
						<div>
							<div class="wf-blacklist wf-blacklist-7d">
								<?php if (count($d->blacklist7d['counts']) == 0): ?>
									<div class="wf-dashboard-item-list-text"><p><em>No blocks have been recorded.</em></p></div>
								<?php else: ?>
									<?php $data = array_slice($d->blacklist7d['counts'], 0, min($limit, count($d->blacklist7d['counts'])), true); include(dirname(__FILE__) . '/widget_content_blacklist.php'); ?>
									<?php if (count($d->blacklist7d['counts']) > $limit): ?>
										<div class="wf-dashboard-item-list-text"><div class="wf-dashboard-show-more" data-grouping="blacklist" data-period="7d"><a href="#">Show more</a></div></div>
									<?php endif; ?>
								<?php endif; ?>
							</div>
							<script type="application/javascript">
								(function($) {
									$('.wf-blacklist .wf-dashboard-show-more a').on('click', function(e) {
										e.preventDefault();
										e.stopPropagation();

										var grouping = $(this).parent().data('grouping');
										var period = $(this).parent().data('period');

										$(this).closest('.wf-dashboard-item-list-text').fadeOut();

										var self = this;
										WFAD.ajax('wordfence_dashboardShowMore', {
											grouping: grouping,
											period: period
										}, function(res) {
											if (res.ok) {
												var table = $('#blacklist-data-template').tmpl(res);
												$(self).closest('.wf-blacklist').css('overflow-y', 'auto');
												$(self).closest('.wf-blacklist').find('table').replaceWith(table);
											}
											else {
												WFAD.colorbox('300px', 'An error occurred', 'We encountered an error trying load more data.');
												$(this).closest('.wf-dashboard-item-list-text').fadeIn();
											}
										});
									});
								})(jQuery);
							</script>
						</div>
					</li>
				</ul>
			</div>
		</div>
	</div>
</div>
<script type="text/x-jquery-template" id="blacklist-data-template">
	<table class="wf-table wf-table-hover">
		<thead>
		<tr>
			<th>IP</th>
			<th colspan="2">Country</th>
			<th>Blocked Attacks Local</th>
			<th>Blocked Attacks Network</th>
		</tr>
		</thead>
		<tbody>
		{{each(idx, d) data}}
		<tr>
			<td>${d.IP}</td>
			<td>${d.countryName}</td>
			<td><img src="${d.countryFlag}" class="wfFlag" height="11" width="16" alt="${d.countryName}" title="${d.countryName}"></td>
			<td>${d.localCount}</td>
			<td>${d.networkCount}</td>
		</tr>
		{{/each}}
		</tbody>
	</table>
</script>