<div class="wf-row">
	<div class="wf-col-xs-12">
		<div class="wf-dashboard-item active">
			<div class="wf-dashboard-item-inner">
				<div class="wf-dashboard-item-content">
					<div class="wf-dashboard-item-title">
						<strong>Threat Defense Feed</strong>
					</div>
					<div class="wf-dashboard-item-action"><div class="wf-dashboard-item-action-disclosure"></div></div>
				</div>
			</div>
			<div class="wf-dashboard-item-extra">
				<ul class="wf-dashboard-item-list">
					<li class="wf-dashboard-item-list-header"><p>IP Blacklist</p></li>
					<li class="wf-dashboard-item-list-body">
						<?php if ($d->tdfBlacklist === null): ?>
							<div class="wf-dashboard-item-list-text"><em>Threat Defense Feed statistics will be updated soon.</em></div>
						<?php else: ?>
							<ul class="wf-dashboard-item-list wf-dashboard-item-list-horizontal">
								<li>
									<div class="wf-dashboard-item-labeled-count">
										<div class="wf-dashboard-item-labeled-count-count"><?php echo $d->tdfBlacklist; ?></div>
										<div class="wf-dashboard-item-labeled-count-label">Blacklisted IP Addresses</div>
									</div>
								</li>
							</ul>
						<?php endif; ?>
					</li>
					<?php if (!wfConfig::get('isPaid')): ?>
						<li class="wf-dashboard-item-list-footer">
							<div class="wf-dashboard-item-list-text">
								<p>Premium users are protected by the Wordfence Real-time IP Blacklist, blocking all requests from the most malicious attackers. Upgrade today to enable this feature.</p>
							</div>
						</li>
					<?php else: ?>
						<li class="wf-dashboard-item-list-footer">
							<div class="wf-dashboard-item-list-text">
								<p>Premium users are protected by the Wordfence Real-time IP Blacklist, blocking all requests from the most malicious attackers.</p>
							</div>
						</li>
					<?php endif; ?>
					<li class="wf-dashboard-item-list-header"><p>Total Firewall Rules and Malware Signatures</p></li>
					<li class="wf-dashboard-item-list-body">
						<?php if ($d->tdfCommunity === null): ?>
							<div class="wf-dashboard-item-list-text"><em>Threat Defense Feed statistics will be updated soon.</em></div>
						<?php else: ?>
							<ul class="wf-dashboard-item-list wf-dashboard-item-list-horizontal">
								<li>
									<div class="wf-dashboard-item-labeled-count">
										<div class="wf-dashboard-item-labeled-count-count"><?php echo $d->tdfCommunity; ?></div>
										<div class="wf-dashboard-item-labeled-count-label">Free Count</div>
									</div>
								</li>
								<li>
									<div class="wf-dashboard-item-labeled-count">
										<div class="wf-dashboard-item-labeled-count-count"><?php echo $d->tdfPremium; ?></div>
										<div class="wf-dashboard-item-labeled-count-label">Premium Count</div>
									</div>
								</li>
								<li>
									<div class="wf-dashboard-item-labeled-count">
										<div class="wf-dashboard-item-labeled-count-count"><?php echo max($d->tdfPremium - $d->tdfCommunity, 0); ?></div>
										<div class="wf-dashboard-item-labeled-count-label">Premium Only Count</div>
									</div>
								</li>
							</ul>
						<?php endif; ?>
					</li>
					<?php if (!wfConfig::get('isPaid')): ?>
						<li class="wf-dashboard-item-list-footer">
							<div class="wf-dashboard-item-list-text">
								<p>As a free Wordfence user, you are currently using the Community version of the Threat Defense Feed. Premium users are protected by an additional <?php echo ($d->tdfPremium - $d->tdfCommunity); ?> firewall rules and malware signatures. Upgrade to Premium today to improve your protection.</p>
								<p><a class="wf-btn wf-btn-primary wf-btn-callout" href="https://www.wordfence.com/gnl1scanUpgrade/wordfence-signup/" target="_blank">Upgrade to Premium</a></p>
							</div>
						</li>
					<?php else: ?>
						<li class="wf-dashboard-item-list-footer">
							<div class="wf-dashboard-item-list-text">
								<p>As a Premium user you receive updates to the Threat Defense Feed in real-time. You are currently protected by an additional <?php echo ($d->tdfPremium - $d->tdfCommunity); ?> firewall rules and malware signatures.</p>
							</div>
						</li>
					<?php endif; ?>
				</ul>
			</div>
		</div>
	</div>
</div>