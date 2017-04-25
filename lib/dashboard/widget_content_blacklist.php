<?php //$data is defined here as an array of blacklisted block totals: array('ip' => text ip, 'countryCode' => string, 'local' => int, 'network' => int, 'countryName' => string) ?>
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
	<?php foreach ($data as $l): ?>
		<tr>
			<td><?php echo esc_html($l['ip']); ?></td>
			<td><?php echo esc_html($l['countryName']); ?></td>
			<td><img src="<?php echo wfUtils::getBaseURL() . 'images/flags/' . esc_attr(strtolower($l['countryCode'])); ?>.png" class="wfFlag" height="11" width="16" alt="<?php echo esc_attr($l['countryName']); ?>" title="<?php echo esc_attr($l['countryName']); ?>"></td>
			<td><?php echo esc_html(number_format_i18n($l['local'])); ?></td>
			<td><?php echo esc_html(number_format_i18n($l['network'])); ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>