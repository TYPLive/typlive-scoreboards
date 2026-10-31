(() => {
	'use strict';

	const app = document.querySelector('[data-schedule-app]');
	if (!app) return;

	const rowsRoots = new Map(
		Array.from(app.querySelectorAll('[data-schedule-rows]')).map((root) => [root.dataset.scheduleRows, root])
	);
	const playerSelect = app.querySelector('[data-player-select]');
	const feedState = app.querySelector('[data-feed-state]');
	const updated = app.querySelector('[data-updated]');
	let activeFilter = 'all';

	const element = (tag, className, value) => {
		const node = document.createElement(tag);
		if (className) node.className = className;
		if (value !== undefined && value !== null) node.textContent = String(value);
		return node;
	};

	const matchState = (match) => {
		if (match?.state === 'live' || match?.live) return 'live';
		if (match?.state === 'result' || match?.completed) return 'result';
		return 'upcoming';
	};

	const applyFilter = () => {
		app.querySelectorAll('[data-schedule-section]').forEach((section) => {
			const rows = Array.from(section.querySelectorAll('[data-player]'));
			let visible = 0;
			rows.forEach((row) => {
				const show = activeFilter === 'all' || row.dataset.player === activeFilter;
				row.hidden = !show;
				if (show) visible += 1;
			});

			const empty = section.querySelector('[data-section-empty]');
			const count = section.querySelector('[data-section-count]');
			if (empty) empty.hidden = visible !== 0;
			if (count) count.textContent = String(visible);
		});
	};

	const setFilter = (value) => {
		activeFilter = value || 'all';
		app.querySelectorAll('[data-filter]').forEach((button) => {
			button.setAttribute('aria-pressed', String(button.dataset.filter === activeFilter));
		});
		if (playerSelect && playerSelect.value !== activeFilter) playerSelect.value = activeFilter;
		applyFilter();
	};

	const buildRow = (match) => {
		const state = matchState(match);
		const row = element('article', `upusa-match${state === 'live' ? ' is-live' : ''}`);
		row.dataset.player = match.player_slug || '';
		row.dataset.matchState = state;

		const timeGroup = element('div', 'upusa-match-time');
		const time = element('time', '', match.scheduled_label || match.scheduled_time || 'Date & time TBC');
		if (match.scheduled_time) time.dateTime = String(match.scheduled_time);
		timeGroup.append(time);
		if (state === 'live') timeGroup.append(element('span', '', 'Live'));

		const player = element('div', 'upusa-match-player');
		player.append(element('strong', '', match.tracked_player || 'Player'));

		const detail = element('div', 'upusa-match-detail');
		const copy = element('div', 'upusa-match-copy');
		copy.append(
			element('strong', '', `vs ${match.opponent_name || 'Opponent TBC'}`),
			element('span', '', `${match.tournament || 'Competition'} · ${match.round || 'Round TBC'}`)
		);
		detail.append(copy);

		const showScore = state === 'live' || (state === 'result' && match.score_available !== false);
		if (showScore) {
			const outcome = state === 'live' ? 'Current' : (match.result_outcome || 'Final');
			const outcomeClass = String(outcome).toLowerCase().replace(/[^a-z0-9-]/g, '');
			const score = element('div', `upusa-inline-score is-${outcomeClass}`);
			score.append(
				element('strong', '', `${Number(match.player_score) || 0} – ${Number(match.opponent_score) || 0}`),
				element('small', '', outcome)
			);
			detail.append(score);
		}

		row.append(timeGroup, player, detail, element('span', 'upusa-match-table', match.table || 'Table TBC'));
		return row;
	};

	const render = (data) => {
		if (!Array.isArray(data?.schedule)) return;
		rowsRoots.forEach((root) => root.replaceChildren());

		data.schedule.forEach((match) => {
			const root = rowsRoots.get(matchState(match));
			if (root) root.append(buildRow(match));
		});

		if (updated) {
			const stamp = data.updated_at ? new Date(data.updated_at) : new Date();
			updated.textContent = `Updated ${stamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
		}
		feedState?.classList.remove('is-reconnecting');
		applyFilter();
	};

	app.querySelectorAll('[data-filter]').forEach((button) => {
		button.addEventListener('click', () => setFilter(button.dataset.filter));
	});
	playerSelect?.addEventListener('change', () => setFilter(playerSelect.value));

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

	setFilter('all');
	window.setTimeout(poll, 1000);
})();
