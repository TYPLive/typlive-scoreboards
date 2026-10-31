(() => {
	'use strict';

	const app = document.querySelector('[data-schedule-app]');
	if (!app) return;

	const rowsRoot = app.querySelector('[data-schedule-rows]');
	const empty = app.querySelector('[data-filter-empty]');
	const feedState = app.querySelector('[data-feed-state]');
	const updated = app.querySelector('[data-updated]');
	let activeFilter = 'all';

	const element = (tag, className, value) => {
		const node = document.createElement(tag);
		if (className) node.className = className;
		if (value !== undefined && value !== null) node.textContent = String(value);
		return node;
	};

	const applyFilter = () => {
		let visible = 0;
		const rows = rowsRoot ? Array.from(rowsRoot.querySelectorAll('[data-player]')) : [];
		rows.forEach((row) => {
			const show = activeFilter === 'all' || row.dataset.player === activeFilter;
			row.hidden = !show;
			if (show) visible += 1;
		});
		if (empty) empty.hidden = rows.length === 0 || visible !== 0;
	};

	const render = (data) => {
		if (!rowsRoot || !Array.isArray(data?.schedule)) return;
		rowsRoot.replaceChildren();

		if (data.schedule.length === 0) {
			rowsRoot.append(element('p', 'upusa-empty', 'No published matches yet. This page will update when the schedule is released.'));
		} else {
			data.schedule.forEach((match) => {
				const row = element('article', `upusa-match${match.live ? ' is-live' : ''}`);
				row.dataset.player = match.player_slug || '';

				const timeGroup = element('div', 'upusa-match-time');
				const time = element('time', '', match.scheduled_label || match.scheduled_time || 'Time TBC');
				if (match.scheduled_time) time.dateTime = String(match.scheduled_time);
				timeGroup.append(time);
				if (match.live) timeGroup.append(element('span', '', 'Live'));

				const player = element('div', 'upusa-match-player');
				player.append(element('strong', '', match.tracked_player || 'Tracked player'), element('small', '', 'Tracked player'));

				const detail = element('div', 'upusa-match-detail');
				detail.append(
					element('strong', '', `vs ${match.opponent_name || 'Opponent TBC'}`),
					element('span', '', `${match.tournament || 'Competition'} · ${match.round || 'Round TBC'}`)
				);

				row.append(timeGroup, player, detail, element('span', 'upusa-match-table', match.table || 'Table TBC'));
				rowsRoot.append(row);
			});
		}

		if (updated) {
			const stamp = data.updated_at ? new Date(data.updated_at) : new Date();
			updated.textContent = `Updated ${stamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
		}
		feedState?.classList.remove('is-reconnecting');
		applyFilter();
	};

	app.querySelectorAll('[data-filter]').forEach((button) => {
		button.addEventListener('click', () => {
			activeFilter = button.dataset.filter || 'all';
			app.querySelectorAll('[data-filter]').forEach((candidate) => {
				candidate.setAttribute('aria-pressed', String(candidate === button));
			});
			applyFilter();
		});
	});

	const endpoint = app.dataset.endpoint;
	const refreshSeconds = Math.max(10, Math.min(60, Number(app.dataset.refresh) || 15));
	const poll = async () => {
		try {
			const controller = new AbortController();
			const timeout = window.setTimeout(() => controller.abort(), 15000);
			const response = await fetch(`${endpoint}${endpoint.includes('?') ? '&' : '?'}_=${Date.now()}`, {
				cache: 'no-store',
				credentials: 'same-origin',
				signal: controller.signal,
			});
			window.clearTimeout(timeout);
			if (!response.ok) throw new Error(`HTTP ${response.status}`);
			render(await response.json());
		} catch (error) {
			feedState?.classList.add('is-reconnecting');
			if (updated) updated.textContent = 'Reconnecting…';
		} finally {
			window.setTimeout(poll, refreshSeconds * 1000);
		}
	};

	applyFilter();
	window.setTimeout(poll, 1000);
})();
