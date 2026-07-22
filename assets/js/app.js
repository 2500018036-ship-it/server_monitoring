(function (window) {
	'use strict';

	if (!window.jQuery) {
		return;
	}

	var $ = window.jQuery;
	var charts = {};
	var refreshTimer = null;
	var navbarRefreshTimer = null;
	var currentAlertSignature = '';
	var pullInFlight = false;
	var pullDisabledServers = {};
	var lastPullAt = 0;
	var lastPullServerId = '';

	function showFlashMessage() {
		if (!window.SM_FLASH) {
			return;
		}

		if (window.SM_FLASH.success) {
			fireAlert('success', window.SM_FLASH.success);
		}

		if (window.SM_FLASH.error) {
			fireAlert('error', window.SM_FLASH.error);
		}
	}

	function fireAlert(icon, message) {
		if (window.Swal) {
			window.Swal.fire({
				icon: icon,
				title: icon === 'success' ? 'Berhasil' : 'Gagal',
				html: message,
				timer: icon === 'success' ? 2200 : undefined,
				showConfirmButton: icon !== 'success'
			});
			return;
		}

		window.alert(String(message).replace(/<br\s*\/?>/gi, '\n'));
	}

	function bindConfirmForms() {
		$('.confirm-form').on('submit', function (event) {
			var form = this;
			var message = $(form).data('confirm') || 'Lanjutkan aksi ini?';

			if (!window.Swal) {
				if (!window.confirm(message)) {
					event.preventDefault();
				}
				return;
			}

			event.preventDefault();
			window.Swal.fire({
				icon: 'warning',
				title: 'Konfirmasi',
				text: message,
				showCancelButton: true,
				confirmButtonText: 'Ya',
				cancelButtonText: 'Batal'
			}).then(function (result) {
				if (result.isConfirmed) {
					form.submit();
				}
			});
		});
	}

	function initDataTables() {
		if (!$.fn.DataTable) {
			return;
		}

		$('.datatable').each(function () {
			var $table = $(this);
			var withExport = $table.hasClass('datatable-export');

			$table.DataTable({
				autoWidth: false,
				pageLength: 10,
				order: [],
				dom: withExport ? 'Bfrtip' : 'frtip',
				buttons: withExport ? ['excelHtml5', 'pdfHtml5', 'print'] : []
			});
		});
	}

	function initDarkMode() {
		var key = 'serverMonitoringDarkMode';
		var enabled = window.localStorage && window.localStorage.getItem(key) === '1';
		var $body = $('body');
		var $button = $('#darkModeToggle');
		var $icon = $button.find('i');

		function apply(isEnabled) {
			$body.toggleClass('dark-mode', isEnabled);
			$icon.toggleClass('fa-sun', isEnabled);
			$icon.toggleClass('fa-moon', !isEnabled);
		}

		apply(enabled);

		$button.on('click', function () {
			enabled = !$body.hasClass('dark-mode');
			apply(enabled);

			if (window.localStorage) {
				window.localStorage.setItem(key, enabled ? '1' : '0');
			}
		});
	}

	function config() {
		return window.SM_CONFIG || { baseUrl: '/', pollInterval: 3000 };
	}

	function apiUrl(path, params) {
		var url = config().baseUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, '');
		var query = $.param(params || {});

		return query ? url + '?' + query : url;
	}

	function csrfData(extra) {
		var data = extra || {};

		if (window.SM_CSRF && window.SM_CSRF.name) {
			data[window.SM_CSRF.name] = window.SM_CSRF.hash;
		}

		return data;
	}

	function updateCsrf(response) {
		if (response && response.csrf_hash && window.SM_CSRF) {
			window.SM_CSRF.hash = response.csrf_hash;
		}
	}

	function postJson(path, data) {
		return $.post(apiUrl(path), csrfData(data || {}))
			.done(updateCsrf)
			.fail(function (xhr) {
				updateCsrf(xhr.responseJSON);
			});
	}

	function text(value, suffix) {
		if (value === null || value === undefined || value === '') {
			return '-';
		}

		return suffix ? value + suffix : value;
	}

	function percent(value) {
		if (value === null || value === undefined || value === '') {
			return '-';
		}

		return Number(value).toFixed(2).replace(/\.00$/, '') + '%';
	}

	function bytesPerSecond(value) {
		if (!value) {
			return '0 B/s';
		}

		var units = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
		var size = Number(value);
		var index = 0;

		while (size >= 1024 && index < units.length - 1) {
			size = size / 1024;
			index++;
		}

		return size.toFixed(index === 0 ? 0 : 2) + ' ' + units[index];
	}

	function duration(seconds) {
		if (!seconds) {
			return '-';
		}

		var total = Number(seconds);
		var days = Math.floor(total / 86400);
		var hours = Math.floor((total % 86400) / 3600);
		var minutes = Math.floor((total % 3600) / 60);

		return (days ? days + 'd ' : '') + hours + 'h ' + minutes + 'm';
	}

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
	}

	function hasObjectKeys(value) {
		return value && typeof value === 'object' && !Array.isArray(value) && Object.keys(value).length > 0;
	}

	function badge(value) {
		var normalized = String(value || 'unknown').toLowerCase();
		var css = 'secondary';

		if (normalized === 'online' || normalized === 'running' || normalized === 'active') {
			css = 'success';
		} else if (normalized === 'offline' || normalized === 'stopped' || normalized === 'error' || normalized === 'critical') {
			css = 'danger';
		} else if (normalized === 'warning') {
			css = 'warning';
		}

		return '<span class="badge badge-' + css + '">' + escapeHtml(normalized) + '</span>';
	}

	function healthClass(status) {
		status = String(status || 'offline').toLowerCase();

		if (status === 'online') {
			return 'success';
		}

		if (status === 'warning') {
			return 'warning';
		}

		return 'danger';
	}

	function healthLabel(status) {
		status = String(status || 'offline').toLowerCase();

		return status.charAt(0).toUpperCase() + status.slice(1);
	}

	function healthBadge(status) {
		return '<span class="badge badge-' + healthClass(status) + '">' + healthLabel(status) + '</span>';
	}

	function serverSummary(server) {
		server = server || {};

		if (server.health_status === 'offline') {
			return server.health_summary || ('Offline' + (server.last_seen_text ? ' - Last Seen ' + server.last_seen_text : ''));
		}

		return server.health_summary || [server.operating_system, server.public_ip].filter(Boolean).join(' | ') || '-';
	}

	function chartData(rows, field) {
		rows = rows || [];

		return {
			labels: rows.map(function (row) {
				return (row.metric_time || '').slice(11, 19);
			}),
			values: rows.map(function (row) {
				return Number(row[field] || 0);
			})
		};
	}

	function createLineChart(canvasId, datasets, max) {
		var canvas = document.getElementById(canvasId);

		if (!canvas || !window.Chart) {
			return null;
		}

		return new window.Chart(canvas, {
			type: 'line',
			data: {
				labels: [],
				datasets: datasets
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				animation: false,
				plugins: {
					legend: {
						position: 'bottom'
					}
				},
				scales: {
					y: {
						beginAtZero: true,
						max: max || undefined
					}
				}
			}
		});
	}

	function initRealtimeCharts() {
		charts.resource = createLineChart('resourceRealtimeChart', [
			{ label: 'CPU', data: [], borderColor: '#007bff', backgroundColor: 'rgba(0,123,255,.1)', tension: .35 },
			{ label: 'RAM', data: [], borderColor: '#20c997', backgroundColor: 'rgba(32,201,151,.1)', tension: .35 },
			{ label: 'Disk', data: [], borderColor: '#6610f2', backgroundColor: 'rgba(102,16,242,.1)', tension: .35 }
		], 100);

		charts.network = createLineChart('networkRealtimeChart', [
			{ label: 'Upload B/s', data: [], borderColor: '#fd7e14', backgroundColor: 'rgba(253,126,20,.1)', tension: .35 },
			{ label: 'Download B/s', data: [], borderColor: '#17a2b8', backgroundColor: 'rgba(23,162,184,.1)', tension: .35 }
		]);
	}

	function updateCharts(data) {
		if (!data.charts) {
			return;
		}

		var cpu = chartData(data.charts.cpu, 'usage_percent');
		var memory = chartData(data.charts.memory, 'usage_percent');
		var storage = chartData(data.charts.storage, 'disk_percentage');
		var upload = chartData(data.charts.network_upload, 'upload_speed');
		var download = chartData(data.charts.network_download, 'download_speed');

		if (charts.resource) {
			charts.resource.data.labels = cpu.labels;
			charts.resource.data.datasets[0].data = cpu.values;
			charts.resource.data.datasets[1].data = memory.values;
			charts.resource.data.datasets[2].data = storage.values;
			charts.resource.update();
		}

		if (charts.network) {
			charts.network.data.labels = upload.labels.length ? upload.labels : download.labels;
			charts.network.data.datasets[0].data = upload.values;
			charts.network.data.datasets[1].data = download.values;
			charts.network.update();
		}
	}

	function setServerSelector(servers, selectedId) {
		var $selector = $('#serverSelector');
		var signature = (servers || []).map(function (server) {
			return [server.id, server.server_name, server.health_status || server.status, server.health_summary || ''].join(':');
		}).join('|');

		if (!$selector.length || ($selector.data('signature') === signature && Number($selector.val()) === Number(selectedId))) {
			return;
		}

		$selector.data('signature', signature);
		$selector.empty();

		if (!servers.length) {
			$selector.append('<option value="">Belum ada agent terhubung</option>');
			return;
		}

		servers.forEach(function (server) {
			var label = server.server_name + ' - ' + (server.health_label || healthLabel(server.health_status || server.status));
			var selected = Number(server.id) === Number(selectedId) ? ' selected' : '';
			$selector.append('<option value="' + server.id + '"' + selected + '>' + escapeHtml(label) + '</option>');
		});
	}

	function currentUrlWithServerId(serverId) {
		var url = new URL(window.location.href);
		url.searchParams.set('server_id', serverId);

		return url.toString();
	}

	function updateNavbarServers(servers, selectedId, selectedServer) {
		var $switcher = $('#navbarServerSwitcher');
		var $list = $('#navbarServerList');

		if (!$switcher.length || !$list.length) {
			return;
		}

		selectedServer = selectedServer || (servers || []).filter(function (server) {
			return Number(server.id) === Number(selectedId);
		})[0] || {};

		var status = selectedServer.health_status || selectedServer.status || 'offline';
		var badgeClass = healthClass(status);
		var signature = JSON.stringify((servers || []).map(function (server) {
			return [server.id, server.server_name, server.health_status, server.health_summary, server.last_seen_text].join(':');
		})) + '|' + selectedId;

		$switcher.attr('data-selected-id', selectedId || 0);
		$('#navbarCurrentServerName').text(selectedServer.server_name || 'No Server');
		$('#navbarCurrentServerMeta').text(serverSummary(selectedServer));
		$('#navbarCurrentServerBadge')
			.removeClass('badge-success badge-warning badge-danger badge-secondary')
			.addClass('badge-' + badgeClass)
			.text(selectedServer.health_label || healthLabel(status));
		$('#navbarCurrentServerDot')
			.removeClass('status-online status-warning status-offline')
			.addClass('status-' + status);

		if ($list.data('signature') === signature) {
			return;
		}

		$list.data('signature', signature);
		$list.empty();
		$list.append('<span class="dropdown-item dropdown-header">Server Switcher</span><div class="dropdown-divider"></div>');

		if (!servers || !servers.length) {
			$list.append('<span class="dropdown-item text-muted">Belum ada server.</span>');
			return;
		}

		servers.forEach(function (server) {
			var itemStatus = server.health_status || server.status || 'offline';
			var current = Number(server.id) === Number(selectedId);
			var currentBadge = current ? ' <span class="badge badge-primary">Current</span>' : '';

			$list.append(
				'<a href="' + escapeHtml(currentUrlWithServerId(server.id)) + '" class="dropdown-item navbar-server-option" data-server-id="' + server.id + '">' +
					'<div class="d-flex align-items-start">' +
						'<span class="server-status-dot status-' + escapeHtml(itemStatus) + ' mt-1"></span>' +
						'<div class="server-switcher-item flex-fill">' +
							'<div class="d-flex justify-content-between align-items-center">' +
								'<strong>' + escapeHtml(server.server_name || '-') + '</strong>' +
								'<span>' + healthBadge(itemStatus) + currentBadge + '</span>' +
							'</div>' +
							'<small class="text-muted">' + escapeHtml(serverSummary(server)) + '</small>' +
						'</div>' +
					'</div>' +
				'</a>'
			);
		});
	}

	function updateAlerts(alerts) {
		alerts = alerts || [];
		var signature = JSON.stringify(alerts);
		var $wrap = $('#realtimeAlerts');

		$('#activeAlerts').text(alerts.length);

		if (!$wrap.length || signature === currentAlertSignature) {
			return;
		}

		currentAlertSignature = signature;
		$wrap.empty();

		alerts.forEach(function (alert) {
			$wrap.append(
				'<div class="alert alert-' + escapeHtml(alert.level || 'warning') + ' alert-dismissible fade show">' +
				'<button type="button" class="close" data-dismiss="alert">&times;</button>' +
				escapeHtml(alert.message || '') +
				'</div>'
			);
		});
	}

	function fillRows(selector, rows, emptyCols, builder) {
		var $body = $(selector);

		if (!$body.length) {
			return;
		}

		$body.empty();

		if (!rows || !rows.length) {
			$body.append('<tr><td colspan="' + emptyCols + '" class="text-muted text-center">Belum ada data realtime.</td></tr>');
			return;
		}

		rows.forEach(function (row) {
			$body.append(builder(row));
		});
	}

	function updateTables(data) {
		var payload = data.payload || {};
		var cpuProcesses = ((payload.processes || {}).top_cpu || (payload.cpu || {}).top_processes || []);
		var memoryProcesses = ((payload.processes || {}).top_memory || (payload.memory || {}).top_processes || []);

		fillRows('#topCpuProcessRows', cpuProcesses, 6, processRow);
		fillRows('#topMemoryProcessRows', memoryProcesses, 6, processRow);
		fillRows('#serviceRows', payload.services || [], 4, function (row) {
			return '<tr><td>' + escapeHtml(row.name) + '</td><td>' + badge(row.status) + '</td><td>' + escapeHtml(row.logged_at || data.metric_time || '-') + '</td><td>' + escapeHtml(row.log_excerpt || '-') + '</td></tr>';
		});
		fillRows('#dockerRows', ((payload.docker || {}).containers || []), 5, function (row) {
			return '<tr><td>' + escapeHtml(row.container_name || row.container_id || '-') + '</td><td>' + escapeHtml(row.image || '-') + '</td><td>' + badge(row.status) + '</td><td>' + percent(row.cpu) + '</td><td>' + escapeHtml(row.ram || '-') + '</td></tr>';
		});
		fillRows('#websiteRows', payload.websites || [], 5, function (row) {
			return '<tr><td>' + escapeHtml(row.domain || '-') + '</td><td>' + badge(row.status) + '</td><td>' + escapeHtml(row.http_status || '-') + '</td><td>' + text(row.response_time_ms, ' ms') + '</td><td>' + escapeHtml(row.last_check || '-') + '</td></tr>';
		});
		fillRows('#databaseRows', hasObjectKeys(payload.database) ? [payload.database] : [], 5, function (row) {
			return '<tr><td>' + escapeHtml(row.engine || '-') + '</td><td>' + badge(row.status) + '</td><td>' + text(row.database_size_mb, ' MB') + '</td><td>' + escapeHtml(row.threads || '-') + '</td><td>' + duration(row.uptime_seconds) + '</td></tr>';
		});
		fillRows('#logRows', payload.logs || [], 5, logRow);
	}

	function processRow(row) {
		return '<tr>' +
			'<td>' + escapeHtml(row.pid || '-') + '</td>' +
			'<td>' + escapeHtml(row.user || '-') + '</td>' +
			'<td class="text-truncate-sm" title="' + escapeHtml(row.command || '-') + '">' + escapeHtml(row.command || '-') + '</td>' +
			'<td>' + percent(row.cpu) + '</td>' +
			'<td>' + percent(row.ram) + '</td>' +
			'<td>' + escapeHtml(row.running_time || '-') + '</td>' +
		'</tr>';
	}

	function logRow(row) {
		var levelClass = row.level === 'error' || row.level === 'critical' ? 'table-danger' : (row.level === 'warning' ? 'table-warning' : '');

		return '<tr class="' + levelClass + '">' +
			'<td>' + escapeHtml(row.logged_at || '-') + '</td>' +
			'<td>' + escapeHtml(row.log_type || row.type || '-') + '</td>' +
			'<td>' + badge(row.level) + '</td>' +
			'<td>' + escapeHtml(row.source || '-') + '</td>' +
			'<td>' + escapeHtml(row.message || '-') + '</td>' +
		'</tr>';
	}

	function updateDashboard(data) {
		var server = data.server || {};
		var metric = data.metric || {};
		var payload = data.payload || {};
		var cpu = payload.cpu || {};
		var memory = payload.memory || {};
		var storage = payload.storage || {};
		var network = payload.network || {};
		var online = (data.servers || []).filter(function (item) {
			return item.health_status === 'online' || item.health_status === 'warning';
		}).length;
		var healthStatus = server.health_status || server.status || 'offline';

		setServerSelector(data.servers || [], data.selected_server_id);
		updateNavbarServers(data.servers || [], data.selected_server_id, server);
		$('#realtimeDashboard').attr('data-server-id', data.selected_server_id || 0);
		$('#totalServers').text((data.servers || []).length);
		$('#onlineServers').text(online);
		$('#serverStatus').text(server.health_label || healthLabel(healthStatus));
		$('#serverStatusBox')
			.removeClass('bg-success bg-warning bg-danger')
			.addClass('bg-' + healthClass(healthStatus));
		$('#cpuUsage').text(percent(metric.cpu_usage));
		$('#memoryUsage').text(percent(metric.memory_usage));
		$('#diskUsage').text(percent(metric.disk_usage));
		$('#activeConnections').text(text(metric.active_connections));

		$('#serverName').text(text(server.server_name));
		$('#hostname').text(text(server.hostname));
		$('#operatingSystem').text(text(server.operating_system));
		$('#kernel').text(text(server.kernel));
		$('#architecture').text(text(server.architecture));
		$('#uptime').text(duration(server.uptime_seconds));
		$('#currentTime').text(text(server.current_time));
		$('#timezone').text(text(server.timezone));
		$('#publicIp').text(text(server.public_ip));
		$('#privateIp').text(text(server.private_ip));
		$('#azureRegion').text(text(server.azure_region));
		$('#lastHeartbeat').text(text(server.last_heartbeat_at));
		$('#latency').text(text(server.last_latency_ms, ' ms'));
		$('#responseTime').text(text(server.last_response_time_ms, ' ms'));
		$('#serverHealthStatus').html(healthBadge(healthStatus));
		$('#serverMonitoringStatus').text(server.status ? healthLabel(server.status) : '-');

		$('#cpuCores').text(text(cpu.cores));
		$('#cpuModel').text(text(cpu.model));
		$('#cpuFreq').text(text(cpu.frequency_mhz, ' MHz'));
		$('#loadAverage').text(cpu.load_1 === undefined ? '-' : cpu.load_1 + ' / ' + cpu.load_5 + ' / ' + cpu.load_15);
		$('#ramTotal').text(text(memory.total_mb, ' MB'));
		$('#ramUsed').text(text(memory.used_mb, ' MB'));
		$('#ramFree').text(text(memory.free_mb, ' MB'));
		$('#swapFree').text(text(memory.swap_free_mb, ' MB'));
		$('#diskTotal').text(text(storage.disk_total_gb, ' GB'));
		$('#diskUsed').text(text(storage.disk_used_gb, ' GB'));
		$('#diskFree').text(text(storage.disk_free_gb, ' GB'));
		$('#mountPoint').text(text(storage.mount_point));
		$('#uploadSpeed').text(bytesPerSecond(network.upload_speed));
		$('#downloadSpeed').text(bytesPerSecond(network.download_speed));
		$('#networkInterface').text(text(network.interface_name));
		$('#packetLoss').text(percent(network.packet_loss));

		updateAlerts(data.alerts || []);
		updateCharts(data);
		updateTables(data);
	}

	function refreshRealtimeDashboard() {
		var $dashboard = $('#realtimeDashboard');

		if (!$dashboard.length) {
			return;
		}

		var serverId = Number($('#serverSelector').val() || $dashboard.attr('data-server-id') || 0);

		pullSshMetrics(serverId).always(function () {
			$.getJSON(apiUrl('api/server', { server_id: serverId }))
				.done(function (response) {
					if (response && response.ok) {
						updateDashboard(response);
					}
				});
		});
	}

	function pullSshMetrics(serverId) {
		var deferred = $.Deferred();
		var now = Date.now();
		var targetServerId = String(serverId || '');
		var disabledKey = targetServerId || 'default';

		if (pullDisabledServers[disabledKey] || pullInFlight || (targetServerId === lastPullServerId && now - lastPullAt < 2500)) {
			return deferred.resolve().promise();
		}

		pullInFlight = true;
		lastPullAt = now;
		lastPullServerId = targetServerId;

		return postJson('api/pull-ssh-metrics', { server_id: serverId || '' })
			.done(function (response) {
				if (response && response.server_id) {
					$('#realtimeDashboard').attr('data-server-id', response.server_id);
				}
			})
			.fail(function (xhr) {
				if (xhr.status === 404) {
					pullDisabledServers[disabledKey] = true;
				}
			})
			.always(function () {
				pullInFlight = false;
			});
	}

	function bindRealtimeDashboard() {
		if (!$('#realtimeDashboard').length) {
			return;
		}

		initRealtimeCharts();
		refreshRealtimeDashboard();

		$('#serverSelector').on('change', function () {
			$('#realtimeDashboard').attr('data-server-id', this.value || 0);
			refreshRealtimeDashboard();
		});

		refreshTimer = window.setInterval(refreshRealtimeDashboard, Math.min(config().pollInterval || 3000, 3000));
	}

	function refreshNavbarServers() {
		var $switcher = $('#navbarServerSwitcher');

		if (!$switcher.length || $('#realtimeDashboard').length) {
			return;
		}

		var serverId = Number($switcher.attr('data-selected-id') || 0);

		pullSshMetrics(serverId).always(function () {
			$.getJSON(apiUrl('api/server', { server_id: serverId }))
				.done(function (response) {
					if (response && response.ok) {
						updateNavbarServers(response.servers || [], response.selected_server_id, response.server || {});
					}
				});
		});
	}

	function bindNavbarServerSwitcher() {
		if (!$('#navbarServerSwitcher').length || $('#realtimeDashboard').length) {
			return;
		}

		refreshNavbarServers();
		navbarRefreshTimer = window.setInterval(refreshNavbarServers, Math.min(config().pollInterval || 3000, 3000));
	}

	function bindLogViewer() {
		var $viewer = $('#logViewer');

		if (!$viewer.length) {
			return;
		}

		function loadLogs() {
			var serverId = $('#logServerSelector').val();

			pullSshMetrics(serverId).always(function () {
				$.getJSON(apiUrl('api/logs', {
					server_id: serverId,
					level: $('#logLevelFilter').val(),
					log_type: $('#logTypeFilter').val(),
					date: $('#logDateFilter').val(),
					search: $('#logSearch').val()
				})).done(function (response) {
					if (!response.ok) {
						return;
					}

					fillRows('#logViewerRows', response.logs || [], 5, logRow);

					if ($('#autoScrollLogs').is(':checked')) {
						var panel = document.querySelector('.log-viewer-panel');
						if (panel) {
							panel.scrollTop = panel.scrollHeight;
						}
					}
				});
			});
		}

		$('#logServerSelector, #logLevelFilter, #logTypeFilter, #logDateFilter').on('change', function () {
			if (this.id === 'logServerSelector') {
				$('#navbarServerSwitcher').attr('data-selected-id', this.value || 0);
			}
			loadLogs();
		});
		$('#logSearch').on('keyup', debounce(loadLogs, 350));
		$('#copyLogs').on('click', function () {
			var textValue = $('#logViewerRows').text();
			if (navigator.clipboard) {
				navigator.clipboard.writeText(textValue);
				fireAlert('success', 'Log berhasil disalin.');
			}
		});
		$('#downloadLogs').on('click', function () {
			var blob = new Blob([$('#logViewerRows').text()], { type: 'text/plain' });
			var url = URL.createObjectURL(blob);
			var link = document.createElement('a');
			link.href = url;
			link.download = 'server-logs.txt';
			link.click();
			URL.revokeObjectURL(url);
		});

		loadLogs();
		window.setInterval(loadLogs, Math.min(config().pollInterval || 3000, 3000));
	}

	function bindRemoteActionForms() {
		function sync(selector, target) {
			var value = $(selector).val();
			$(target).find('input[name="ssh_config_id"]').val(value);
		}

		sync('#quickSshConfig', '.quick-action-form');
		sync('#serviceSshConfig', '.service-action-form');
		$('#quickSshConfig').on('change', function () { sync('#quickSshConfig', '.quick-action-form'); });
		$('#serviceSshConfig').on('change', function () { sync('#serviceSshConfig', '.service-action-form'); });
	}

	function bindTerminalManager() {
		var $manager = $('#terminalManager');

		if (!$manager.length || !window.Terminal) {
			return;
		}

		var term = new window.Terminal({
			cursorBlink: true,
			theme: {
				background: '#101418',
				foreground: '#d7dde5'
			},
			scrollback: 5000
		});
		var fitAddon = window.FitAddon ? new window.FitAddon.FitAddon() : null;
		if (fitAddon) {
			term.loadAddon(fitAddon);
		}
		term.open(document.getElementById('xtermContainer'));
		if (fitAddon) {
			fitAddon.fit();
			$(window).on('resize', function () { fitAddon.fit(); });
		}
		term.writeln('Server Monitoring Terminal');
		term.writeln('Commands are executed over SSH via phpseclib.');
		term.write('$ ');

		function runCommand() {
			var command = $('#terminalCommand').val();
			var sshConfigId = $('#terminalSshConfig').val();

			if (!command) {
				return;
			}

			term.writeln(command);
			$('#terminalCommand').val('');
			postJson('terminal/execute', {
				ssh_config_id: sshConfigId,
				command: command
			}).done(function (response) {
				term.writeln(response.output || response.message || '');
				term.writeln('[exit: ' + (response.exit_status === null || response.exit_status === undefined ? 'n/a' : response.exit_status) + ']');
				term.write('$ ');
			}).fail(function () {
				term.writeln('Request failed.');
				term.write('$ ');
			});
		}

		$('#terminalRun').on('click', runCommand);
		$('#terminalCommand').on('keydown', function (event) {
			if (event.key === 'Enter') {
				event.preventDefault();
				runCommand();
			}
		});
		$('#terminalClear').on('click', function () {
			term.clear();
			term.write('$ ');
		});
		$('#terminalCopy').on('click', function () {
			if (navigator.clipboard) {
				navigator.clipboard.writeText(term.getSelection() || '');
			}
		});
		$('#terminalPaste').on('click', function () {
			if (navigator.clipboard) {
				navigator.clipboard.readText().then(function (value) {
					$('#terminalCommand').val($('#terminalCommand').val() + value).focus();
				});
			}
		});
		$('#terminalFullscreen').on('click', function () {
			var element = document.querySelector('#terminalManager .card');
			if (element && element.requestFullscreen) {
				element.requestFullscreen();
				window.setTimeout(function () { if (fitAddon) { fitAddon.fit(); } }, 250);
			}
		});
	}

	function bindFileManager() {
		var $manager = $('#fileManager');
		var editor = null;
		var editingPath = '';

		if (!$manager.length) {
			return;
		}

		function fileData(extra) {
			extra = extra || {};
			extra.ssh_config_id = $('#fileSshConfig').val();
			return extra;
		}

		function browse(path) {
			path = path || $('#fileCurrentPath').val() || '/';
			$('#fileCurrentPath').val(path);
			postJson('file_manager/browse', fileData({ path: path })).done(function (response) {
				var rows = response.items || [];
				fillRows('#fileRows', rows, 6, function (row) {
					var icon = row.type === 'folder' ? 'fa-folder' : 'fa-file';
					var open = row.type === 'folder'
						? '<button class="btn btn-sm btn-outline-primary file-open" data-path="' + escapeHtml(row.path) + '"><i class="fas fa-folder-open"></i></button>'
						: '<button class="btn btn-sm btn-outline-info file-edit" data-path="' + escapeHtml(row.path) + '"><i class="fas fa-edit"></i></button>';
					var download = row.type === 'file'
						? '<a class="btn btn-sm btn-outline-success" href="' + apiUrl('file_manager/download', { ssh_config_id: $('#fileSshConfig').val(), path: row.path }) + '"><i class="fas fa-download"></i></a>'
						: '';
					return '<tr>' +
						'<td><i class="fas ' + icon + ' mr-2"></i>' + escapeHtml(row.name) + '</td>' +
						'<td>' + escapeHtml(row.permission) + '</td>' +
						'<td>' + escapeHtml(row.owner) + '</td>' +
						'<td>' + escapeHtml(row.size) + '</td>' +
						'<td>' + escapeHtml(row.last_modified) + '</td>' +
						'<td>' + open + ' ' + download + ' <button class="btn btn-sm btn-outline-warning file-rename" data-path="' + escapeHtml(row.path) + '"><i class="fas fa-i-cursor"></i></button> <button class="btn btn-sm btn-outline-danger file-delete" data-path="' + escapeHtml(row.path) + '"><i class="fas fa-trash"></i></button></td>' +
					'</tr>';
				});
			});
		}

		function fileAction(action, source, target) {
			postJson('file_manager/action', fileData({
				file_action: action,
				source_path: source,
				target_path: target
			})).done(function (response) {
				fireAlert(response.ok ? 'success' : 'error', response.message || 'Done');
				browse();
			});
		}

		$('#fileSshConfig').on('change', function () { browse('/'); });
		$('#fileCurrentPath').on('keydown', function (event) {
			if (event.key === 'Enter') {
				browse(this.value);
			}
		});
		$('#fileRows').on('click', '.file-open', function () {
			browse($(this).data('path'));
		});
		$('#fileRows').on('click', '.file-delete', function () {
			var path = $(this).data('path');
			if (window.confirm('Delete ' + path + '?')) {
				fileAction('delete', path, '');
			}
		});
		$('#fileRows').on('click', '.file-rename', function () {
			var source = $(this).data('path');
			var target = window.prompt('Target path', source);
			if (target) {
				fileAction('rename', source, target);
			}
		});
		$('#fileRows').on('click', '.file-edit', function () {
			editingPath = $(this).data('path');
			postJson('file_manager/preview', fileData({ path: editingPath })).done(function (response) {
				if (!response.ok) {
					fireAlert('error', response.message);
					return;
				}

				$('#fileEditorTitle').text(editingPath);
				$('#fileEditorContent').val(response.content);
				$('#fileEditorModal').modal('show');
				window.setTimeout(function () {
					if (!editor && window.CodeMirror) {
						editor = window.CodeMirror.fromTextArea(document.getElementById('fileEditorContent'), {
							lineNumbers: true,
							theme: 'material-darker',
							mode: window.CodeMirror.findModeByFileName(editingPath) ? window.CodeMirror.findModeByFileName(editingPath).mode : null
						});
					}
					if (editor) {
						editor.setValue(response.content);
						editor.refresh();
					}
				}, 250);
			});
		});
		$('#fileEditorSave').on('click', function () {
			postJson('file_manager/save', fileData({
				path: editingPath,
				content: editor ? editor.getValue() : $('#fileEditorContent').val()
			})).done(function (response) {
				fireAlert(response.ok ? 'success' : 'error', response.message);
				if (response.ok) {
					$('#fileEditorModal').modal('hide');
				}
			});
		});
		$('#fileMkdirBtn').on('click', function () {
			var target = window.prompt('New folder path', ($('#fileCurrentPath').val() || '/') + '/new-folder');
			if (target) {
				fileAction('mkdir', '', target);
			}
		});
		$('#fileUploadBtn').on('click', function () {
			$('#fileUploadInput').click();
		});
		$('#fileUploadInput').on('change', function () {
			var file = this.files[0];
			if (!file) {
				return;
			}
			var data = new FormData();
			data.append('file', file);
			data.append('path', $('#fileCurrentPath').val() || '/');
			data.append('ssh_config_id', $('#fileSshConfig').val());
			if (window.SM_CSRF && window.SM_CSRF.name) {
				data.append(window.SM_CSRF.name, window.SM_CSRF.hash);
			}
			$.ajax({
				url: apiUrl('file_manager/upload'),
				type: 'POST',
				data: data,
				processData: false,
				contentType: false
			}).done(function (response) {
				updateCsrf(response);
				fireAlert(response.ok ? 'success' : 'error', response.message);
				browse();
			});
		});
		$('#fileSearchBtn').on('click', function () {
			postJson('file_manager/search', fileData({
				path: $('#fileCurrentPath').val() || '/',
				query: $('#fileSearch').val()
			})).done(function (response) {
				$('#fileSearchResult').removeClass('d-none').text(response.output || response.message || '');
			});
		});

		browse('/');
	}

	function debounce(fn, delay) {
		var timer;

		return function () {
			var args = arguments;
			window.clearTimeout(timer);
			timer = window.setTimeout(function () {
				fn.apply(null, args);
			}, delay);
		};
	}

	$(function () {
		initDarkMode();
		showFlashMessage();
		bindConfirmForms();
		initDataTables();
		bindRemoteActionForms();
		bindTerminalManager();
		bindFileManager();
		bindRealtimeDashboard();
		bindNavbarServerSwitcher();
		bindLogViewer();
	});

	window.addEventListener('beforeunload', function () {
		if (refreshTimer) {
			window.clearInterval(refreshTimer);
		}
		if (navbarRefreshTimer) {
			window.clearInterval(navbarRefreshTimer);
		}
	});
})(window);
